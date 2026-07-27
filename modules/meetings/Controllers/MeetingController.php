<?php

namespace Modules\Meetings\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Request;
use App\Core\View;
use Modules\Meetings\Models\Meeting;
use Modules\Meetings\Models\MeetingAttendee;
use Modules\Meetings\Models\MeetingNote;

class MeetingController
{
    private const TYPES = ['in_person', 'online'];
    private const STATUSES = ['scheduled', 'completed', 'cancelled'];
    private const RECURRENCE_RULES = ['none', 'weekly', 'monthly'];
    private const MAX_RECURRENCE_OCCURRENCES = 52;

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('meetings.view')) {
            $this->forbidden();
            return;
        }

        $filters = $this->currentFilters();
        $page = max(1, (int) Request::query('page', 1));
        $result = Meeting::paginate($companyId, Auth::id(), $this->canManage(), $filters, $page, 15);

        View::render('meetings::index', [
            'pageTitle' => 'الاجتماعات',
            'meetings' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 15,
            'filters' => $filters,
            'canCreate' => $this->can('meetings.create'),
            'canManage' => $this->canManage(),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('meetings.create')) {
            $this->forbidden();
            return;
        }

        View::render('meetings::form', [
            'pageTitle' => 'اجتماع جديد',
            'isEdit' => false,
            'formAction' => route('/meetings'),
            'meeting' => null,
            'attendees' => [],
            'companyUsers' => $this->companyUsers($companyId),
            'conflicts' => [],
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('meetings.create')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/meetings/create');

        $data = $this->validated();
        if ($data === null) {
            redirect('/meetings/create');
        }

        [$userIds, $external] = $this->parseAttendeeRequest($companyId);

        if (!Request::input('confirm_conflicts')) {
            $conflicts = Meeting::findConflicts($companyId, array_merge($userIds, [Auth::id()]), Auth::id(), $data['starts_at'], $data['ends_at'], null);
            if ($conflicts) {
                View::render('meetings::form', [
                    'pageTitle' => 'اجتماع جديد',
                    'isEdit' => false,
                    'formAction' => route('/meetings'),
                    'meeting' => $data,
                    'attendees' => $this->syntheticAttendees($userIds, $external),
                    'companyUsers' => $this->companyUsers($companyId),
                    'conflicts' => $conflicts,
                ]);
                return;
            }
        }

        $recurrenceRule = Request::input('recurrence_rule', 'none');
        if (!in_array($recurrenceRule, self::RECURRENCE_RULES, true)) {
            $recurrenceRule = 'none';
        }
        $recurrenceEndDate = trim((string) Request::input('recurrence_end_date', ''));
        if ($recurrenceRule !== 'none' && $recurrenceEndDate === '') {
            flash_set('error', 'يرجى تحديد تاريخ نهاية التكرار.');
            View::render('meetings::form', [
                'pageTitle' => 'اجتماع جديد',
                'isEdit' => false,
                'formAction' => route('/meetings'),
                'meeting' => array_merge($data, ['recurrence_rule' => $recurrenceRule]),
                'attendees' => $this->syntheticAttendees($userIds, $external),
                'companyUsers' => $this->companyUsers($companyId),
                'conflicts' => [],
            ]);
            return;
        }

        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();
        $data['recurrence_rule'] = $recurrenceRule;
        $data['recurrence_end_date'] = $recurrenceRule !== 'none' ? $recurrenceEndDate : null;

        $meetingId = Meeting::create($data);
        $this->applyAttendees($meetingId, $userIds, $external);

        ActivityLog::log('meetings.create', 'meeting', $meetingId, "إنشاء اجتماع: {$data['title']}");
        $this->notifyAttendees($meetingId, 'دعوة لحضور اجتماع', $data['title']);

        $occurrenceCount = 0;
        if ($recurrenceRule !== 'none') {
            Meeting::update($meetingId, ['recurrence_group_id' => $meetingId]);
            $occurrences = Meeting::generateOccurrences($data['starts_at'], $data['ends_at'], $recurrenceRule, $recurrenceEndDate, self::MAX_RECURRENCE_OCCURRENCES);
            foreach ($occurrences as [$occStartsAt, $occEndsAt]) {
                $occId = Meeting::create([
                    'company_id' => $companyId,
                    'created_by' => Auth::id(),
                    'title' => $data['title'],
                    'type' => $data['type'],
                    'location' => $data['location'],
                    'description' => $data['description'],
                    'starts_at' => $occStartsAt,
                    'ends_at' => $occEndsAt,
                    'recurrence_rule' => $recurrenceRule,
                    'recurrence_end_date' => $recurrenceEndDate,
                    'recurrence_group_id' => $meetingId,
                ]);
                $this->applyAttendees($occId, $userIds, $external);
                $occurrenceCount++;
            }
            ActivityLog::log('meetings.create_recurring', 'meeting', $meetingId, "توليد {$occurrenceCount} موعد إضافي لسلسلة متكررة");
        }

        flash_set('success', $occurrenceCount > 0
            ? "تم إنشاء الاجتماع مع {$occurrenceCount} موعد متكرر إضافي."
            : 'تم إنشاء الاجتماع.');
        redirect('/meetings/' . $meetingId);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        $myAttendee = MeetingAttendee::findForUser($meeting['id'], Auth::id());

        // معالجة بأثر رجعي لاجتماعات أُنشئت قبل اعتماد حضور المنشئ تلقائياً:
        // منشئ الاجتماع لا يُطالب بتأكيد حضور اجتماعه - يُعتمد فور فتحه الصفحة.
        if ($myAttendee
            && $myAttendee['response'] === 'pending'
            && (int) $meeting['created_by'] === (int) Auth::id()) {
            MeetingAttendee::respond((int) $myAttendee['id'], 'accepted');
            $myAttendee = MeetingAttendee::findForUser($meeting['id'], Auth::id());
        }

        View::render('meetings::show', [
            'pageTitle' => $meeting['title'],
            'meeting' => $meeting,
            'attendees' => MeetingAttendee::forMeeting($meeting['id']),
            'notes' => MeetingNote::forMeeting($meeting['id']),
            'myAttendee' => $myAttendee,
            'canEdit' => $this->canEditMeeting($meeting),
            'canDelete' => $this->canDeleteMeeting($meeting),
            'canManage' => $this->canManage(),
        ]);
    }

    /** محضر الاجتماع بصيغة صفحة قابلة للطباعة (تصدير PDF عبر "طباعة" بالمتصفح) - بلا تخطيط النظام العادي. */
    public function print(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);

        View::render('meetings::print', [
            'pageTitle' => $meeting['title'],
            'meeting' => $meeting,
            'attendees' => MeetingAttendee::forMeeting($meeting['id']),
            'notes' => MeetingNote::forMeeting($meeting['id']),
        ], '');
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            $this->forbidden();
            return;
        }

        View::render('meetings::form', [
            'pageTitle' => 'تعديل اجتماع',
            'isEdit' => true,
            'formAction' => route('/meetings/' . $meeting['id']),
            'meeting' => $meeting,
            'attendees' => MeetingAttendee::forMeeting($meeting['id']),
            'companyUsers' => $this->companyUsers($companyId),
            'conflicts' => [],
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/meetings/' . $meeting['id'] . '/edit');

        $data = $this->validated();
        if ($data === null) {
            redirect('/meetings/' . $meeting['id'] . '/edit');
        }

        [$userIds, $external] = $this->parseAttendeeRequest($companyId);

        if (!Request::input('confirm_conflicts')) {
            $conflicts = Meeting::findConflicts($companyId, array_merge($userIds, [Auth::id()]), Auth::id(), $data['starts_at'], $data['ends_at'], $meeting['id']);
            if ($conflicts) {
                View::render('meetings::form', [
                    'pageTitle' => 'تعديل اجتماع',
                    'isEdit' => true,
                    'formAction' => route('/meetings/' . $meeting['id']),
                    'meeting' => array_merge($meeting, $data),
                    'attendees' => $this->syntheticAttendees($userIds, $external),
                    'companyUsers' => $this->companyUsers($companyId),
                    'conflicts' => $conflicts,
                ]);
                return;
            }
        }

        // إعادة تعيين تذكير الموعد القريب إن تغيّر وقت البداية، حتى يصل تذكير صحيح للموعد الجديد.
        if ($data['starts_at'] !== $meeting['starts_at']) {
            $data['reminder_sent_at'] = null;
        }

        Meeting::update($meeting['id'], $data);
        $this->applyAttendees($meeting['id'], $userIds, $external);

        ActivityLog::log('meetings.update', 'meeting', $meeting['id'], "تعديل اجتماع: {$data['title']}");
        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/meetings/' . $meeting['id']);
    }

    /** يوقف باقي مواعيد السلسلة المتكررة (كل ما هو قادم بعد هذا الموعد) - لا يحذف هذا الموعد نفسه ولا التاريخية منها. */
    public function stopRecurrence(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/meetings/' . $meeting['id']);

        if (empty($meeting['recurrence_group_id'])) {
            flash_set('error', 'هذا الاجتماع ليس جزءاً من سلسلة متكررة.');
            redirect('/meetings/' . $meeting['id']);
        }

        $count = Meeting::stopRecurrence((int) $meeting['recurrence_group_id'], $companyId, $meeting['id']);
        ActivityLog::log('meetings.stop_recurrence', 'meeting', $meeting['id'], "إيقاف تكرار سلسلة اجتماعات (حذف {$count} موعد قادم)");
        flash_set('success', "تم إيقاف التكرار، وحُذف {$count} موعد قادم من السلسلة.");
        redirect('/meetings/' . $meeting['id']);
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canDeleteMeeting($meeting)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/meetings/' . $meeting['id']);

        Meeting::delete($meeting['id']);
        ActivityLog::log('meetings.delete', 'meeting', $meeting['id'], "حذف اجتماع: {$meeting['title']}");
        flash_set('success', 'تم حذف الاجتماع.');
        redirect('/meetings');
    }

    public function respond(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/meetings/' . $meeting['id']);

        $attendee = MeetingAttendee::findForUser($meeting['id'], Auth::id());
        if (!$attendee) {
            flash_set('error', 'لست مدعواً لهذا الاجتماع.');
            redirect('/meetings/' . $meeting['id']);
        }

        $response = Request::input('response');
        if (!in_array($response, ['accepted', 'declined'], true)) {
            flash_set('error', 'استجابة غير صحيحة.');
            redirect('/meetings/' . $meeting['id']);
        }

        MeetingAttendee::respond($attendee['id'], $response);
        ActivityLog::log('meetings.respond', 'meeting', $meeting['id'], $response === 'accepted' ? 'قبول دعوة اجتماع' : 'رفض دعوة اجتماع');

        if ((int) $meeting['created_by'] !== Auth::id()) {
            $label = $response === 'accepted' ? 'قبل' : 'اعتذر عن';
            Notification::send((int) $meeting['created_by'], 'رد على دعوة اجتماع', Auth::user()['name'] . " {$label} حضور: {$meeting['title']}", route('/meetings/' . $meeting['id']));
        }

        flash_set('success', 'تم تسجيل ردك.');
        redirect('/meetings/' . $meeting['id']);
    }

    /** يسمح لمنشئ الاجتماع أو مديره بتأكيد/رفض الحضور نيابةً عن مدعو (خصوصاً الخارجيين الذين لا يملكون حساباً). */
    public function respondFor(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/meetings/' . $meeting['id']);

        $attendee = MeetingAttendee::find((int) $params['attendeeId']);
        if (!$attendee || (int) $attendee['meeting_id'] !== $meeting['id']) {
            flash_set('error', 'المدعو غير موجود.');
            redirect('/meetings/' . $meeting['id']);
        }

        $response = Request::input('response');
        if (!in_array($response, ['accepted', 'declined'], true)) {
            flash_set('error', 'استجابة غير صحيحة.');
            redirect('/meetings/' . $meeting['id']);
        }

        MeetingAttendee::respond($attendee['id'], $response);
        $name = $attendee['external_name'] ?? ('مستخدم #' . $attendee['user_id']);
        ActivityLog::log('meetings.respond_for', 'meeting', $meeting['id'], "تسجيل رد نيابة عن {$name}: " . ($response === 'accepted' ? 'قبول' : 'اعتذار'));

        flash_set('success', 'تم تسجيل الرد نيابة عن المدعو.');
        redirect('/meetings/' . $meeting['id']);
    }

    public function addNote(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/meetings/' . $meeting['id']);

        $phase = Request::input('phase', 'before');
        if (!in_array($phase, ['before', 'after'], true)) {
            $phase = 'before';
        }
        $body = trim((string) Request::input('body', ''));
        if ($body === '') {
            flash_set('error', 'لا يمكن إضافة ملاحظة فارغة.');
            redirect('/meetings/' . $meeting['id']);
        }

        MeetingNote::add($meeting['id'], Auth::id(), $phase, $body);
        flash_set('success', 'تمت إضافة الملاحظة.');
        redirect('/meetings/' . $meeting['id']);
    }

    public function updateOutcomes(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/meetings/' . $meeting['id']);

        Meeting::update($meeting['id'], ['outcomes' => trim((string) Request::input('outcomes', '')) ?: null]);
        ActivityLog::log('meetings.outcomes', 'meeting', $meeting['id'], "تحديث مخرجات اجتماع: {$meeting['title']}");
        flash_set('success', 'تم حفظ مخرجات الاجتماع.');
        redirect('/meetings/' . $meeting['id']);
    }

    public function statusAction(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $meeting = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditMeeting($meeting)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/meetings/' . $meeting['id']);

        $action = Request::input('action');
        $map = ['complete' => 'completed', 'cancel' => 'cancelled', 'reopen' => 'scheduled'];
        if (!isset($map[$action])) {
            flash_set('error', 'إجراء غير معروف.');
            redirect('/meetings/' . $meeting['id']);
        }

        // حارس انتقالات منطقي: لا يُكمَّل أو يُلغى إلا اجتماع مجدول، ولا يُعاد فتح
        // إلا اجتماع مكتمل/ملغى - يمنع "إكمال اجتماع ملغى" أو "إلغاء اجتماع منتهٍ".
        $allowed = [
            'complete' => ['scheduled'],
            'cancel' => ['scheduled'],
            'reopen' => ['completed', 'cancelled'],
        ];
        if (!in_array($meeting['status'], $allowed[$action], true)) {
            flash_set('error', 'لا يمكن تنفيذ هذا الإجراء على اجتماع حالته الحالية.');
            redirect('/meetings/' . $meeting['id']);
        }

        Meeting::update($meeting['id'], ['status' => $map[$action]]);
        ActivityLog::log('meetings.status', 'meeting', $meeting['id'], "تغيير حالة اجتماع: {$meeting['title']}");
        flash_set('success', 'تم تحديث حالة الاجتماع.');
        redirect('/meetings/' . $meeting['id']);
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('meetings::no-company', ['pageTitle' => 'الاجتماعات']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return \App\Core\Permission::check($key) || \App\Core\Permission::check('meetings.manage');
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || \App\Core\Permission::check('meetings.manage');
    }

    private function canEditMeeting(array $meeting): bool
    {
        if ($this->canManage()) {
            return true;
        }
        return $this->can('meetings.edit') && (int) $meeting['created_by'] === Auth::id();
    }

    private function canDeleteMeeting(array $meeting): bool
    {
        if ($this->canManage()) {
            return true;
        }
        return \App\Core\Permission::check('meetings.delete') && (int) $meeting['created_by'] === Auth::id();
    }

    private function findVisible(int $id, int $companyId): array
    {
        $meeting = Meeting::find($id);
        if (!$meeting || (int) $meeting['company_id'] !== $companyId) {
            flash_set('error', 'الاجتماع غير موجود.');
            redirect('/meetings');
        }

        if (!$this->can('meetings.view')) {
            $this->forbidden();
            exit;
        }

        $visible = $this->canManage()
            || (int) $meeting['created_by'] === Auth::id()
            || MeetingAttendee::findForUser($meeting['id'], Auth::id()) !== null;

        if (!$visible) {
            $this->forbidden();
            exit;
        }

        return $meeting;
    }

    private function companyUsers(int $companyId): array
    {
        return Database::select('SELECT id, name FROM users WHERE company_id = :c AND status = "active" ORDER BY name', ['c' => $companyId]);
    }

    private function currentFilters(): array
    {
        $filters = [];
        $status = Request::query('status', '');
        if (in_array($status, self::STATUSES, true)) {
            $filters['status'] = $status;
        }
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        return $filters;
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [], '');
    }

    /** يقرأ ويُصحّح قائمة الحاضرين المطلوبة من الطلب: [مصفوفة user_id داخليين, مصفوفة خارجيين ['name','contact']]. */
    private function parseAttendeeRequest(int $companyId): array
    {
        $requestedUserIds = array_map('intval', (array) Request::input('attendee_user_ids', []));
        $validUserIds = array_column($this->companyUsers($companyId), 'id');
        $requestedUserIds = array_values(array_intersect($requestedUserIds, array_map('intval', $validUserIds)));

        $externalLines = array_filter(array_map('trim', explode("\n", (string) Request::input('external_attendees', ''))));
        $requestedExternal = [];
        foreach ($externalLines as $line) {
            $parts = array_map('trim', explode(',', $line, 2));
            $name = $parts[0] ?? '';
            if ($name === '') {
                continue;
            }
            $requestedExternal[] = ['name' => $name, 'contact' => $parts[1] ?? null];
        }

        return [$requestedUserIds, $requestedExternal];
    }

    /**
     * يوفّق قائمة الحاضرين المطلوبة (داخليين مختارين + خارجيين من النص) مع القائمة
     * الحالية بالقاعدة: يحافظ على رد من هو باقٍ (لا يُصفّر قبوله/رفضه لمجرد حفظ
     * تعديل بسيط على عنوان الاجتماع مثلاً)، يحذف من أُزيل، ويضيف الجديد كـ pending.
     *
     * الداخليون يُطابَقون بـ user_id، والخارجيون بمفتاح (الاسم + التواصل) - وهو
     * مستقر عملياً - للحفاظ على ردّهم المسجّل وتوكن RSVP الخاص بهم بدل حذفهم
     * وإعادة إنشائهم من الصفر (الذي كان يصفّر ردودهم ويُبطل روابطهم مع كل تعديل).
     */
    private function applyAttendees(int $meetingId, array $requestedUserIds, array $requestedExternal): void
    {
        $existing = MeetingAttendee::forMeeting($meetingId);
        $existingUserIds = [];
        $existingExternalKeys = [];
        $extKey = fn (?string $name, ?string $contact): string => mb_strtolower(trim((string) $name)) . '|' . mb_strtolower(trim((string) $contact));

        foreach ($existing as $row) {
            if ($row['user_id']) {
                $existingUserIds[(int) $row['user_id']] = $row['id'];
            } else {
                $existingExternalKeys[$extKey($row['external_name'], $row['external_contact'])] = $row['id'];
            }
        }

        $requestedExternalKeys = [];
        foreach ($requestedExternal as $ext) {
            $requestedExternalKeys[$extKey($ext['name'], $ext['contact'])] = true;
        }

        foreach ($existing as $row) {
            if ($row['user_id']) {
                $stillWanted = in_array((int) $row['user_id'], $requestedUserIds, true);
            } else {
                $stillWanted = isset($requestedExternalKeys[$extKey($row['external_name'], $row['external_contact'])]);
            }
            if (!$stillWanted) {
                Database::delete('meetings_attendees', 'id = :id', ['id' => $row['id']]);
            }
        }

        foreach ($requestedUserIds as $uid) {
            if (!isset($existingUserIds[$uid])) {
                MeetingAttendee::addInternal($meetingId, $uid);
            }
        }

        foreach ($requestedExternal as $ext) {
            if (!isset($existingExternalKeys[$extKey($ext['name'], $ext['contact'])])) {
                MeetingAttendee::addExternal($meetingId, $ext['name'], $ext['contact']);
            }
        }
    }

    /** يبني شكل صفوف حاضرين وهمي (بلا id فعلي) لإعادة عرض النموذج بعد تنبيه تعارض مواعيد، قبل أي إدراج فعلي بالقاعدة. */
    private function syntheticAttendees(array $userIds, array $external): array
    {
        $rows = array_map(fn ($uid) => ['user_id' => $uid, 'external_name' => null, 'external_contact' => null], $userIds);
        foreach ($external as $ext) {
            $rows[] = ['user_id' => null, 'external_name' => $ext['name'], 'external_contact' => $ext['contact']];
        }
        return $rows;
    }

    private function notifyAttendees(int $meetingId, string $title, string $message): void
    {
        foreach (MeetingAttendee::forMeeting($meetingId) as $a) {
            if ($a['user_id'] && (int) $a['user_id'] !== Auth::id()) {
                Notification::send((int) $a['user_id'], $title, $message, route('/meetings/' . $meetingId));
            }
        }
    }

    private function validated(): ?array
    {
        $title = trim((string) Request::input('title', ''));
        $type = Request::input('type', 'in_person');
        $location = trim((string) Request::input('location', ''));
        $startsAt = Request::input('starts_at');
        $endsAt = Request::input('ends_at') ?: null;
        $description = trim((string) Request::input('description', ''));

        if ($title === '') {
            flash_set('error', 'عنوان الاجتماع مطلوب.');
            return null;
        }
        if (!in_array($type, self::TYPES, true)) {
            $type = 'in_person';
        }
        if (!$startsAt) {
            flash_set('error', 'يرجى تحديد موعد بداية الاجتماع.');
            return null;
        }

        return [
            'title' => $title,
            'type' => $type,
            'location' => $location ?: null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'description' => $description ?: null,
        ];
    }
}
