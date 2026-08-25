<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\CompanyStamp;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;

/**
 * إدارة مكتبة أختام الشركة العامة (مدير الشركة/النظام) - وهي المتاحة للجميع.
 * أما الأختام الشخصية فيرفعها كل مستخدم من ملفه الشخصي ويشاركها بنفسه، ولا
 * تظهر هنا ولا يملك المدير حذفها.
 */
class StampController
{
    public function index(): void
    {
        $companyId = $this->requireManager();
        View::render('stamps.index', [
            'pageTitle' => 'أختام الشركة',
            'stamps' => CompanyStamp::companyLibrary($companyId),
        ]);
    }

    public function store(): void
    {
        $companyId = $this->requireManager();
        $this->verifyCsrf();

        $name = trim((string) Request::input('name', '')) ?: 'ختم';
        $dir = BASE_PATH . '/storage/uploads/stamps/' . $companyId;
        $img = Uploads::handleImage('image', $dir);
        if ($img['error']) {
            flash_set('error', $img['error']);
            redirect('/stamps');
        }
        if (!$img['filename']) {
            flash_set('error', 'يرجى اختيار صورة الختم (يُفضّل PNG بخلفية شفافة).');
            redirect('/stamps');
        }

        CompanyStamp::create($companyId, $name, $img['filename']);
        ActivityLog::log('stamps.add', 'company_stamp', null, "إضافة ختم: {$name}");
        flash_set('success', 'تمت إضافة الختم.');
        redirect('/stamps');
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireManager();
        $this->verifyCsrf();

        $stamp = CompanyStamp::findForCompany((int) $params['id'], $companyId);
        // مكتبة الشركة فقط - الأختام الشخصية يحذفها أصحابها من ملفاتهم
        if ($stamp && empty($stamp['user_id'])) {
            @unlink(BASE_PATH . '/storage/uploads/stamps/' . $companyId . '/' . $stamp['image']);
            CompanyStamp::delete((int) $stamp['id'], $companyId);
            ActivityLog::log('stamps.delete', 'company_stamp', (int) $stamp['id'], "حذف ختم: {$stamp['name']}");
            flash_set('success', 'تم حذف الختم.');
        }
        redirect('/stamps');
    }

    private function requireManager(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId || !(Auth::isSystemAdmin() || Auth::isCompanyAdmin())) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
        return $companyId;
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/stamps');
        }
    }
}
