<?php

namespace Modules\Forms\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HtmlSanitizer;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\View;
use Modules\Forms\Models\FormSetting;

/** إعدادات ترويسة النماذج: خلفية، رأس/تذييل، توقيع، ختم، بادئة الترقيم. */
class FormSettingController
{
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        View::render('forms::settings', [
            'pageTitle' => 'إعدادات النماذج',
            'settings' => FormSetting::getOrCreate($companyId),
        ]);
    }

    public function update(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canManage()) {
            $this->forbidden();
            return;
        }
        $this->verifyCsrf('/forms/settings');
        $current = FormSetting::getOrCreate($companyId);

        $data = [
            'number_prefix' => mb_substr(trim((string) Request::input('number_prefix', '')), 0, 30) ?: null,
            'signer_name' => mb_substr(trim((string) Request::input('signer_name', '')), 0, 120) ?: null,
            'signer_title' => mb_substr(trim((string) Request::input('signer_title', '')), 0, 120) ?: null,
            'header_html' => HtmlSanitizer::sanitize(Request::input('header_html', '')) ?: null,
            'footer_html' => HtmlSanitizer::sanitize(Request::input('footer_html', '')) ?: null,
        ];

        $dir = BASE_PATH . '/storage/uploads/forms/' . $companyId;
        foreach (['background_image', 'signature_image', 'stamp_image'] as $field) {
            $up = Uploads::handleImage($field, $dir);
            if ($up['filename']) {
                $data[$field] = $up['filename'];
                if ($current[$field]) {
                    @unlink($dir . '/' . $current[$field]);
                }
            } elseif ($up['error']) {
                flash_set('error', 'فشل رفع صورة: ' . $up['error']);
                redirect('/forms/settings');
            }
        }

        FormSetting::update($companyId, $data);
        ActivityLog::log('forms.settings', 'company', $companyId, 'تحديث إعدادات النماذج');
        flash_set('success', 'تم حفظ الإعدادات.');
        redirect('/forms/settings');
    }

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('forms::no-company', ['pageTitle' => 'النماذج']);
            exit;
        }
        return $companyId;
    }

    private function canManage(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('forms.manage');
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [], '');
    }
}
