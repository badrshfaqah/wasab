<?php

namespace Modules\Phone\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Core\Request;
use App\Core\View;
use Modules\Phone\Models\PhoneCompanySetting;
use Modules\Phone\Models\PhoneUser;

class PhoneController
{
    public function settings(): void
    {
        $this->guardAccess();

        $companyId = Auth::companyId();
        $companySetting = $companyId ? PhoneCompanySetting::forCompany($companyId) : null;
        $phoneUser = PhoneUser::forUser(Auth::id());

        View::render('phone::settings', [
            'pageTitle' => 'إعدادات الهاتف',
            'phoneUser' => $phoneUser,
            'companyConfigured' => !empty($companySetting['api_key']),
        ]);
    }

    public function updateSettings(): void
    {
        $this->guardAccess();

        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/phone/settings');
        }

        $extension = trim((string) Request::input('extension', ''));
        $secret = trim((string) Request::input('secret', ''));
        $enabled = Request::input('enabled') ? true : false;

        if ($extension === '') {
            flash_set('error', 'يرجى إدخال رقم التحويلة.');
            redirect('/phone/settings');
        }

        $existing = PhoneUser::forUser(Auth::id());
        if (!$existing && $secret === '') {
            flash_set('error', 'يرجى إدخال كلمة مرور التحويلة.');
            redirect('/phone/settings');
        }

        PhoneUser::save(Auth::id(), $extension, $secret ?: null, $enabled);
        ActivityLog::log('phone.settings_update', 'phone_user', Auth::id(), 'تحديث إعدادات التحويلة الشخصية');

        flash_set('success', 'تم حفظ إعدادات التحويلة.');
        redirect('/phone/settings');
    }

    private function guardAccess(): void
    {
        if (!Permission::check('phone.view')) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }
}
