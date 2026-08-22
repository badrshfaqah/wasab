<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Notification;
use App\Core\Permission;
use App\Core\Uploads;
use Modules\Mobileapi\Support\Api;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Models\TaskLog;
use Modules\Tasks\Models\TaskSubtask;

/**
 * نقاط JSON لوحدة المهام - تعيد استخدام موديلات الوحدة نفسها، وتطبّق نفس
 * قواعد الرؤية والصلاحيات المطبقة في TaskController الويب حرفياً.
 */
class TasksApiController
{
    private const STATUSES = ['todo', 'in_progress', 'in_review', 'done', 'cancelled'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    private const ATTACHMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const ATTACHMENT_MAX_BYTES = 10485760;

    // ---------- القوائم ----------

    /** GET /api/v1/tasks?scope=&page=&q=&assignee_id=&priority=&status=&sort=&dir= */
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('tasks.view');

        $scope = (string) Api::input('scope', 'mine');
        $allowed = ['mine', 'created', 'overdue', 'approval'];
        if ($this->canManage()) {
            $allowed[] = 'all';
        }
        if (!in_array($scope, $allowed, true)) {
            $scope = 'mine';
        }

        $page = max(1, (int) Api::input('page', 1));
        $sort = (string) Api::input('sort', 'due');
        $dir = strtolower((string) Api::input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $result = Task::paginate($companyId, $scope, Auth::id(), $page, 15, $this->currentFilters(), $sort, $dir);

        Api::ok([
            'tasks' => array_map([$this, 'taskSummary'], $result['rows']),
            'total' => (int) $result['total'],
            'page' => $page,
            'per_page' => 15,
            'can_manage' => $this->canManage(),
            'can_create' => Permission::check('tasks.create'),
        ]);
    }

    /** GET /api/v1/tasks/users - مستخدمو الشركة النشطون (لاختيار المسند إليه). */
    public function companyUsers(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('tasks.view');

        $rows = Database::select(
            'SELECT id, name FROM users WHERE company_id = :c AND status = "active" ORDER BY name',
            ['c' => $companyId]
        );
        Api::ok(['users' => array_map(
            fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name']],
            $rows
        )]);
    }

    /** GET /api/v1/tasks/{id} - التفاصيل الكاملة. */
    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('tasks.view');
        $task = $this->findVisible((int) $params['id'], $companyId);

        Api::ok([
            'task' => $this->taskDetail($task),
            'comments' => array_map(fn ($c) => [
                'id' => (int) $c['id'],
                'user_id' => (int) $c['user_id'],
                'user_name' => $c['user_name'] ?? '',
                'body' => $c['body'],
                'created_at' => $c['created_at'],
            ], TaskComment::forTask((int) $task['id'])),
            'subtasks' => array_map(fn ($s) => [
                'id' => (int) $s['id'],
                'title' => $s['title'],
                'is_done' => (bool) $s['is_done'],
            ], TaskSubtask::forTask((int) $task['id'])),
            'attachments' => array_map(fn ($a) => [
                'id' => (int) $a['id'],
                'original_name' => $a['original_name'],
                'size' => (int) $a['size'],
                'user_name' => $a['user_name'] ?? '',
                'created_at' => $a['created_at'],
            ], TaskAttachment::forTask((int) $task['id'])),
            'logs' => array_map(fn ($l) => [
                'id' => (int) $l['id'],
                'action' => $l['action'],
                'description' => $l['description'],
                'user_name' => $l['user_name'] ?? '',
                'created_at' => $l['created_at'],
            ], TaskLog::forTask((int) $task['id'])),
            'can_edit' => $this->canEditTask($task),
            'can_delete' => $this->canManage() || (Permission::check('tasks.delete') && (int) $task['creator_id'] === Auth::id()),
            'can_change_status' => $this->canChangeStatus($task),
            'can_manage_subtasks' => $this->canManageSubtasks($task),
            'can_approve' => $this->canApproveTask($task),
        ]);
    }

    // ---------- الإنشاء والتعديل ----------

    /** POST /api/v1/tasks */
    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('tasks.create');

        $data = $this->validated($companyId);
        $data['company_id'] = $companyId;
        $data['creator_id'] = Auth::id();
        $data['created_at'] = date('Y-m-d H:i:s');

        $taskId = Task::create($data);
        TaskLog::add($taskId, Auth::id(), 'created', 'إنشاء المهمة من تطبيق الجوال');

        if ((int) $data['assignee_id'] !== Auth::id()) {
            Notification::send((int) $data['assignee_id'], 'تم إسناد مهمة جديدة لك', $data['title'], route('/tasks/' . $taskId));
        }
        ActivityLog::log('tasks.create', 'task', (string) $taskId, 'إنشاء مهمة: ' . $data['title']);

