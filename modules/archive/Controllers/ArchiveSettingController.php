<?php

namespace Modules\Archive\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;
use Modules\Archive\Models\ArchiveSetting;

class ArchiveSettingController
{
    public function edit(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();

        View::render('archive::settings', [
            'pageTitle' => 'إعدادات الأرشيف',
            'settings' => ArchiveSetting::getOrCreate($companyId),
        ]);
    }

    public function update(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();
        $this->verifyCsrf();

        $days = (int) Request::input('expiry_warning_days', 7);
        if ($days < 1 || $days > 365) {
            $days = 7;
        }

        ArchiveSetting::update($companyId, ['expiry_warning_days' => $days]);
        ActivityLog::log('archive.settings.update', 'archive_setting', $companyId, 'تحديث إعدادات الأرشيف');
        flash_set('success', 'تم حفظ الإعدادات.');
        redirect('/archive/settings');
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('archive::no-company', ['pageTitle' => 'إعدادات الأرشيف']);
            exit;
        }
        return $companyId;
    }

    private function guardManage(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin() && !\App\Core\Permission::check('archive.manage')) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/archive/settings');
        }
    }
}
