<?php

namespace Modules\Phone\Controllers;

use App\Core\ActivityLog;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use Modules\Phone\Models\PhoneCompanySetting;

/**
 * إدارة مفتاح Innocalls API لكل شركة. محصورة بمدير النظام فقط عبر ميدلوير systemAdmin على المسار،
 * وليست صلاحية قابلة للمنح - بحسب طلب العمل: "مدير النظام يحدد للشركة الـ API".
 */
class PhoneAdminController
{
    public function index(): void
    {
        $companies = Database::select('SELECT id, name FROM companies ORDER BY name');
        $settings = [];
        foreach (Database::select('SELECT company_id, api_key FROM phone_company_settings') as $row) {
            $settings[$row['company_id']] = $row['api_key'];
        }

        View::render('phone::admin', [
            'pageTitle' => 'إعدادات الهاتف للشركات',
            'companies' => $companies,
            'settings' => $settings,
        ]);
    }

    public function update(array $params): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/phone/admin');
        }

        $companyId = (int) $params['companyId'];
        $company = Database::first('SELECT id, name FROM companies WHERE id = :id', ['id' => $companyId]);
        if (!$company) {
            flash_set('error', 'الشركة غير موجودة.');
            redirect('/phone/admin');
        }

        $apiKey = trim((string) Request::input('api_key', ''));
        PhoneCompanySetting::save($companyId, $apiKey);
        ActivityLog::log('phone.company_api_update', 'company', $companyId, "تحديث مفتاح API للهاتف - شركة: {$company['name']}");

        flash_set('success', 'تم حفظ مفتاح API لشركة ' . $company['name'] . '.');
        redirect('/phone/admin');
    }
}
