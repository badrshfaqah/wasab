<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

class UserController
{
    public function index(): void
    {
        $this->guardManageAccess();

        $page = max(1, (int) Request::query('page', 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];
        if (!Auth::isSystemAdmin()) {
            $where = 'u.company_id = :cid';
            $params['cid'] = Auth::companyId();
        }

        $total = (int) (Database::first("SELECT COUNT(*) AS c FROM users u WHERE {$where}", $params)['c'] ?? 0);
        $users = Database::select(
            "SELECT u.*, c.name AS company_name
               FROM users u LEFT JOIN companies c ON c.id = u.company_id
              WHERE {$where}
              ORDER BY u.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        View::render('users.index', [
            'pageTitle' => 'المستخدمون',
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function create(): void
    {
        $this->guardManageAccess();
        View::render('users.form', [
            'pageTitle' => 'إضافة مستخدم',
            'user' => null,
            'companies' => Auth::isSystemAdmin() ? Database::select('SELECT id, name FROM companies ORDER BY name') : [],
            'roles' => $this->availableRoles(Auth::companyId()),
            'userRoleIds' => [],
        ]);
    }

    public function store(): void
    {
        $this->guardManageAccess();
        $this->verifyCsrf('/users/create');

        $data = $this->validated(null);
        if ($data === null) {
            redirect('/users/create');
        }

        Database::beginTransaction();
        try {
            $userId = Database::insert('users', [
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'membership_type' => $data['membership_type'],
                'status' => $data['status'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->syncRoles($userId, Request::input('roles', []));
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            flash_set('error', 'تعذر إنشاء المستخدم: ' . $e->getMessage());
            redirect('/users/create');
        }

        ActivityLog::log('user.create', 'user', $userId, "إنشاء مستخدم: {$data['email']}");
        flash_set('success', 'تم إنشاء المستخدم بنجاح.');
        redirect('/users');
    }

    public function edit(array $params): void
    {
        $this->guardManageAccess();
        $target = $this->findTarget((int) $params['id']);

        $roleIds = array_column(Database::select('SELECT role_id FROM user_roles WHERE user_id = :u', ['u' => $target['id']]), 'role_id');

        View::render('users.form', [
            'pageTitle' => 'تعديل مستخدم',
            'user' => $target,
            'companies' => Auth::isSystemAdmin() ? Database::select('SELECT id, name FROM companies ORDER BY name') : [],
            'roles' => $this->availableRoles($target['company_id']),
            'userRoleIds' => array_map('intval', $roleIds),
        ]);
    }

    public function update(array $params): void
    {
        $this->guardManageAccess();
        $this->verifyCsrf('/users/' . $params['id'] . '/edit');
        $target = $this->findTarget((int) $params['id']);

        $data = $this->validated($target);
        if ($data === null) {
            redirect('/users/' . $params['id'] . '/edit');
        }

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'membership_type' => $data['membership_type'],
            'company_id' => $data['company_id'],
            'status' => $data['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($data['password'])) {
            $update['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        Database::beginTransaction();
        try {
            Database::update('users', $update, 'id = :id', ['id' => $target['id']]);
            $this->syncRoles($target['id'], Request::input('roles', []));
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            flash_set('error', 'تعذر حفظ التعديلات: ' . $e->getMessage());
            redirect('/users/' . $params['id'] . '/edit');
        }

        ActivityLog::log('user.update', 'user', $target['id'], "تعديل مستخدم: {$data['email']}");
        flash_set('success', 'تم تحديث بيانات المستخدم.');
        redirect('/users');
    }

    public function destroy(array $params): void
    {
        $this->guardManageAccess();
        $this->verifyCsrf('/users');
        $target = $this->findTarget((int) $params['id']);

        if ($target['id'] === Auth::id()) {
            flash_set('error', 'لا يمكنك حذف حسابك الحالي.');
            redirect('/users');
        }
        if ($target['membership_type'] === 'system_admin' && !Auth::isSystemAdmin()) {
            flash_set('error', 'لا تملك صلاحية حذف هذا الحساب.');
            redirect('/users');
        }

        Database::delete('users', 'id = :id', ['id' => $target['id']]);
        ActivityLog::log('user.delete', 'user', $target['id'], "حذف مستخدم: {$target['email']}");
        flash_set('success', 'تم حذف المستخدم.');
        redirect('/users');
    }

    // ---------------------------------------------------------------

    /** فقط مدير النظام ومدير الشركة يديران المستخدمين، ويُفرض هذا من الخادم لا الواجهة فقط */
    private function guardManageAccess(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }

    /** يمنع مدير الشركة من الوصول لمستخدم خارج شركته أو من نوع مدير نظام */
    private function findTarget(int $id): array
    {
        $target = Database::first('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        if (!$target) {
            flash_set('error', 'المستخدم غير موجود.');
            redirect('/users');
        }

        if (!Auth::isSystemAdmin()) {
            if ((int) $target['company_id'] !== Auth::companyId() || $target['membership_type'] === 'system_admin') {
                http_response_code(403);
                View::render('errors/403', [], '');
                exit;
            }
        }

        return $target;
    }

    private function availableRoles(?int $companyId): array
    {
        if (!$companyId) {
            return [];
        }
        return Database::select('SELECT id, name FROM roles WHERE company_id = :c ORDER BY name', ['c' => $companyId]);
    }

    private function syncRoles(int $userId, $roleIds): void
    {
        Database::delete('user_roles', 'user_id = :u', ['u' => $userId]);
        if (!is_array($roleIds)) {
            return;
        }
        foreach ($roleIds as $roleId) {
            $roleId = (int) $roleId;
            if ($roleId <= 0) {
                continue;
            }
            Database::insert('user_roles', ['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    /** يعيد بيانات صالحة أو null (مع ضبط رسالة الخطأ) - يفرض قواعد الصلاحيات هنا في الخادم */
    private function validated(?array $target): ?array
    {
        $isEdit = $target !== null;

        $data = [
            'name' => trim((string) Request::input('name', '')),
            'email' => trim((string) Request::input('email', '')),
            'password' => (string) Request::input('password', ''),
            'membership_type' => Request::input('membership_type', 'user'),
            'company_id' => Request::input('company_id', ''),
            'status' => Request::input('status', 'active') === 'inactive' ? 'inactive' : 'active',
        ];

        $rules = ['name' => 'required|max:150', 'email' => 'required|email|max:150'];
        if (!$isEdit) {
            $rules['password'] = 'required|min:8';
        } elseif ($data['password'] !== '') {
            $rules['password'] = 'min:8';
        }

        $v = Validator::make($data, $rules);
        if ($v->fails()) {
            flash_set('error', $v->firstError());
            Session::setOld($data);
            return null;
        }

        $existing = Database::first('SELECT id FROM users WHERE email = :e AND id != :id', [
            'e' => $data['email'],
            'id' => $target['id'] ?? 0,
        ]);
        if ($existing) {
            flash_set('error', 'هذا البريد الإلكتروني مستخدم مسبقاً.');
            return null;
        }

        // --- فرض قواعد الصلاحيات من جهة الخادم ---
        if (Auth::isSystemAdmin()) {
            if (!in_array($data['membership_type'], ['system_admin', 'company_admin', 'user'], true)) {
                $data['membership_type'] = 'user';
            }
            $data['company_id'] = $data['membership_type'] === 'system_admin' ? null : ((int) $data['company_id'] ?: null);
            if ($data['membership_type'] !== 'system_admin' && !$data['company_id']) {
                flash_set('error', 'يجب اختيار الشركة لهذا النوع من المستخدمين.');
                return null;
            }
        } else {
            // مدير الشركة: لا يستطيع إنشاء مدير نظام، ولا نقل مستخدم خارج شركته
            if ($data['membership_type'] === 'system_admin') {
                flash_set('error', 'لا تملك صلاحية إنشاء أو ترقية مستخدم إلى مدير نظام.');
                return null;
            }
            if ($isEdit && $target['membership_type'] === 'system_admin') {
                flash_set('error', 'لا تملك صلاحية تعديل حساب مدير النظام.');
                return null;
            }
            if (!in_array($data['membership_type'], ['company_admin', 'user'], true)) {
                $data['membership_type'] = 'user';
            }
            $data['company_id'] = Auth::companyId();
        }

        return $data;
    }
}
