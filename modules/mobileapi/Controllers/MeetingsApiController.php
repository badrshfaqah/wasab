<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Notification;
use App\Core\Permission;
use Modules\Meetings\Models\Meeting;
use Modules\Meetings\Models\MeetingAttendee;
use Modules\Meetings\Models\MeetingNote;
use Modules\Mobileapi\Support\Api;

/**
 * نقاط JSON للاجتماعات - نفس قواعد MeetingController الويب، مع تحويل تحذير
 * التعارضات إلى رد 409 صريح يعاد إرساله مع confirm_conflicts=1 للتأكيد.
 */
class MeetingsApiController
{
    private const TYPES = ['in_person', 'online'];
    private const STATUSES = ['scheduled', 'completed', 'cancelled'];
    private const RECURRENCE_RULES = ['none', 'weekly', 'monthly'];
    private const MAX_RECURRENCE_OCCURRENCES = 52;

    // ---------- القوائم ----------

    /** GET /api/v1/meetings?status=&q=&page= */
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requireView();

        $filters = [];
        $status = (string) Api::input('status', '');
        if (in_array($status, self::STATUSES, true)) {
            $filters['status'] = $status;
        }
        $q = trim((string) Api::input('q', ''));
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $page = max(1, (int) Api::input('page', 1));
        $result = Meeting::paginate($companyId, Auth::id(), $this->canManage(), $filters, $page, 15);