        Api::ok(['id' => $taskId], 201);
    }

    /** POST /api/v1/tasks/{id} - تعديل. */
    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditTask($task)) {
            Api::error('لا تملك صلاحية تعديل هذه المهمة.', 403, 'forbidden');
        }

        $data = $this->validated($companyId, $task);
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (($data['due_date'] ?? null) !== ($task['due_date'] ?? null)) {
            $data['escalated_at'] = null;
        }

        Task::update((int) $task['id'], $data);
        TaskLog::add((int) $task['id'], Auth::id(), 'updated', 'تعديل المهمة من تطبيق الجوال');

        if ((int) $data['assignee_id'] !== (int) $task['assignee_id'] && (int) $data['assignee_id'] !== Auth::id()) {
            Notification::send((int) $data['assignee_id'], 'تم إسناد مهمة لك', $data['title'], route('/tasks/' . $task['id']));
        }
        $this->notifyOthers($task, 'تم تعديل مهمة', $data['title']);
        ActivityLog::log('tasks.update', 'task', (string) $task['id'], 'تعديل مهمة: ' . $data['title']);

        Api::ok(['id' => (int) $task['id']]);
    }

    /** POST /api/v1/tasks/{id}/delete */
    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);

        $allowed = $this->canManage()
            || (Permission::check('tasks.delete') && (int) $task['creator_id'] === Auth::id());
        if (!$allowed) {
            Api::error('لا تملك صلاحية حذف هذه المهمة.', 403, 'forbidden');
        }

        foreach (TaskAttachment::forTask((int) $task['id']) as $attachment) {
            @unlink(BASE_PATH . '/storage/uploads/tasks/' . $companyId . '/' . $attachment['stored_name']);
        }
        Task::delete((int) $task['id']);
        ActivityLog::log('tasks.delete', 'task', (string) $task['id'], 'حذف مهمة: ' . $task['title']);

        Api::ok(['message' => 'تم حذف المهمة.']);
    }

    /** POST /api/v1/tasks/{id}/status  {status} */
    public function changeStatus(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canChangeStatus($task)) {
            Api::error('لا تملك صلاحية تغيير حالة هذه المهمة.', 403, 'forbidden');
        }

        $status = (string) Api::input('status', '');
        if (!in_array($status, self::STATUSES, true)) {
            Api::error('حالة غير صحيحة.', 422, 'validation');
        }

        $update = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($status === 'done' && empty($task['completed_at'])) {
            $update['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($status !== 'done') {
            $update['completed_at'] = null;
        }
        Task::update((int) $task['id'], $update);

        TaskLog::add((int) $task['id'], Auth::id(), 'status_changed', 'تغيير الحالة إلى: ' . status_label($status));
        $this->notifyOthers($task, 'تم تغيير حالة مهمة', $task['title']);
        ActivityLog::log('tasks.status', 'task', (string) $task['id'], 'تغيير حالة مهمة: ' . $task['title']);

        Api::ok(['status' => $status]);
    }

    /** POST /api/v1/tasks/{id}/approve */
    public function approve(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canApproveTask($task)) {
            Api::error('لا يمكنك اعتماد هذه المهمة.', 403, 'forbidden');
        }

        Task::update((int) $task['id'], ['approved_at' => date('Y-m-d H:i:s')]);
        TaskLog::add((int) $task['id'], Auth::id(), 'approved', 'اعتماد المهمة');
        Notification::send((int) $task['creator_id'], 'تم اعتماد مهمتك', $task['title'], route('/tasks/' . $task['id']));
        ActivityLog::log('tasks.approve', 'task', (string) $task['id'], 'اعتماد مهمة: ' . $task['title']);

        Api::ok(['message' => 'تم الاعتماد.']);
    }

    // ---------- الملاحظات والقوائم الفرعية ----------

    /** POST /api/v1/tasks/{id}/comments  {body} */
    public function comment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);

        $body = trim((string) Api::input('body', ''));
        if ($body === '') {
            Api::error('اكتب نص الملاحظة.', 422, 'validation');
        }

        TaskComment::add((int) $task['id'], Auth::id(), $body);
        $this->notifyOthers($task, 'ملاحظة جديدة على مهمة', $task['title']);

        Api::ok(['message' => 'تمت إضافة الملاحظة.'], 201);
    }

    /** POST /api/v1/tasks/{id}/subtasks  {title} */
    public function addSubtask(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManageSubtasks($task)) {
            Api::error('لا تملك صلاحية إدارة القائمة الفرعية.', 403, 'forbidden');
        }

        $title = trim((string) Api::input('title', ''));
        if ($title === '') {
            Api::error('اكتب عنوان العنصر.', 422, 'validation');
        }

        TaskSubtask::add((int) $task['id'], mb_substr($title, 0, 255));
        TaskLog::add((int) $task['id'], Auth::id(), 'subtask_added', 'إضافة عنصر: ' . mb_substr($title, 0, 100));

        Api::ok(['message' => 'تمت الإضافة.'], 201);
    }

    /** POST /api/v1/tasks/{id}/subtasks/{subtaskId}/toggle */
    public function toggleSubtask(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManageSubtasks($task)) {
            Api::error('لا تملك صلاحية إدارة القائمة الفرعية.', 403, 'forbidden');
        }

        $subtask = TaskSubtask::find((int) $params['subtaskId']);
        if (!$subtask || (int) $subtask['task_id'] !== (int) $task['id']) {
            Api::error('العنصر غير موجود.', 404, 'not_found');
        }

        $newState = !((bool) $subtask['is_done']);
        TaskSubtask::toggle((int) $subtask['id'], $newState);

        $progress = TaskSubtask::progress((int) $task['id']);
        Api::ok([
            'done' => $newState,
            'progress' => ['total' => (int) ($progress['total'] ?? 0), 'done' => (int) ($progress['done'] ?? 0)],
        ]);
    }

    /** POST /api/v1/tasks/{id}/subtasks/{subtaskId}/delete */
    public function deleteSubtask(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManageSubtasks($task)) {
            Api::error('لا تملك صلاحية إدارة القائمة الفرعية.', 403, 'forbidden');
        }

        $subtask = TaskSubtask::find((int) $params['subtaskId']);
        if ($subtask && (int) $subtask['task_id'] === (int) $task['id']) {
            TaskSubtask::delete((int) $subtask['id']);
            TaskLog::add((int) $task['id'], Auth::id(), 'subtask_removed', 'حذف عنصر من القائمة الفرعية');
        }

        Api::ok(['message' => 'تم الحذف.']);
    }

    // ---------- المرفقات ----------

    /** POST /api/v1/tasks/{id}/attachments - multipart، الحقل: file */
    public function uploadAttachment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManageSubtasks($task)) {
            Api::error('لا تملك صلاحية إضافة مرفقات.', 403, 'forbidden');
        }

        $result = Uploads::handleFile(
            'file',
            BASE_PATH . '/storage/uploads/tasks/' . $companyId,
            self::ATTACHMENT_EXTENSIONS,
            self::ATTACHMENT_MAX_BYTES
        );
        if (!empty($result['error'])) {
            Api::error($result['error'], 422, 'validation');
        }

        TaskAttachment::add((int) $task['id'], Auth::id(), $result['original'], $result['filename'], (int) $result['size']);
        TaskLog::add((int) $task['id'], Auth::id(), 'attachment_added', 'إضافة مرفق: ' . mb_substr($result['original'], 0, 100));

        Api::ok(['message' => 'تم رفع المرفق.'], 201);
    }

    /** GET /api/v1/tasks/{id}/attachments/{attachmentId} - تنزيل بتوثيق Bearer. */
    public function downloadAttachment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);

        $attachment = TaskAttachment::find((int) $params['attachmentId']);
        if (!$attachment || (int) $attachment['task_id'] !== (int) $task['id']) {
            Api::error('المرفق غير موجود.', 404, 'not_found');
        }

        $path = BASE_PATH . '/storage/uploads/tasks/' . $companyId . '/' . $attachment['stored_name'];
        if (!is_file($path)) {
            Api::error('ملف المرفق غير موجود على الخادم.', 404, 'not_found');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($attachment['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ---------- تسلسل البيانات ----------

    private function taskSummary(array $t): array
    {
        return [
            'id' => (int) $t['id'],
            'title' => $t['title'],
            'status' => $t['status'],
            'priority' => $t['priority'],
            'start_date' => $t['start_date'] ?? null,
            'due_date' => $t['due_date'] ?? null,
            'assignee_id' => (int) $t['assignee_id'],
            'assignee_name' => $t['assignee_name'] ?? null,
            'creator_id' => (int) $t['creator_id'],
            'creator_name' => $t['creator_name'] ?? null,
            'requires_approval' => (bool) ($t['requires_approval'] ?? 0),
            'approved_at' => $t['approved_at'] ?? null,
        ];
    }

    private function taskDetail(array $t): array
    {
        return $this->taskSummary($t) + [
            'description' => $t['description'] ?? null,
            'approver_id' => isset($t['approver_id']) ? (int) $t['approver_id'] : null,
            'approver_name' => $t['approver_name'] ?? null,
            'completed_at' => $t['completed_at'] ?? null,
            'linked_type' => $t['linked_type'] ?? null,
            'linked_label' => $t['linked_label'] ?? null,
            'created_at' => $t['created_at'] ?? null,
            'updated_at' => $t['updated_at'] ?? null,
        ];
    }

    // ---------- نفس قواعد TaskController الويب ----------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            Api::error('حسابك غير مرتبط بشركة.', 422, 'no_company');
        }
        return $companyId;
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('tasks.manage');
    }

    private function canEditTask(array $task): bool
    {
        if ($this->canManage()) {
            return true;
        }
        return Permission::check('tasks.edit')
            && ((int) $task['creator_id'] === Auth::id() || (int) $task['assignee_id'] === Auth::id());
    }

    private function canChangeStatus(array $task): bool
    {
        return $this->canEditTask($task)
            || (int) $task['assignee_id'] === Auth::id()
            || (int) $task['creator_id'] === Auth::id();
    }

    private function canManageSubtasks(array $task): bool
    {
        return $this->canEditTask($task)
            || (int) $task['assignee_id'] === Auth::id()
            || (int) $task['creator_id'] === Auth::id();
    }

    private function canApproveTask(array $task): bool
    {
        if (empty($task['requires_approval']) || !empty($task['approved_at']) || $task['status'] === 'cancelled') {
            return false;
        }
        return $this->canManage()
            || (Permission::check('tasks.approve') && (int) ($task['approver_id'] ?? 0) === Auth::id());
    }

    private function findVisible(int $id, int $companyId): array
    {
        $task = Task::find($id);
        if (!$task || (int) $task['company_id'] !== $companyId) {
            Api::error('المهمة غير موجودة.', 404, 'not_found');
        }

        $visible = $this->canManage()
            || in_array(Auth::id(), [
                (int) $task['assignee_id'],
                (int) $task['creator_id'],
                (int) ($task['approver_id'] ?? 0),
            ], true);
        if (!$visible) {
            Api::error('لا تملك صلاحية عرض هذه المهمة.', 403, 'forbidden');
        }

        return $task;
    }

    private function currentFilters(): array
    {
        $filters = [];
        $q = trim((string) Api::input('q', ''));
        if ($q !== '') {
            $filters['q'] = $q;
        }
        $assignee = (int) Api::input('assignee_id', 0);
        if ($assignee > 0) {
            $filters['assignee_id'] = $assignee;
        }
        $priority = (string) Api::input('priority', '');
        if (in_array($priority, self::PRIORITIES, true)) {
            $filters['priority'] = $priority;
        }
        $status = (string) Api::input('status', '');
        if (in_array($status, self::STATUSES, true)) {
            $filters['status'] = $status;
        }
        return $filters;
    }

    /** نفس عقد validated() في متحكم الويب. */
    private function validated(int $companyId, ?array $task = null): array
    {
        $title = trim((string) Api::input('title', ''));
        if ($title === '') {
            Api::error('عنوان المهمة مطلوب.', 422, 'validation');
        }

        $assigneeId = (int) Api::input('assignee_id', 0);
        $assignee = Database::first(
            'SELECT id FROM users WHERE id = :id AND company_id = :c LIMIT 1',
            ['id' => $assigneeId, 'c' => $companyId]
        );
        if (!$assignee) {
            Api::error('اختر مسنداً إليه من مستخدمي شركتك.', 422, 'validation');
        }

        $priority = (string) Api::input('priority', 'medium');
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'medium';
        }
        $status = (string) Api::input('status', 'todo');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'todo';
        }

        $requiresApproval = Api::input('requires_approval') ? 1 : 0;
        $approverId = null;
        if ($requiresApproval) {
            $approverId = (int) Api::input('approver_id', 0);
            $approver = Database::first(
                'SELECT id FROM users WHERE id = :id AND company_id = :c LIMIT 1',
                ['id' => $approverId, 'c' => $companyId]
            );
            if (!$approver) {
                Api::error('اختر معتمداً من مستخدمي شركتك.', 422, 'validation');
            }
        }

        $description = trim((string) Api::input('description', ''));
        $startDate = trim((string) Api::input('start_date', ''));
        $dueDate = trim((string) Api::input('due_date', ''));

        return [
            'title' => mb_substr($title, 0, 200),
            'description' => $description !== '' ? $description : null,
            'assignee_id' => $assigneeId,
            'start_date' => $startDate !== '' ? $startDate : null,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'priority' => $priority,
            'status' => $status,
            'requires_approval' => $requiresApproval,
            'approver_id' => $approverId,
            // ربط المهمة بسجلات وحدات أخرى غير مدعوم من الجوال بعد - نحافظ على القيم الحالية عند التعديل.
            'linked_type' => $task['linked_type'] ?? null,
            'linked_id' => $task['linked_id'] ?? null,
            'linked_label' => $task['linked_label'] ?? null,
        ];
    }

    private function notifyOthers(array $task, string $title, string $message): void
    {
        $targets = array_unique([(int) $task['assignee_id'], (int) $task['creator_id']]);
        foreach ($targets as $userId) {
            if ($userId && $userId !== Auth::id()) {
                Notification::send($userId, $title, $message, route('/tasks/' . $task['id']));
            }
        }
    }
}
