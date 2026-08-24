<?php

namespace Modules\Documents\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HtmlSanitizer;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Documents\Models\DocumentSetting;

class DocumentSettingController
{
    public function edit(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();

        View::render('documents::settings', [
            'pageTitle' => 'إعدادات المستندات',
            'settings' => DocumentSetting::getOrCreate($companyId),
        ]);
    }

    public function update(): void
    {
        $companyId = $this->requireCompanyContext();
        $this->guardManage();
        $this->verifyCsrf();

        $prefix = trim((string) Request::input('number_prefix', 'DOC'));
        if (!preg_match('/^[A-Za-z0-9\-]{1,20}$/', $prefix)) {
            $prefix = 'DOC';
        }

        $data = [
            'number_prefix' => $prefix,
            'signer_name' => trim((string) Request::input('signer_name', '')) ?: null,
            'signer_title' => trim((string) Request::input('signer_title', '')) ?: null,
            'header_html' => HtmlSanitizer::sanitize(Request::input('header_html', '')) ?: null,
            'footer_html' => HtmlSanitizer::sanitize(Request::input('footer_html', '')) ?: null,
        ];

        $current = DocumentSetting::getOrCreate($companyId);

        $signature = Uploads::handleImage('signature_image', BASE_PATH . '/storage/uploads/documents/' . $companyId);
        if ($signature['error']) {
            flash_set('error', 'فشل رفع صورة التوقيع: ' . $signature['error']);
            redirect('/documents/settings');
        }
        if ($signature['filename']) {
            $data['signature_image'] = $signature['filename'];
            if ($current['signature_image']) {
                @unlink(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $current['signature_image']);
            }
        }

        $stamp = Uploads::handleImage('stamp_image', BASE_PATH . '/storage/uploads/documents/' . $companyId);
        if ($stamp['error']) {
            flash_set('error', 'فشل رفع صورة الختم: ' . $stamp['error']);
            redirect('/documents/settings');
        }
        if ($stamp['filename']) {
            $data['stamp_image'] = $stamp['filename'];
            if ($current['stamp_image']) {
                @unlink(BASE_PATH . '/storage/uploads/documents/' . $companyId . '/' . $current['stamp_image']);
            }
        }

        DocumentSetting::update($companyId, $data);
        ActivityLog::log('documents.settings.update', 'document_setting', $companyId, 'تحديث إعدادات المستندات');
        flash_set('success', 'تم حفظ الإعدادات.');
        redirect('/documents/settings');
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('documents::no-company', ['pageTitle' => 'إعدادات المستندات']);
            exit;
        }
        return $companyId;
    }

    private function guardManage(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin() && !\App\Core\Permission::check('documents.manage')) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/documents/settings');
        }
    }
}