        Api::ok([
            'meetings' => array_map([$this, 'summaryPayload'], $result['rows']),
            'total' => (int) $result['total'],
            'page' => $page,
            'per_page' => 15,
            'can_create' => $this->can('meetings.create'),
            'can_manage' => $this->canManage(),
        ]);
    }

    /** GET /api/v1/meetings/users - مستخدمو الشركة النشطون (لاختيار الحضور). */
    public function companyUsers(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->requireView();

        $rows = Database::select(
            'SELECT id, name FROM users WHERE company_id = :c AND status = "active" ORDER BY name',
            ['c' => $companyId]
        );
        Api::ok(['users' => array_map(
            fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name']],
            $rows
        )]);
    }

    /** GET /api/v1/meetings/{id} */
    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);

        $attendees = MeetingAttendee::forMeeting((int) $meeting['id']);
        $mine = MeetingAttendee::findForUser((int) $meeting['id'], Auth::id());

        $creator = Database::first('SELECT name FROM users WHERE id = :id', ['id' => $meeting['created_by']]);
        $canMakeTask = ModuleManager::isActive('tasks') && Permission::check('tasks.create');

        Api::ok([
            'meeting' => $this->detailPayload($meeting, $creator['name'] ?? null),
            'attendees' => array_map(fn ($a) => [
                'id' => (int) $a['id'],
                'user_id' => isset($a['user_id']) && $a['user_id'] ? (int) $a['user_id'] : null,
                'name' => $a['user_name'] ?? ($a['external_name'] ?? ''),
                'is_external' => empty($a['user_id']),
                'response' => $a['response'],
                'responded_at' => $a['responded_at'] ?? null,
            ], $attendees),
            'notes' => array_map(fn ($n) => [
                'id' => (int) $n['id'],
                'phase' => $n['phase'],
                'body' => $n['body'],
                'user_name' => $n['user_name'] ?? '',
                'created_at' => $n['created_at'],
            ], MeetingNote::forMeeting((int) $meeting['id'])),
            'my_response' => $mine ? $mine['response'] : null,
            'can_respond' => $mine !== null,
            'can_edit' => $this->canEditMeeting($meeting),
            'can_delete' => $this->canDeleteMeeting($meeting),
            'can_make_task' => $canMakeTask,
        ]);
    }

    // ---------- الإنشاء والتعديل ----------

    /** POST /api/v1/meetings */
    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('meetings.create')) {
            Api::error('لا تملك صلاحية إنشاء اجتماع.', 403, 'forbidden');
        }

        $data = $this->validated();
        [$userIds, $externals] = $this->parseAttendees($companyId);
        $this->checkConflictsOrFail($companyId, $userIds, $data['starts_at'], $data['ends_at'], null);

        $recurrenceRule = (string) Api::input('recurrence_rule', 'none');
        if (!in_array($recurrenceRule, self::RECURRENCE_RULES, true)) {
            $recurrenceRule = 'none';
        }
        $recurrenceEnd = trim((string) Api::input('recurrence_end_date', ''));
        if ($recurrenceRule !== 'none' && $recurrenceEnd === '') {
            Api::error('حدد تاريخ نهاية التكرار.', 422, 'validation');
        }

        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();
        $data['recurrence_rule'] = $recurrenceRule;
        $data['recurrence_end_date'] = $recurrenceRule !== 'none' ? $recurrenceEnd : null;

        $meetingId = Meeting::create($data);
        $this->applyAttendees($meetingId, $userIds, $externals);
        ActivityLog::log('meetings.create', 'meeting', (string) $meetingId, 'إنشاء اجتماع من الجوال: ' . $data['title']);
        $this->notifyAttendees($meetingId, 'دعوة لحضور اجتماع', $data['title']);

        if ($recurrenceRule !== 'none') {
            Meeting::update($meetingId, ['recurrence_group_id' => $meetingId]);
            $occurrences = Meeting::generateOccurrences(
                $data['starts_at'],
                $data['ends_at'],
                $recurrenceRule,
                $recurrenceEnd,
                self::MAX_RECURRENCE_OCCURRENCES
            );
            foreach ($occurrences as [$startsAt, $endsAt]) {
                $occurrenceId = Meeting::create([
                    'company_id' => $companyId,
                    'title' => $data['title'],
                    'type' => $data['type'],
                    'location' => $data['location'],
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'description' => $data['description'],
                    'agenda' => $data['agenda'],
                    'created_by' => Auth::id(),
                    'recurrence_rule' => $recurrenceRule,
                    'recurrence_end_date' => $recurrenceEnd,
                    'recurrence_group_id' => $meetingId,
                ]);
                $this->applyAttendees($occurrenceId, $userIds, $externals);
            }
        }

        Api::ok(['id' => $meetingId], 201);
    }

    /** POST /api/v1/meetings/{id} - تعديل. */
    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            Api::error('لا تملك صلاحية تعديل هذا الاجتماع.', 403, 'forbidden');
        }

        $data = $this->validated();
        [$userIds, $externals] = $this->parseAttendees($companyId);
        $this->checkConflictsOrFail($companyId, $userIds, $data['starts_at'], $data['ends_at'], (int) $meeting['id']);

        if ($data['starts_at'] !== $meeting['starts_at']) {
            $data['reminder_sent_at'] = null;
        }

        Meeting::update((int) $meeting['id'], $data);
        $this->applyAttendees((int) $meeting['id'], $userIds, $externals);
        ActivityLog::log('meetings.update', 'meeting', (string) $meeting['id'], 'تعديل اجتماع من الجوال: ' . $data['title']);

        Api::ok(['id' => (int) $meeting['id']]);
    }

    /** POST /api/v1/meetings/{id}/delete */
    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canDeleteMeeting($meeting)) {
            Api::error('لا تملك صلاحية حذف هذا الاجتماع.', 403, 'forbidden');
        }

        Meeting::delete((int) $meeting['id']);
        ActivityLog::log('meetings.delete', 'meeting', (string) $meeting['id'], 'حذف اجتماع من الجوال: ' . $meeting['title']);
        Api::ok(['message' => 'تم حذف الاجتماع.']);
    }

    // ---------- الرد والمحاضر ----------

    /** POST /api/v1/meetings/{id}/respond  {response: accepted|declined} */
    public function respond(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);

        $mine = MeetingAttendee::findForUser((int) $meeting['id'], Auth::id());
        if (!$mine) {
            Api::error('لست مدعواً لهذا الاجتماع.', 422, 'not_invited');
        }

        $response = (string) Api::input('response', '');
        if (!in_array($response, ['accepted', 'declined'], true)) {
            Api::error('قيمة الرد غير صحيحة.', 422, 'validation');
        }

        MeetingAttendee::respond((int) $mine['id'], $response);
        ActivityLog::log('meetings.respond', 'meeting', (string) $meeting['id'], ($response === 'accepted' ? 'قبول' : 'اعتذار عن') . ' دعوة اجتماع من الجوال');

        if ((int) $meeting['created_by'] !== Auth::id()) {
            $verb = $response === 'accepted' ? 'قبل' : 'اعتذر عن';
            Notification::send(
                (int) $meeting['created_by'],
                'رد على دعوة اجتماع',
                (Auth::user()['name'] ?? '') . " {$verb} حضور: " . $meeting['title'],
                route('/meetings/' . $meeting['id'])
            );
        }

        Api::ok(['response' => $response]);
    }

    /** POST /api/v1/meetings/{id}/notes  {phase: before|after, body} */
    public function addNote(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);

        $phase = (string) Api::input('phase', 'before');
        if (!in_array($phase, ['before', 'after'], true)) {
            $phase = 'before';
        }
        $body = trim((string) Api::input('body', ''));
        if ($body === '') {
            Api::error('اكتب نص الملاحظة.', 422, 'validation');
        }

        MeetingNote::add((int) $meeting['id'], Auth::id(), $phase, $body);
        Api::ok(['message' => 'تمت إضافة الملاحظة.'], 201);
    }

    /** POST /api/v1/meetings/{id}/notes/{noteId}/task  {assignee_id, due_date?} */
    public function noteToTask(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);

        if (!ModuleManager::isActive('tasks') || !Permission::check('tasks.create')) {
            Api::error('لا تملك صلاحية إنشاء مهمة.', 403, 'forbidden');
        }

        $note = Database::first(
            'SELECT * FROM meetings_notes WHERE id = :id AND meeting_id = :m LIMIT 1',
            ['id' => (int) $params['noteId'], 'm' => $meeting['id']]
        );
        if (!$note) {
            Api::error('الملاحظة غير موجودة.', 404, 'not_found');
        }

        $assigneeId = (int) Api::input('assignee_id', 0);
        $assignee = Database::first(
            'SELECT id FROM users WHERE id = :id AND company_id = :c AND status = "active" LIMIT 1',
            ['id' => $assigneeId, 'c' => $companyId]
        );
        if (!$assignee) {
            Api::error('اختر مسنداً إليه من مستخدمي شركتك.', 422, 'validation');
        }

        $dueDate = trim((string) Api::input('due_date', ''));
        $taskId = Database::insert('tasks_tasks', [
            'company_id' => $companyId,
            'title' => mb_substr($note['body'], 0, 200),
            'description' => 'قرار من اجتماع «' . $meeting['title'] . '» بتاريخ ' . substr((string) $meeting['starts_at'], 0, 10) . ":\n\n" . $note['body'],
            'assignee_id' => $assigneeId,
            'creator_id' => Auth::id(),
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'priority' => 'medium',
            'status' => 'todo',
            'requires_approval' => 0,
            'linked_type' => 'meeting',
            'linked_id' => (int) $meeting['id'],
            'linked_label' => mb_substr($meeting['title'], 0, 200),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Notification::send($assigneeId, '📋 مهمة جديدة من اجتماع', mb_substr($note['body'], 0, 120), route('/tasks/' . $taskId));
        ActivityLog::log('meetings.note_to_task', 'task', (string) $taskId, 'تحويل قرار اجتماع إلى مهمة من الجوال');

        Api::ok(['task_id' => $taskId], 201);
    }

    /** POST /api/v1/meetings/{id}/outcomes  {outcomes} */
    public function updateOutcomes(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            Api::error('لا تملك صلاحية تعديل هذا الاجتماع.', 403, 'forbidden');
        }

        $outcomes = trim((string) Api::input('outcomes', ''));
        Meeting::update((int) $meeting['id'], ['outcomes' => $outcomes !== '' ? $outcomes : null]);
        ActivityLog::log('meetings.outcomes', 'meeting', (string) $meeting['id'], 'تحديث مخرجات اجتماع من الجوال');

        Api::ok(['message' => 'تم حفظ المخرجات.']);
    }

    /** POST /api/v1/meetings/{id}/status  {action: complete|cancel|reopen} */
    public function changeStatus(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            Api::error('لا تملك صلاحية تعديل هذا الاجتماع.', 403, 'forbidden');
        }

        $map = ['complete' => 'completed', 'cancel' => 'cancelled', 'reopen' => 'scheduled'];
        $action = (string) Api::input('action', '');
        if (!isset($map[$action])) {
            Api::error('إجراء غير معروف.', 422, 'validation');
        }

        $current = $meeting['status'];
        $allowed = ($action === 'reopen' && in_array($current, ['completed', 'cancelled'], true))
            || (in_array($action, ['complete', 'cancel'], true) && $current === 'scheduled');
        if (!$allowed) {
            Api::error('لا يمكن تنفيذ هذا الإجراء على حالة الاجتماع الحالية.', 422, 'invalid_transition');
        }

        Meeting::update((int) $meeting['id'], ['status' => $map[$action]]);
        ActivityLog::log('meetings.status', 'meeting', (string) $meeting['id'], 'تغيير حالة اجتماع من الجوال');

        Api::ok(['status' => $map[$action]]);
    }

    // ---------- نفس قواعد MeetingController الويب ----------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            Api::error('حسابك غير مرتبط بشركة.', 422, 'no_company');
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return Permission::check($key) || Permission::check('meetings.manage');
    }

    private function requireView(): void
    {
        if (!$this->can('meetings.view')) {
            Api::error('لا تملك صلاحية مشاهدة الاجتماعات.', 403, 'forbidden');
        }
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('meetings.manage');
    }

    private function canEditMeeting(array $meeting): bool
    {
        return $this->canManage()
            || ($this->can('meetings.edit') && (int) $meeting['created_by'] === Auth::id());
    }

    private function canDeleteMeeting(array $meeting): bool
    {
        return $this->canManage()
            || (Permission::check('meetings.delete') && (int) $meeting['created_by'] === Auth::id());
    }

    private function findVisible(int $id, int $companyId): array
    {
        $meeting = Meeting::find($id);
        if (!$meeting || (int) $meeting['company_id'] !== $companyId) {
            Api::error('الاجتماع غير موجود.', 404, 'not_found');
        }
        $this->requireView();

        $visible = $this->canManage()
            || (int) $meeting['created_by'] === Auth::id()
            || MeetingAttendee::findForUser($id, Auth::id()) !== null;
        if (!$visible) {
            Api::error('لا تملك صلاحية عرض هذا الاجتماع.', 403, 'forbidden');
        }

        return $meeting;
    }

    /** نفس حقول validated() في الويب مع تحقق إضافي من صيغة التاريخ. */
    private function validated(): array
    {
        $title = trim((string) Api::input('title', ''));
        if ($title === '') {
            Api::error('عنوان الاجتماع مطلوب.', 422, 'validation');
        }

        $type = (string) Api::input('type', 'in_person');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'in_person';
        }

        $startsAt = $this->normalizeDateTime((string) Api::input('starts_at', ''));
        if ($startsAt === null) {
            Api::error('حدد وقت بداية صحيحاً للاجتماع.', 422, 'validation');
        }
        $endsAt = $this->normalizeDateTime((string) Api::input('ends_at', ''));
        if ($endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            Api::error('وقت النهاية يجب أن يكون بعد وقت البداية.', 422, 'validation');
        }

        $location = trim((string) Api::input('location', ''));
        $description = trim((string) Api::input('description', ''));
        $agenda = trim((string) Api::input('agenda', ''));

        return [
            'title' => mb_substr($title, 0, 255),
            'type' => $type,
            'location' => $location !== '' ? mb_substr($location, 0, 255) : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'description' => $description !== '' ? $description : null,
            'agenda' => $agenda !== '' ? $agenda : null,
        ];
    }

    /** يقبل "Y-m-d H:i" أو "Y-m-dTH:i" (مع ثوانٍ اختيارية)، يعيد null عند الفراغ أو صيغة خاطئة. */
    private function normalizeDateTime(string $value): ?string
    {
        $value = trim(str_replace('T', ' ', $value));
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value) || strtotime($value) === false) {
            Api::error('صيغة التاريخ والوقت غير صحيحة.', 422, 'validation');
        }
        return strlen($value) === 16 ? $value . ':00' : $value;
    }

    /** @return array{0: int[], 1: array<array{name: string, contact: ?string}>} */
    private function parseAttendees(int $companyId): array
    {
        $requested = Api::input('attendee_user_ids');
        $userIds = [];
        if (is_array($requested)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $requested), fn ($v) => $v > 0)));
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $rows = Database::select(
                    "SELECT id FROM users WHERE id IN ({$in}) AND company_id = ? AND status = 'active'",
                    array_merge($ids, [$companyId])
                );
                $userIds = array_map(fn ($r) => (int) $r['id'], $rows);
            }
        }

        // مدعوون خارجيون: مصفوفة [{name, contact}] من التطبيق.
        $externals = [];
        $externalInput = Api::input('external_attendees');
        if (is_array($externalInput)) {
            foreach ($externalInput as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $contact = trim((string) ($row['contact'] ?? ''));
                $externals[] = ['name' => mb_substr($name, 0, 150), 'contact' => $contact !== '' ? mb_substr($contact, 0, 150) : null];
            }
        }

        return [$userIds, $externals];
    }

    /** مطابقة سلوك applyAttendees في الويب: الاحتفاظ بردود الحضور الحاليين. */
    private function applyAttendees(int $meetingId, array $userIds, array $externals): void
    {
        $existing = MeetingAttendee::forMeeting($meetingId);

        $keepInternal = [];
        $keepExternal = [];
        foreach ($existing as $attendee) {
            if (!empty($attendee['user_id'])) {
                if (in_array((int) $attendee['user_id'], $userIds, true)) {
                    $keepInternal[] = (int) $attendee['user_id'];
                } else {
                    Database::delete('meetings_attendees', 'id = :id', ['id' => $attendee['id']]);
                }
            } else {
                $key = mb_strtolower(trim((string) $attendee['external_name'])) . '|' . mb_strtolower(trim((string) ($attendee['external_contact'] ?? '')));
                $requested = array_map(
                    fn ($e) => mb_strtolower($e['name']) . '|' . mb_strtolower((string) ($e['contact'] ?? '')),
                    $externals
                );
                if (in_array($key, $requested, true)) {
                    $keepExternal[] = $key;
                } else {
                    Database::delete('meetings_attendees', 'id = :id', ['id' => $attendee['id']]);
                }
            }
        }

        foreach ($userIds as $userId) {
            if (!in_array($userId, $keepInternal, true)) {
                MeetingAttendee::addInternal($meetingId, $userId);
            }
        }
        foreach ($externals as $external) {
            $key = mb_strtolower($external['name']) . '|' . mb_strtolower((string) ($external['contact'] ?? ''));
            if (!in_array($key, $keepExternal, true)) {
                MeetingAttendee::addExternal($meetingId, $external['name'], $external['contact']);
            }
        }
    }

    /** تحذير التعارضات: 409 مع نص التعارضات ما لم يُرسل confirm_conflicts=1. */
    private function checkConflictsOrFail(int $companyId, array $userIds, string $startsAt, ?string $endsAt, ?int $excludeId): void
    {
        if (filter_var(Api::input('confirm_conflicts', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        $conflicts = Meeting::findConflicts(
            $companyId,
            array_values(array_unique(array_merge($userIds, [Auth::id()]))),
            Auth::id(),
            $startsAt,
            $endsAt,
            $excludeId
        );
        if (!$conflicts) {
            return;
        }

        $lines = array_map(
            fn ($c) => '• ' . $c['title'] . ' (' . $c['starts_at'] . ') - ' . ($c['person_name'] ?? ''),
            array_slice($conflicts, 0, 5)
        );
        Api::error(
            "يوجد تعارض مع اجتماعات أخرى:\n" . implode("\n", $lines) . "\n\nأعد الحفظ للتأكيد رغم التعارض.",
            409,
            'conflicts'
        );
    }

    private function notifyAttendees(int $meetingId, string $title, string $message): void
    {
        foreach (MeetingAttendee::forMeeting($meetingId) as $attendee) {
            $userId = isset($attendee['user_id']) ? (int) $attendee['user_id'] : 0;
            if ($userId && $userId !== Auth::id()) {
                Notification::send($userId, $title, $message, route('/meetings/' . $meetingId));
            }
        }
    }

    // ---------- التسلسل ----------

    private function summaryPayload(array $m): array
    {
        return [
            'id' => (int) $m['id'],
            'title' => $m['title'],
            'type' => $m['type'],
            'status' => $m['status'],
            'starts_at' => $m['starts_at'],
            'ends_at' => $m['ends_at'] ?? null,
            'location' => $m['location'] ?? null,
            'creator_name' => $m['creator_name'] ?? null,
            'is_recurring' => !empty($m['recurrence_group_id']),
        ];
    }

    private function detailPayload(array $m, ?string $creatorName): array
    {
        $m['creator_name'] = $creatorName;
        return $this->summaryPayload($m) + [
            'description' => $m['description'] ?? null,
            'agenda' => $m['agenda'] ?? null,
            'outcomes' => $m['outcomes'] ?? null,
            'recurrence_rule' => $m['recurrence_rule'] ?? 'none',
            'recurrence_end_date' => $m['recurrence_end_date'] ?? null,
            'created_by' => (int) $m['created_by'],
            'created_at' => $m['created_at'] ?? null,
        ];
    }
}
