<?php

namespace Modules\Clients\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;

/** سجل العملاء: CRUD بسيط مع صفحة عميل تعرض ما يرتبط به من المهام والأرشيف. */
class ClientController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('clients.view')) {
            $this->forbidden();
            return;
        }
        $q = trim((string) Request::query('q', ''));
        $where = "company_id = :c AND status = 'active'";
        $params = ['c' => $companyId];
        if ($q !== '') {
            $where .= ' AND (name LIKE :q OR phone LIKE :q2 OR contact_name LIKE :q3)';
            $params += ['q' => "%{$q}%", 'q2' => "%{$q}%", 'q3' => "%{$q}%"];
        }
        View::render('clients::index', [
            'pageTitle' => 'العملاء',
            'clients' => Database::select("SELECT * FROM clients_clients WHERE {$where} ORDER BY name LIMIT 500", $params),
            'q' => $q,
            'canCreate' => $this->can('clients.create'),
        ]);
    }

    public function create(): void
    {
        $this->requireCompanyContext();
        if (!$this->can('clients.create')) {
            $this->forbidden();
            return;
        }
        View::render('clients::form', ['pageTitle' => 'عميل جديد', 'client' => null]);
    }

    public function store(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('clients.create')) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/clients/create');
        $data = $this->validated();
        if ($data === null) {
            redirect('/clients/create');
        }
        $data['company_id'] = $companyId;
        $data['created_by'] = Auth::id();
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = Database::insert('clients_clients', $data);
        ActivityLog::log('clients.create', 'client', $id, "إضافة عميل: {$data['name']}");
        flash_set('success', 'أُضيف العميل.');
        redirect('/clients/' . $id);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('clients.view')) {
            $this->forbidden();
            return;
        }
        $client = $this->findVisible((int) $params['id'], $companyId);

        // ما يرتبط بالعميل من الإضافات الأخرى (عبر نظام الربط)
        $linkedTasks = [];
        $linkedFiles = [];
        try {
            if (ModuleManager::isActive('tasks')) {
                $linkedTasks = Database::select(
                    "SELECT id, title, status FROM tasks_tasks WHERE company_id = :c AND linked_type = 'client' AND linked_id = :id ORDER BY id DESC LIMIT 10",
                    ['c' => $companyId, 'id' => $client['id']]
                );
            }
            if (ModuleManager::isActive('archive')) {
                $linkedFiles = Database::select(
                    "SELECT f.id, COALESCE(f.title, f.original_name) AS title FROM archive_files f
                       JOIN archive_file_links l ON l.file_id = f.id
                      WHERE f.company_id = :c AND l.linked_module = 'clients' AND l.linked_id = :id AND f.deleted_at IS NULL
                      ORDER BY f.id DESC LIMIT 10",
                    ['c' => $companyId, 'id' => $client['id']]
                );
            }
        } catch (\Throwable $e) {
            log_exception($e);
        }

        View::render('clients::show', [
            'pageTitle' => $client['name'],
            'client' => $client,
            'linkedTasks' => $linkedTasks,
            'linkedFiles' => $linkedFiles,
            'canEdit' => $this->can('clients.edit'),
            'canDelete' => $this->can('clients.delete'),
        ]);
    }

    public function edit(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('clients.edit')) {
            $this->forbidden();
            return;
        }
        $client = $this->findVisible((int) $params['id'], $companyId);
        View::render('clients::form', ['pageTitle' => 'تعديل: ' . $client['name'], 'client' => $client]);
    }

    public function update(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('clients.edit')) {
            $this->forbidden();
            return;
        }
        $client = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/clients/' . $client['id'] . '/edit');
        $data = $this->validated();
        if ($data === null) {
            redirect('/clients/' . $client['id'] . '/edit');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('clients_clients', $data, 'id = :id', ['id' => $client['id']]);
        ActivityLog::log('clients.update', 'client', (int) $client['id'], "تعديل عميل: {$data['name']}");
        flash_set('success', 'حُفظت التعديلات.');
        redirect('/clients/' . $client['id']);
    }

    public function archive(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('clients.delete')) {
            $this->forbidden();
            return;
        }
        $client = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/clients');
        Database::update('clients_clients', ['status' => 'archived', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $client['id']]);
        ActivityLog::log('clients.archive', 'client', (int) $client['id'], "أرشفة عميل: {$client['name']}");
        flash_set('success', 'أُرشف العميل.');
        redirect('/clients');
    }

    // ---------------------------------------------------------------

    private function validated(): ?array
    {
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            flash_set('error', 'اسم العميل مطلوب.');
            return null;
        }
        $type = Request::input('type', 'company');
        if (!in_array($type, ['company', 'person'], true)) {
            $type = 'company';
        }
        return [
            'name' => mb_substr($name, 0, 200),
            'type' => $type,
            'contact_name' => mb_substr(trim((string) Request::input('contact_name', '')), 0, 150) ?: null,
            'phone' => mb_substr(trim((string) Request::input('phone', '')), 0, 50) ?: null,
            'email' => mb_substr(trim((string) Request::input('email', '')), 0, 150) ?: null,
            'address' => mb_substr(trim((string) Request::input('address', '')), 0, 300) ?: null,
            'notes' => trim((string) Request::input('notes', '')) ?: null,
        ];
    }

    private function findVisible(int $id, int $companyId): array
    {
        $client = Database::first('SELECT * FROM clients_clients WHERE id = :id', ['id' => $id]);
        if (!$client || (int) $client['company_id'] !== $companyId) {
            flash_set('error', 'العميل غير موجود.');
            redirect('/clients');
        }
        return $client;
    }

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('clients::no-company', ['pageTitle' => 'العملاء']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return Permission::check($key);
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
}
