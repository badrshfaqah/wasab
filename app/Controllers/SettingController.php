<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Setting;
use App\Core\Uploads;
use App\Core\View;

class SettingController
{
    public function index(): void
    {
        $this->guardAccess();

        $company = null;
        if (!Auth::isSystemAdmin() && Auth::companyId()) {
            $company = Database::first('SELECT * FROM companies WHERE id = :id', ['id' => Auth::companyId()]);
        }

        View::render('settings.index', [
            'pageTitle' => 'الإعدادات',
            'company' => $company,
            'appName' => app_name(),
        ]);
    }

    public function update(): void
    {
        $this->guardAccess();
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/settings');
        }

        if (Auth::isSystemAdmin()) {
            $appName = trim((string) Request::input('app_name', ''));
            if ($appName !== '') {
                Setting::set('app_name', $appName);
                ActivityLog::log('settings.update', 'setting', 'app_name', 'تحديث اسم النظام');
            }
        } else {
            $companyId = Auth::companyId();
            $name = trim((string) Request::input('company_name', ''));
            $color = trim((string) Request::input('primary_color', '#2563eb'));
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#2563eb';
            }
            $sidebarColor = trim((string) Request::input('sidebar_color', '#111827'));
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $sidebarColor)) {
                $sidebarColor = '#111827';
            }

            $update = ['primary_color' => $color, 'sidebar_color' => $sidebarColor, 'updated_at' => date('Y-m-d H:i:s')];
            if ($name !== '') {
                $update['name'] = $name;
            }

            $upload = Uploads::handleImage('logo', BASE_PATH . '/storage/uploads');
            if ($upload['filename']) {
                $update['logo'] = $upload['filename'];
            }

            Database::update('companies', $update, 'id = :id', ['id' => $companyId]);
            ActivityLog::log('settings.update', 'company', $companyId, 'تحديث إعدادات الشركة');

            if ($upload['error']) {
                flash_set('error', 'تم حفظ باقي الإعدادات، لكن فشل رفع الشعار: ' . $upload['error']);
                redirect('/settings');
            }
        }

        flash_set('success', 'تم حفظ الإعدادات.');
        redirect('/settings');
    }

    private function guardAccess(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }
}
