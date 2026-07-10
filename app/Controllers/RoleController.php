<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

class RoleController
{
    public function index(): void
    {
        $this->guardManageAccess();

        if (Auth::isSystemAdmin()) {
            $roles = Database::select(
                'SELECT r.*, c.name AS company_name,
                        (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS users_count
                   FROM roles r LEFT JOIN companies c ON c.id = r.company_id
                  ORDER BY r.id DESC'
            );
        } else {
            $roles = Database::select(
                'SELECT r.*, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS users_count
                   FROM roles r WHERE r.company_id = :c ORDER BY r.id DESC',
                ['c' => Auth::companyId()]
            );
        }

        View::render('roles.index', ['pageTitle' => 'الأدوار والصلاحيات', 'roles' => $roles]);
    }

    public function create(): void
    {
        $this->guardManageAccess();
        View::render('roles.form', [
            'pageTitle' => 'إضافة دور',
            'role' => null,
            'companies' => Auth::isSystemAdmin() ? Database::select('SELECT id, name FROM companies ORDER BY name') : [],
            'permissionGroups' => $this->activePermissionGroups(),
            'rolePermissionIds' => [],
        ]);
    }

    public function store(): void
    {
        $this->guardManageAccess();
        $this->verifyCsrf('/roles/create');

        $data = $this->validated();
        if ($data === null) {
            redirect('/roles/create');
        }

        Database::beginTransaction();
        try {
            $roleId = Database::insert('roles', [
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'is_system' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->syncPermissions($roleId, Request::input('permissions', []));
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            log_exception($e);
            flash_set('error', 'تعذر إنشاء الدور. حاول مرة أخرى أو راجع مسؤول النظام.');
            redirect('/roles/create');
        }

        ActivityLog::log('role.create', 'role', $roleId, "إنشاء دور: {$data['name']}");
        flash_set('success', 'تم إنشاء الدور بنجاح.');
        redirect('/roles');
    }

    public function edit(array $params): void
    {
        $this->guardManageAccess();
        $role = $this->findTarget((int) $params['id']);

        $permIds = array_column(
            Database::select('SELECT permission_id FROM role_permissions WHERE role_id = :r', ['r' => $role['id']]),
            'permission_id'
        );

        View::render('roles.form', [
            'pageTitle' => 'تعديل دور',
            'role' => $role,
            'companies' => Auth::isSystemAdmin() ? Database::select('SELECT id, name FROM companies ORDER BY name') : [],
            'permissionGroups' => $this->activePermissionGroups(),
            'rolePermissionIds' => array_map('intval', $permIds),
        ]);
    }

    public function update(array $params): void
    {
        $this->guardManageAccess();
        $this->verifyCsrf('/roles/' . $params['id'] . '/edit');
        $role = $this->findTarget((int) $params['id']);

        $data = $this->validated();
        if ($data === null) {
            redirect('/roles/' . $params['id'] . '/edit');
        }

        Database::beginTransaction();
        try {
            Database::update('roles', [
                'name' => $data['name'],
                'company_id' => $data['company_id'],
            ], 'id = :id', ['id' => $role['id']]);
            $this->syncPermissions($role['id'], Request::input('permissions', []));
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            log_exception($e);
            flash_set('error', 'تعذر حفظ التعديلات. حاول مرة أخرى أو راجع مسؤول النظام.');
            redirect('/roles/' . $params['id'] . '/edit');
        }

        ActivityLog::log('role.update', 'role', $role['id'], "تعديل دور: {$data['name']}");
        flash_set('success', 'تم تحديث الدور.');
        redirect('/roles');
    }

    public function destroy(array $params): void
    {
        $this->guardManageAccess();
        $this->verifyCsrf('/roles');
        $role = $this->findTarget((int) $params['id']);

        Database::delete('roles', 'id = :id', ['id' => $role['id']]);
        ActivityLog::log('role.delete', 'role', $role['id'], "حذف دور: {$role['name']}");
        flash_set('success', 'تم حذف الدور.');
        redirect('/roles');
    }

    // ---------------------------------------------------------------

    private function guardManageAccess(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }

    private function findTarget(int $id): array
    {
        $role = Database::first('SELECT * FROM roles WHERE id = :id', ['id' => $id]);
        if (!$role) {
            flash_set('error', 'الدور غير موجود.');
            redirect('/roles');
        }
        if (!Auth::isSystemAdmin() && (int) $role['company_id'] !== Auth::companyId()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
        return $role;
    }

    /** الصلاحيات القابلة للتعيين: فقط من إضافات مفعلة، مجمّعة حسب الإضافة */
    private function activePermissionGroups(): array
    {
        $all = Database::select('SELECT * FROM permissions ORDER BY module_key, id');
        $modules = ModuleManager::list();
        $names = [];
        foreach ($modules as $m) {
            $names[$m['key']] = $m['name'];
        }

        $groups = [];
        foreach ($all as $perm) {
            if (!ModuleManager::isActive($perm['module_key'])) {
                continue;
            }
            $groups[$perm['module_key']]['label'] = $names[$perm['module_key']] ?? $perm['module_key'];
            $groups[$perm['module_key']]['permissions'][] = $perm;
        }
        return $groups;
    }

    private function syncPermissions(int $roleId, $permissionIds): void
    {
        Database::delete('role_permissions', 'role_id = :r', ['r' => $roleId]);
        if (!is_array($permissionIds)) {
            return;
        }
        foreach ($permissionIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) {
                continue;
            }
            Database::insert('role_permissions', ['role_id' => $roleId, 'permission_id' => $pid]);
        }
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function validated(): ?array
    {
        $data = [
            'name' => trim((string) Request::input('name', '')),
            'company_id' => Request::input('company_id', ''),
        ];

        $v = Validator::make($data, ['name' => 'required|max:100']);
        if ($v->fails()) {
            flash_set('error', $v->firstError());
            Session::setOld($data);
            return null;
        }

        if (Auth::isSystemAdmin()) {
            $data['company_id'] = (int) $data['company_id'] ?: null;
            if (!$data['company_id']) {
                flash_set('error', 'يجب اختيار الشركة التابع لها هذا الدور.');
                return null;
            }
        } else {
            $data['company_id'] = Auth::companyId();
        }

        return $data;
    }
}
