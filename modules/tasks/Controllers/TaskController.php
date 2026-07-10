<?php

namespace Modules\Tasks\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Request;
use App\Core\View;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Models\TaskLog;

class TaskController
{
    private const STATUSES = ['todo', 'in_progress', 'in_review', 'done', 'cancelled'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('tasks.view')) {
            $this->forbidden();
            return;
        }

        $scope = Request::query('scope', 'mine');
        $allowedScopes = ['mine', 'created', 'overdue', 'approval'];
        if ($this->canManage()) {
            $allowedScopes[] = 'all';
        }
        if (!in_array($scope, $allowedScopes, true)) {
            $scope = 'mine';
        }

        $filters = $this->currentFilters();
        $page = max(1, (int) Request::query('page', 1));
        $result = Task::paginate($companyId, $scope, Auth::id(), $page, 15, $filters);

        View::render('tasks::index', [
            'pageTitle' => 'المهام',
            'tasks' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 15,
            'scope' => $scope,
            'filters' => $filters,
            'companyUsers' => $this->companyUsers($companyId),
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'canManage' => $this->canManage(),
        ]);
    }

    public function board(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('tasks.view')) {
            $this->forbidden();
            return;
        }

        $scope = Request::query('scope', 'mine');
        $allowedScopes = ['mine', 'created', 'overdue', 'approval'];
        if ($this->canManage()) {
            $allowedScopes[] = 'all';
        }
        if (!in_array($scope, $allowedScopes, true)) {
            $scope = 'mine';
        }

        $filters = $this->currentFilters();
        $columns = Task::forBoard($companyId, $scope, Auth::id(), $filters);

        View::render('tasks::board', [
            'pageTitle' => 'لوحة المهام',
            'columns' => $columns,
            'scope' => $scope,
            'filters' => $filters,
            'companyUsers' => $this->companyUsers($companyId),
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'canManage' => $this->canManage(),
        ]);
    }

    public function create(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('tasks.create')) {
            $this->forbidden();
            return;
        }

        View::render('tasks::form', [
            'pageTitle' => 'إضافة مهمة',
            'task' => null,
            'companyUsers' => $this->companyUsers($companyId),
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('tasks.create')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/create');

        $data = $this->validated($companyId, null);
        if ($data === null) {
            redirect('/tasks/create');
        }

        $data['company_id'] = $companyId;
        $data['creator_id'] = Auth::id();
        $data['created_at'] = date('Y-m-d H:i:s');

        $taskId = Task::create($data);
        TaskLog::add($taskId, Auth::id(), 'created', 'تم إنشاء المهمة');

        if ((int) $data['assignee_id'] !== Auth::id()) {
            Notification::send((int) $data['assignee_id'], 'تم إسناد مهمة جديدة لك', $data['title'], route('/tasks/' . $taskId));
        }

        ActivityLog::log('tasks.create', 'task', $taskId, "إنشاء مهمة: {$data['title']}");
        flash_set('success', 'تم إنشاء المهمة بنجاح.');
        redirect('/tasks/' . $taskId);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);

        View::render('tasks::show', [
            'pageTitle' => $task['title'],
            'task' => $task,
            'comments' => TaskComment::forTask($task['id']),
            'attachments' => TaskAttachment::forTask($task['id']),
            'logs' => TaskLog::forTask($task['id']),
            'canEdit' => $this->canEditTask($task),
            'canApprove' => $this->canApproveTask($task),
            'statuses' => self::STATUSES,
        ]);
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditTask($task)) {
            $this->forbidden();
            return;
        }

        View::render('tasks::form', [
            'pageTitle' => 'تعديل مهمة',
            'task' => $task,
            'companyUsers' => $this->companyUsers($companyId),
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canEditTask($task)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/' . $task['id'] . '/edit');

        $data = $this->validated($companyId, $task);
        if ($data === null) {
            redirect('/tasks/' . $task['id'] . '/edit');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        $assigneeChanged = (int) $task['assignee_id'] !== (int) $data['assignee_id'];

        Task::update($task['id'], $data);
        TaskLog::add($task['id'], Auth::id(), 'updated', 'تم تعديل بيانات المهمة');

        if ($assigneeChanged && (int) $data['assignee_id'] !== Auth::id()) {
            Notification::send((int) $data['assignee_id'], 'تم إسناد مهمة لك', $data['title'], route('/tasks/' . $task['id']));
        }
        $this->notifyOthers($task, 'تم تعديل مهمة', $data['title']);

        ActivityLog::log('tasks.update', 'task', $task['id'], "تعديل مهمة: {$data['title']}");
        flash_set('success', 'تم حفظ التعديلات.');
        redirect('/tasks/' . $task['id']);
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);

        $canDelete = $this->canManage() || ($this->can('tasks.delete') && (int) $task['creator_id'] === Auth::id());
        if (!$canDelete) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/' . $task['id']);

        foreach (TaskAttachment::forTask($task['id']) as $att) {
            @unlink(BASE_PATH . '/storage/uploads/tasks/' . $att['stored_name']);
        }

        Task::delete($task['id']);
        ActivityLog::log('tasks.delete', 'task', $task['id'], "حذف مهمة: {$task['title']}");
        flash_set('success', 'تم حذف المهمة.');
        redirect('/tasks');
    }

    public function changeStatus(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        $wantsJson = Request::wantsJson();

        $canChange = $this->canEditTask($task)
            || (int) $task['assignee_id'] === Auth::id()
            || (int) $task['creator_id'] === Auth::id();
        if (!$canChange) {
            if ($wantsJson) {
                $this->jsonResponse(403, ['error' => 'لا تملك صلاحية تعديل هذه المهمة.']);
                return;
            }
            $this->forbidden();
            return;
        }
        if (!$wantsJson) {
            $this->verifyCsrf('/tasks/' . $task['id']);
        } elseif (!Csrf::verify(Request::input('_csrf'))) {
            $this->jsonResponse(419, ['error' => 'انتهت صلاحية الجلسة، أعد تحميل الصفحة.']);
            return;
        }

        $status = Request::input('status');
        if (!in_array($status, self::STATUSES, true)) {
            if ($wantsJson) {
                $this->jsonResponse(422, ['error' => 'حالة غير صحيحة.']);
                return;
            }
            flash_set('error', 'حالة غير صحيحة.');
            redirect('/tasks/' . $task['id']);
        }

        Task::update($task['id'], ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        $statusLabels = ['todo' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'in_review' => 'قيد المراجعة', 'done' => 'مكتملة', 'cancelled' => 'ملغاة'];
        TaskLog::add($task['id'], Auth::id(), 'status_changed', 'تم تغيير الحالة إلى: ' . ($statusLabels[$status] ?? $status));
        $this->notifyOthers($task, 'تم تغيير حالة مهمة', $task['title']);

        ActivityLog::log('tasks.status', 'task', $task['id'], "تغيير حالة مهمة: {$task['title']}");

        if ($wantsJson) {
            $this->jsonResponse(200, ['success' => true, 'status' => $status]);
            return;
        }

        flash_set('success', 'تم تحديث حالة المهمة.');
        redirect('/tasks/' . $task['id']);
    }

    public function approve(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);

        if (!$this->canApproveTask($task)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/' . $task['id']);

        Task::update($task['id'], ['approved_at' => date('Y-m-d H:i:s')]);
        TaskLog::add($task['id'], Auth::id(), 'approved', 'تم اعتماد المهمة');
        Notification::send((int) $task['creator_id'], 'تم اعتماد مهمتك', $task['title'], route('/tasks/' . $task['id']));

        ActivityLog::log('tasks.approve', 'task', $task['id'], "اعتماد مهمة: {$task['title']}");
        flash_set('success', 'تم اعتماد المهمة.');
        redirect('/tasks/' . $task['id']);
    }

    public function comment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/tasks/' . $task['id']);

        $body = trim((string) Request::input('body', ''));
        if ($body === '') {
            flash_set('error', 'لا يمكن إضافة ملاحظة فارغة.');
            redirect('/tasks/' . $task['id']);
        }

        TaskComment::add($task['id'], Auth::id(), $body);
        $this->notifyOthers($task, 'ملاحظة جديدة على مهمة', $task['title']);

        flash_set('success', 'تمت إضافة الملاحظة.');
        redirect('/tasks/' . $task['id']);
    }

    public function uploadAttachment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/tasks/' . $task['id']);

        $file = Request::file('file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash_set('error', 'يرجى اختيار ملف صالح.');
            redirect('/tasks/' . $task['id']);
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            flash_set('error', 'الحد الأقصى لحجم الملف هو 10 ميجابايت.');
            redirect('/tasks/' . $task['id']);
        }

        $dir = BASE_PATH . '/storage/uploads/tasks';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $stored = bin2hex(random_bytes(16)) . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
        move_uploaded_file($file['tmp_name'], $dir . '/' . $stored);

        TaskAttachment::add($task['id'], Auth::id(), $file['name'], $stored, (int) $file['size']);
        TaskLog::add($task['id'], Auth::id(), 'attachment_added', 'تم إرفاق ملف: ' . $file['name']);

        flash_set('success', 'تم رفع الملف.');
        redirect('/tasks/' . $task['id']);
    }

    public function downloadAttachment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        $attachment = TaskAttachment::find((int) $params['attachmentId']);

        if (!$attachment || (int) $attachment['task_id'] !== $task['id']) {
            http_response_code(404);
            exit;
        }

        $path = BASE_PATH . '/storage/uploads/tasks/' . $attachment['stored_name'];
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($attachment['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('tasks::no-company', ['pageTitle' => 'المهام']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return \App\Core\Permission::check($key);
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || $this->can('tasks.manage');
    }

    private function canEditTask(array $task): bool
    {
        if ($this->canManage()) {
            return true;
        }
        return $this->can('tasks.edit') && ((int) $task['creator_id'] === Auth::id() || (int) $task['assignee_id'] === Auth::id());
    }

    private function canApproveTask(array $task): bool
    {
        if (!$task['requires_approval'] || $task['approved_at']) {
            return false;
        }
        return $this->canManage() || ($this->can('tasks.approve') && (int) $task['approver_id'] === Auth::id());
    }

    private function findVisible(int $id, int $companyId): array
    {
        $task = Task::find($id);
        if (!$task || (int) $task['company_id'] !== $companyId) {
            flash_set('error', 'المهمة غير موجودة.');
            redirect('/tasks');
        }

        $visible = $this->canManage()
            || (int) $task['assignee_id'] === Auth::id()
            || (int) $task['creator_id'] === Auth::id()
            || (int) $task['approver_id'] === Auth::id();

        if (!$visible) {
            $this->forbidden();
            exit;
        }

        return $task;
    }

    private function companyUsers(int $companyId): array
    {
        return Database::select('SELECT id, name FROM users WHERE company_id = :c AND status = "active" ORDER BY name', ['c' => $companyId]);
    }

    /** فلاتر القائمة/اللوحة المشتركة: بحث بالعنوان، مسؤول، أولوية، حالة. */
    private function currentFilters(): array
    {
        $filters = [];
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        if ($assigneeId = (int) Request::query('assignee_id', 0)) {
            $filters['assignee_id'] = $assigneeId;
        }
        $priority = Request::query('priority', '');
        if (in_array($priority, self::PRIORITIES, true)) {
            $filters['priority'] = $priority;
        }
        $status = Request::query('status', '');
        if (in_array($status, self::STATUSES, true)) {
            $filters['status'] = $status;
        }
        return $filters;
    }

    private function jsonResponse(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }

    private function notifyOthers(array $task, string $title, string $message): void
    {
        $recipients = array_unique(array_filter([(int) $task['assignee_id'], (int) $task['creator_id']]));
        foreach ($recipients as $uid) {
            if ($uid !== Auth::id()) {
                Notification::send($uid, $title, $message, route('/tasks/' . $task['id']));
            }
        }
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

    private function validated(int $companyId, ?array $task): ?array
    {
        $title = trim((string) Request::input('title', ''));
        $description = trim((string) Request::input('description', ''));
        $assigneeId = (int) Request::input('assignee_id', 0);
        $startDate = Request::input('start_date') ?: null;
        $dueDate = Request::input('due_date') ?: null;
        $priority = Request::input('priority', 'medium');
        $status = Request::input('status', 'todo');
        $requiresApproval = Request::input('requires_approval') ? 1 : 0;
        $approverId = (int) Request::input('approver_id', 0) ?: null;

        if ($title === '') {
            flash_set('error', 'عنوان المهمة مطلوب.');
            return null;
        }
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'medium';
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'todo';
        }

        $assignee = Database::first('SELECT id FROM users WHERE id = :id AND company_id = :c', ['id' => $assigneeId, 'c' => $companyId]);
        if (!$assignee) {
            flash_set('error', 'يرجى اختيار مسؤول صالح من نفس الشركة.');
            return null;
        }

        if ($requiresApproval) {
            $approver = $approverId ? Database::first('SELECT id FROM users WHERE id = :id AND company_id = :c', ['id' => $approverId, 'c' => $companyId]) : null;
            if (!$approver) {
                flash_set('error', 'يرجى اختيار معتمد صالح عند طلب الاعتماد.');
                return null;
            }
        } else {
            $approverId = null;
        }

        return [
            'title' => $title,
            'description' => $description ?: null,
            'assignee_id' => $assigneeId,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'priority' => $priority,
            'status' => $status,
            'requires_approval' => $requiresApproval,
            'approver_id' => $approverId,
        ];
    }
}
