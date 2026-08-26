<?php

namespace Modules\Tasks\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Tasks\Models\ChecklistTemplate;
use Modules\Tasks\Models\RecurringTask;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAttachment;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Models\TaskLog;
use Modules\Tasks\Models\TaskSubtask;

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
        $sort = Request::query('sort', 'due');
        if (!array_key_exists($sort, Task::sortColumns())) {
            $sort = 'due';
        }
        $dir = strtolower((string) Request::query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $page = max(1, (int) Request::query('page', 1));
        $result = Task::paginate($companyId, $scope, Auth::id(), $page, 15, $filters, $sort, $dir);

        View::render('tasks::index', [
            'pageTitle' => 'المهام',
            'tasks' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 15,
            'scope' => $scope,
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
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

    // ---------------------------------------------------------------
    // المهام المتكررة (إدارة القوالب - للمدراء)

    public function recurring(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        View::render('tasks::recurring', [
            'pageTitle' => 'المهام المتكررة',
            'items' => RecurringTask::forCompany($companyId),
            'companyUsers' => $this->companyUsers($companyId),
            'priorities' => self::PRIORITIES,
            'frequencyLabels' => RecurringTask::frequencyLabels(),
        ]);
    }

    public function recurringStore(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/recurring');

        $title = trim((string) Request::input('title', ''));
        $assigneeId = (int) Request::input('assignee_id', 0);
        $validUserIds = array_map('intval', array_column($this->companyUsers($companyId), 'id'));
        if ($title === '' || !in_array($assigneeId, $validUserIds, true)) {
            flash_set('error', 'العنوان والمسؤول مطلوبان.');
            redirect('/tasks/recurring');
        }
        $priority = Request::input('priority', 'medium');
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'medium';
        }
        $frequency = Request::input('frequency', 'weekly');
        if (!in_array($frequency, RecurringTask::FREQUENCIES, true)) {
            $frequency = 'weekly';
        }
        $startDate = Request::input('next_run');
        if (!$startDate || !strtotime($startDate)) {
            $startDate = date('Y-m-d');
        }
        $offset = max(0, min(365, (int) Request::input('due_offset_days', 0)));

        RecurringTask::create([
            'company_id' => $companyId,
            'title' => mb_substr($title, 0, 200),
            'description' => trim((string) Request::input('description', '')) ?: null,
            'assignee_id' => $assigneeId,
            'priority' => $priority,
            'frequency' => $frequency,
            'due_offset_days' => $offset,
            'next_run' => date('Y-m-d', strtotime($startDate)),
            'is_active' => 1,
            'created_by' => Auth::id(),
        ]);
        ActivityLog::log('tasks.recurring_add', 'task_recurring', 0, "إضافة مهمة متكررة: {$title}");
        flash_set('success', 'تمت إضافة المهمة المتكررة.');
        redirect('/tasks/recurring');
    }

    public function recurringToggle(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/recurring');

        $item = RecurringTask::find((int) $params['id']);
        if ($item && (int) $item['company_id'] === $companyId) {
            RecurringTask::update($item['id'], ['is_active' => $item['is_active'] ? 0 : 1]);
            flash_set('success', $item['is_active'] ? 'تم إيقاف التكرار.' : 'تم تفعيل التكرار.');
        }
        redirect('/tasks/recurring');
    }

    public function recurringDelete(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/recurring');

        $item = RecurringTask::find((int) $params['id']);
        if ($item && (int) $item['company_id'] === $companyId) {
            RecurringTask::delete($item['id']);
            flash_set('success', 'تم حذف المهمة المتكررة.');
        }
        redirect('/tasks/recurring');
    }

    // ---------------------------------------------------------------
    // قوالب قوائم التحقق (Checklists)

    public function checklists(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        View::render('tasks::checklists', [
            'pageTitle' => 'قوائم التحقق',
            'items' => ChecklistTemplate::forCompany($companyId),
        ]);
    }

    public function checklistStore(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/checklists');

        $name = trim((string) Request::input('name', ''));
        $items = trim((string) Request::input('items', ''));
        if ($name === '' || $items === '') {
            flash_set('error', 'الاسم والعناصر مطلوبان.');
            redirect('/tasks/checklists');
        }
        ChecklistTemplate::create([
            'company_id' => $companyId,
            'name' => mb_substr($name, 0, 160),
            'items' => $items,
            'created_by' => Auth::id(),
        ]);
        flash_set('success', 'تمت إضافة قائمة التحقق.');
        redirect('/tasks/checklists');
    }

    public function checklistDelete(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/checklists');

        $tpl = ChecklistTemplate::find((int) $params['id']);
        if ($tpl && (int) $tpl['company_id'] === $companyId) {
            ChecklistTemplate::delete($tpl['id']);
            flash_set('success', 'تم حذف قائمة التحقق.');
        }
        redirect('/tasks/checklists');
    }

    /** يطبّق قالب قائمة تحقق على مهمة: يُنشئ عناصره كمهام فرعية. */
    public function applyChecklist(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManageSubtasks($task)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/' . $task['id']);

        $tpl = ChecklistTemplate::find((int) Request::input('checklist_id', 0));
        if (!$tpl || (int) $tpl['company_id'] !== $companyId) {
            flash_set('error', 'قائمة تحقق غير صحيحة.');
            redirect('/tasks/' . $task['id']);
        }
        $count = 0;
        foreach (ChecklistTemplate::items($tpl) as $item) {
            TaskSubtask::add($task['id'], $item);
            $count++;
        }
        TaskLog::add($task['id'], Auth::id(), 'updated', "تطبيق قائمة تحقق: {$tpl['name']} ({$count} عنصر)");
        flash_set('success', "تمت إضافة {$count} عنصراً من قائمة \"{$tpl['name']}\".");
        redirect('/tasks/' . $task['id']);
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
            'linkables' => $this->linkableEntities($companyId),
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
            'subtasks' => TaskSubtask::forTask($task['id']),
            'canEdit' => $this->canEditTask($task),
            'canManageSubtasks' => $this->canManageSubtasks($task),
            'canApprove' => $this->canApproveTask($task),
            'statuses' => self::STATUSES,
            'companyUsers' => $this->companyUsers($companyId),
            'checklists' => $this->canManageSubtasks($task) ? ChecklistTemplate::forCompany($companyId) : [],
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
            'linkables' => $this->linkableEntities($companyId),
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

        // تغيّر موعد الاستحقاق يعيد تفعيل التصعيد حتى يُنبَّه على الموعد الجديد إن تأخّر
        if (($data['due_date'] ?? null) !== ($task['due_date'] ?? null)) {
            $data['escalated_at'] = null;
        }

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
            @unlink(BASE_PATH . '/storage/uploads/tasks/' . $companyId . '/' . $att['stored_name']);
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

        $now = date('Y-m-d H:i:s');
        $update = ['status' => $status, 'updated_at' => $now];
        // ختم وقت الإتمام عند الانتقال إلى "مكتملة"، وإلغاؤه عند إعادة الفتح - لقياس الالتزام بدقّة
        if ($status === 'done' && empty($task['completed_at'])) {
            $update['completed_at'] = $now;
        } elseif ($status !== 'done' && !empty($task['completed_at'])) {
            $update['completed_at'] = null;
        }
        Task::update($task['id'], $update);
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

        // تنبيه من ذُكِر بالتعليق عبر @الاسم (عدا من نبّهناه أصلاً كمسند/منشئ، وعدا نفسه)
        $alreadyNotified = [(int) $task['assignee_id'], (int) $task['creator_id'], Auth::id()];
        foreach ($this->extractMentions($body, $this->companyUsers($companyId)) as $uid) {
            if (!in_array($uid, $alreadyNotified, true)) {
                Notification::send(
                    $uid,
                    'ذُكِرت في تعليق على مهمة',
                    (Auth::user()['name'] ?? '') . ' ذكرك في: ' . $task['title'],
                    route('/tasks/' . $task['id'])
                );
            }
        }

        flash_set('success', 'تمت إضافة الملاحظة.');
        redirect('/tasks/' . $task['id']);
    }

    /**
     * يستخرج معرّفات المستخدمين المذكورين بصيغة @الاسم داخل نص. يطابق الاسم الكامل
     * الحرفي لمستخدمي الشركة (الأطول أولاً)، ويتحقق من حدّ بعد الاسم لتفادي مطابقة
     * اسم هو جزء من اسم أطول. أسماء المستخدمين تأتي من القاعدة لا من إدخال المستخدم.
     */
    private function extractMentions(string $body, array $companyUsers): array
    {
        // ترتيب تنازلي بطول الاسم حتى يُطابَق "أحمد علي" قبل "أحمد"
        usort($companyUsers, fn ($a, $b) => mb_strlen($b['name']) <=> mb_strlen($a['name']));
        $found = [];
        $claimed = []; // مواضع "@" استهلكها اسمٌ أطول، فلا يطابقها اسم أقصر هو بادئته
        foreach ($companyUsers as $u) {
            $needle = '@' . $u['name'];
            $offset = 0;
            while (($pos = mb_strpos($body, $needle, $offset)) !== false) {
                $after = mb_substr($body, $pos + mb_strlen($needle), 1);
                // حدّ صالح: نهاية النص أو محرف ليس حرفاً/رقماً عربياً أو لاتينياً
                if (!isset($claimed[$pos]) && ($after === '' || !preg_match('/[\p{L}\p{N}_]/u', $after))) {
                    $found[(int) $u['id']] = true;
                    $claimed[$pos] = true;
                }
                $offset = $pos + mb_strlen($needle);
            }
        }
        return array_keys($found);
    }

    public function addSubtask(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManageSubtasks($task)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/' . $task['id']);

        $title = trim((string) Request::input('title', ''));
        if ($title === '') {
            flash_set('error', 'يرجى إدخال نص العنصر.');
            redirect('/tasks/' . $task['id']);
        }

        TaskSubtask::add($task['id'], $title);
        TaskLog::add($task['id'], Auth::id(), 'subtask_added', 'تمت إضافة عنصر للقائمة الفرعية: ' . $title);

        flash_set('success', 'تمت إضافة العنصر.');
        redirect('/tasks/' . $task['id']);
    }

    public function toggleSubtask(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        $wantsJson = Request::wantsJson();

        if (!$this->canManageSubtasks($task)) {
            if ($wantsJson) {
                $this->jsonResponse(403, ['error' => 'لا تملك صلاحية تعديل هذه المهمة.']);
                return;
            }
            $this->forbidden();
            return;
        }

        if ($wantsJson) {
            if (!Csrf::verify(Request::input('_csrf'))) {
                $this->jsonResponse(419, ['error' => 'انتهت صلاحية الجلسة، أعد تحميل الصفحة.']);
                return;
            }
        } else {
            $this->verifyCsrf('/tasks/' . $task['id']);
        }

        $subtask = TaskSubtask::find((int) $params['subtaskId']);
        if (!$subtask || (int) $subtask['task_id'] !== $task['id']) {
            if ($wantsJson) {
                $this->jsonResponse(404, ['error' => 'العنصر غير موجود.']);
                return;
            }
            flash_set('error', 'العنصر غير موجود.');
            redirect('/tasks/' . $task['id']);
        }

        $done = !$subtask['is_done'];
        TaskSubtask::toggle($subtask['id'], $done);

        if ($wantsJson) {
            $progress = TaskSubtask::progress($task['id']);
            $this->jsonResponse(200, ['success' => true, 'done' => $done, 'progress' => $progress]);
            return;
        }

        redirect('/tasks/' . $task['id']);
    }

    public function deleteSubtask(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManageSubtasks($task)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/' . $task['id']);

        $subtask = TaskSubtask::find((int) $params['subtaskId']);
        if ($subtask && (int) $subtask['task_id'] === $task['id']) {
            TaskSubtask::delete($subtask['id']);
            TaskLog::add($task['id'], Auth::id(), 'subtask_removed', 'تم حذف عنصر من القائمة الفرعية: ' . $subtask['title']);
        }

        flash_set('success', 'تم حذف العنصر.');
        redirect('/tasks/' . $task['id']);
    }

    private const ATTACHMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const ATTACHMENT_MAX_BYTES = 10 * 1024 * 1024;

    public function uploadAttachment(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        $task = $this->findVisible((int) $params['id'], $companyId);
        if (!$this->canManageSubtasks($task)) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/tasks/' . $task['id']);

        $upload = Uploads::handleFile('file', BASE_PATH . '/storage/uploads/tasks/' . $companyId, self::ATTACHMENT_EXTENSIONS, self::ATTACHMENT_MAX_BYTES);
        if ($upload['error']) {
            flash_set('error', $upload['error']);
            redirect('/tasks/' . $task['id']);
        }
        if (!$upload['filename']) {
            flash_set('error', 'يرجى اختيار ملف صالح.');
            redirect('/tasks/' . $task['id']);
        }

        TaskAttachment::add($task['id'], Auth::id(), $upload['original'], $upload['filename'], $upload['size']);
        TaskLog::add($task['id'], Auth::id(), 'attachment_added', 'تم إرفاق ملف: ' . $upload['original']);

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

        $path = BASE_PATH . '/storage/uploads/tasks/' . $companyId . '/' . $attachment['stored_name'];
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

    /** إدارة القائمة الفرعية (إضافة/تأشير/حذف) بنفس صلاحية تغيير الحالة: تعديل كامل أو المسؤول/المنشئ. */
    private function canManageSubtasks(array $task): bool
    {
        return $this->canEditTask($task)
            || (int) $task['assignee_id'] === Auth::id()
            || (int) $task['creator_id'] === Auth::id();
    }

    private function canApproveTask(array $task): bool
    {
        // لا يُعتمد إلا ما يتطلب اعتماداً ولم يُعتمد بعد وليس ملغى (اعتماد مهمة ملغاة غير منطقي)
        if (!$task['requires_approval'] || $task['approved_at'] || $task['status'] === 'cancelled') {
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
    /** تصدير قائمة المهام (بنفس نطاق وفلاتر الصفحة) إلى CSV أو طباعة/PDF. */
    public function export(array $params): void
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

        $tasks = Task::paginate($companyId, $scope, Auth::id(), 1, 100000, $filters)['rows'];

        $priorityLabels = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة'];
        $statusLabels = ['todo' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'in_review' => 'قيد المراجعة', 'done' => 'مكتملة', 'cancelled' => 'ملغاة'];

        $headers = ['العنوان', 'المسؤول', 'المنشئ', 'الأولوية', 'الحالة', 'تاريخ الاستحقاق'];
        $rows = array_map(fn ($t) => [
            $t['title'],
            $t['assignee_name'] ?? '-',
            $t['creator_name'] ?? '-',
            $priorityLabels[$t['priority']] ?? $t['priority'],
            $statusLabels[$t['status']] ?? $t['status'],
            $t['due_date'] ? format_date($t['due_date']) : '-',
        ], $tasks);

        if (($params['format'] ?? 'csv') === 'print') {
            \App\Core\Export::printable('المهام', $headers, $rows);
        }
        \App\Core\Export::csv('tasks', $headers, $rows);
    }

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

        // ارتباط اختياري بكيان (مستند/أصل/موظف) بصيغة "type:id"
        [$linkType, $linkId, $linkLabel] = $this->parseLink($companyId, (string) Request::input('linked', ''));

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
            'linked_type' => $linkType,
            'linked_id' => $linkId,
            'linked_label' => $linkLabel,
        ];
    }

    /** خريطة نوع الكيان: [الجدول, عمود الاسم, مسار العرض, هل الإضافة مفعّلة]. */
    private function linkableTypes(): array
    {
        return [
            'document' => ['documents_documents', 'title', '/documents/', 'documents'],
            'asset' => ['assets_assets', 'name', '/custody/', 'assets'],
            'employee' => ['employees_profiles', 'full_name', '/employees/', 'employees'],
            'contact_org' => ['contacts_organizations', 'name', '/contacts/orgs/', 'contacts'],
            'contact_person' => ['contacts_persons', 'full_name', '/contacts/people/', 'contacts'],
        ];
    }

    /** يحلّل قيمة "type:id" ويتحقق أن الكيان يخص الشركة والإضافة مفعّلة. يُرجع [type,id,label] أو [null,null,null]. */
    private function parseLink(int $companyId, string $raw): array
    {
        if ($raw === '' || !str_contains($raw, ':')) {
            return [null, null, null];
        }
        [$type, $id] = explode(':', $raw, 2);
        $id = (int) $id;
        $types = $this->linkableTypes();
        if (!isset($types[$type]) || $id < 1) {
            return [null, null, null];
        }
        [$table, $nameCol, , $moduleKey] = $types[$type];
        if (!\App\Core\ModuleManager::isActive($moduleKey)) {
            return [null, null, null];
        }
        $row = Database::first("SELECT `{$nameCol}` AS label FROM `{$table}` WHERE id = :id AND company_id = :c", ['id' => $id, 'c' => $companyId]);
        if (!$row) {
            return [null, null, null];
        }
        return [$type, $id, mb_substr((string) $row['label'], 0, 200)];
    }

    /** قوائم الكيانات القابلة للربط، مجمّعة حسب النوع (للنموذج). */
    private function linkableEntities(int $companyId): array
    {
        $out = [];
        foreach ($this->linkableTypes() as $type => [$table, $nameCol, , $moduleKey]) {
            if (!\App\Core\ModuleManager::isActive($moduleKey)) {
                continue;
            }
            $out[$type] = Database::select(
                "SELECT id, `{$nameCol}` AS label FROM `{$table}` WHERE company_id = :c ORDER BY `{$nameCol}` LIMIT 500",
                ['c' => $companyId]
            );
        }
        return $out;
    }

    /** مسار عرض الكيان المرتبط (للعرض في صفحة المهمة). */
    public static function linkUrl(?string $type, $id): ?string
    {
        if (!$type || !$id) {
            return null;
        }
        $paths = ['document' => '/documents/', 'asset' => '/custody/', 'employee' => '/employees/', 'meeting' => '/meetings/', 'crm_org' => '/contacts/orgs/', 'contact_org' => '/contacts/orgs/', 'contact_person' => '/contacts/people/'];
        return isset($paths[$type]) ? route($paths[$type] . (int) $id) : null;
    }
}
