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
            'appUrl' => app_url(),
            'appLogoUrl' => app_logo_url(),
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

            $appUrl = rtrim(trim((string) Request::input('app_url', '')), '/');
            Setting::set('app_url', $appUrl);
            ActivityLog::log('settings.update', 'setting', 'app_url', 'تحديث عنوان الموقع');

            // شعار النظام (علامة بيضاء): يظهر ببوابة الدخول والقائمة الجانبية
            // (لمن لا شعار لشركته) ويتولد منه أيقونتا الجوال 192/512 تلقائياً.
            $logoUpload = Uploads::handleImage('app_logo', BASE_PATH . '/storage/uploads/core');
            if ($logoUpload['filename']) {
                Setting::set('app_logo', $logoUpload['filename']);
                $iconsOk = $this->generateAppIcons(BASE_PATH . '/storage/uploads/core/' . $logoUpload['filename']);
                ActivityLog::log('settings.update', 'setting', 'app_logo', 'تحديث شعار النظام');
                if (!$iconsOk) {
                    flash_set('error', 'تم حفظ الشعار، لكن تعذر توليد أيقونات الجوال تلقائياً (امتداد GD غير متاح) - ستبقى الأيقونة الافتراضية.');
                    redirect('/settings');
                }
            } elseif ($logoUpload['error']) {
                flash_set('error', 'تم حفظ باقي الإعدادات، لكن فشل رفع شعار النظام: ' . $logoUpload['error']);
                redirect('/settings');
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

            $theme = (string) Request::input('theme', '');
            if (!\App\Core\Theme::isValid($theme)) {
                $theme = \App\Core\Theme::DEFAULT;
            }

            $update = ['primary_color' => $color, 'sidebar_color' => $sidebarColor, 'theme' => $theme, 'updated_at' => date('Y-m-d H:i:s')];
            if ($name !== '') {
                $update['name'] = $name;
            }

            $upload = Uploads::handleImage('logo', BASE_PATH . '/storage/uploads/companies');
            if ($upload['filename']) {
                $update['logo'] = $upload['filename'];
            }

            try {
                Database::update('companies', $update, 'id = :id', ['id' => $companyId]);
            } catch (\Throwable $e) {
                log_exception($e);
                flash_set('error', 'تعذر حفظ الإعدادات في قاعدة البيانات. راجع storage/logs/app.log أو تواصل مع مسؤول الاستضافة.');
                redirect('/settings');
            }

            ActivityLog::log('settings.update', 'company', $companyId, 'تحديث إعدادات الشركة');

            if ($upload['error']) {
                flash_set('error', 'تم حفظ باقي الإعدادات، لكن فشل رفع الشعار: ' . $upload['error']);
                redirect('/settings');
            }
        }

        flash_set('success', 'تم حفظ الإعدادات.');
        redirect('/settings');
    }

    /**
     * توليد أيقونتي الجوال (192/512) من شعار النظام: مربع بخلفية بيضاء والشعار
     * بمنتصفه بهامش مريح (آمن لقصّ maskable الدائري بأندرويد). يتطلب امتداد GD.
     */
    private function generateAppIcons(string $sourcePath): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        try {
            $info = getimagesize($sourcePath);
            if (!$info) {
                return false;
            }
            $source = match ($info['mime']) {
                'image/png' => imagecreatefrompng($sourcePath),
                'image/jpeg' => imagecreatefromjpeg($sourcePath),
                'image/webp' => imagecreatefromwebp($sourcePath),
                default => false,
            };
            if ($source === false) {
                return false;
            }

            $srcW = imagesx($source);
            $srcH = imagesy($source);

            foreach ([192, 512] as $size) {
                $canvas = imagecreatetruecolor($size, $size);
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

                $scale = min(($size * 0.84) / $srcW, ($size * 0.84) / $srcH);
                $newW = max(1, (int) round($srcW * $scale));
                $newH = max(1, (int) round($srcH * $scale));
                imagecopyresampled(
                    $canvas, $source,
                    (int) (($size - $newW) / 2), (int) (($size - $newH) / 2),
                    0, 0, $newW, $newH, $srcW, $srcH
                );
                imagepng($canvas, BASE_PATH . "/storage/uploads/core/app-icon-{$size}.png");
                imagedestroy($canvas);
            }
            imagedestroy($source);
            return true;
        } catch (\Throwable $e) {
            log_exception($e);
            return false;
        }
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
