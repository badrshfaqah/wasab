<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Setting;
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

            $update = ['primary_color' => $color, 'updated_at' => date('Y-m-d H:i:s')];
            if ($name !== '') {
                $update['name'] = $name;
            }

            $file = Request::file('logo');
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
                $type = mime_content_type($file['tmp_name']);
                if (isset($allowed[$type])) {
                    $filename = 'company_' . bin2hex(random_bytes(8)) . '.' . $allowed[$type];
                    move_uploaded_file($file['tmp_name'], BASE_PATH . '/storage/uploads/' . $filename);
                    $update['logo'] = $filename;
                }
            }

            Database::update('companies', $update, 'id = :id', ['id' => $companyId]);
            ActivityLog::log('settings.update', 'company', $companyId, 'تحديث إعدادات الشركة');
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
