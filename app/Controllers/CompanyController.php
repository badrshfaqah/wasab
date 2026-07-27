<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Uploads;
use App\Core\Validator;
use App\Core\View;

class CompanyController
{
    public function index(): void
    {
        $page = max(1, (int) Request::query('page', 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $total = Database::count('companies');
        $companies = Database::select(
            "SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS users_count
               FROM companies c ORDER BY c.id DESC LIMIT {$perPage} OFFSET {$offset}"
        );

        View::render('companies.index', [
            'pageTitle' => 'الشركات',
            'companies' => $companies,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function create(): void
    {
        View::render('companies.form', ['pageTitle' => 'إضافة شركة', 'company' => null]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->validated();
        if ($data === null) {
            redirect('/companies/create');
        }

        $upload = Uploads::handleImage('logo', BASE_PATH . '/storage/uploads/companies');

        try {
            Database::insert('companies', [
                'name' => $data['name'],
                'primary_color' => $data['primary_color'],
                'sidebar_color' => $data['sidebar_color'],
                'logo' => $upload['filename'],
                'status' => $data['status'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_exception($e);
            flash_set('error', 'تعذر إنشاء الشركة في قاعدة البيانات. راجع storage/logs/app.log أو تواصل مع مسؤول الاستضافة.');
            redirect('/companies/create');
        }

        ActivityLog::log('company.create', 'company', $data['name'], "إنشاء شركة: {$data['name']}");
        if ($upload['error']) {
            flash_set('error', 'تم إنشاء الشركة، لكن فشل رفع الشعار: ' . $upload['error']);
        } else {
            flash_set('success', 'تم إنشاء الشركة بنجاح.');
        }
        redirect('/companies');
    }

    public function edit(array $params): void
    {
        $company = Database::first('SELECT * FROM companies WHERE id = :id', ['id' => $params['id']]);
        if (!$company) {
            flash_set('error', 'الشركة غير موجودة.');
            redirect('/companies');
        }
        View::render('companies.form', ['pageTitle' => 'تعديل شركة', 'company' => $company]);
    }

    public function update(array $params): void
    {
        $this->verifyCsrf();
        $company = Database::first('SELECT * FROM companies WHERE id = :id', ['id' => $params['id']]);
        if (!$company) {
            flash_set('error', 'الشركة غير موجودة.');
            redirect('/companies');
        }

        $data = $this->validated();
        if ($data === null) {
            redirect('/companies/' . $params['id'] . '/edit');
        }

        $upload = Uploads::handleImage('logo', BASE_PATH . '/storage/uploads/companies');
        $logo = $upload['filename'] ?: $company['logo'];

        try {
            Database::update('companies', [
                'name' => $data['name'],
                'primary_color' => $data['primary_color'],
                'sidebar_color' => $data['sidebar_color'],
                'logo' => $logo,
                'status' => $data['status'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $company['id']]);
        } catch (\Throwable $e) {
            log_exception($e);
            flash_set('error', 'تعذر حفظ التعديلات في قاعدة البيانات. راجع storage/logs/app.log أو تواصل مع مسؤول الاستضافة.');
            redirect('/companies/' . $company['id'] . '/edit');
        }

        ActivityLog::log('company.update', 'company', $company['id'], "تعديل شركة: {$data['name']}");
        if ($upload['error']) {
            flash_set('error', 'تم حفظ باقي البيانات، لكن فشل رفع الشعار: ' . $upload['error']);
        } else {
            flash_set('success', 'تم تحديث بيانات الشركة.');
        }
        redirect('/companies');
    }

    public function destroy(array $params): void
    {
        $this->verifyCsrf();
        $company = Database::first('SELECT * FROM companies WHERE id = :id', ['id' => $params['id']]);
        if (!$company) {
            redirect('/companies');
        }

        $usersCount = Database::count('users', 'company_id = :c', ['c' => $company['id']]);
        if ($usersCount > 0) {
            flash_set('error', 'لا يمكن حذف شركة يتبع لها مستخدمون. انقل المستخدمين أو احذفهم أولاً.');
            redirect('/companies');
        }

        Database::delete('companies', 'id = :id', ['id' => $company['id']]);
        ActivityLog::log('company.delete', 'company', $company['id'], "حذف شركة: {$company['name']}");
        flash_set('success', 'تم حذف الشركة.');
        redirect('/companies');
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/companies');
        }
    }

    private function validated(): ?array
    {
        $data = [
            'name' => trim((string) Request::input('name', '')),
            'primary_color' => trim((string) Request::input('primary_color', '#2563eb')),
            'sidebar_color' => trim((string) Request::input('sidebar_color', '#111827')),
            'status' => Request::input('status', 'active') === 'inactive' ? 'inactive' : 'active',
        ];

        $v = Validator::make($data, ['name' => 'required|max:150']);
        if ($v->fails()) {
            flash_set('error', $v->firstError());
            Session::setOld($data);
            return null;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $data['primary_color'])) {
            $data['primary_color'] = '#2563eb';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $data['sidebar_color'])) {
            $data['sidebar_color'] = '#111827';
        }

        return $data;
    }
}
