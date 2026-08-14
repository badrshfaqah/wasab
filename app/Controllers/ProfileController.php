<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Uploads;
use App\Core\UserSignature;
use App\Core\Validator;
use App\Core\View;

class ProfileController
{
    public function show(): void
    {
        View::render('profile.index', [
            'pageTitle' => 'الملف الشخصي',
            'signatures' => UserSignature::forUser(Auth::id()),
        ]);
    }

    /** حفظ تفضيلات إشعارات الجوال: الفئات غير المحددة تُسكَت عن الجوال فقط. */
    public function updatePushPrefs(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }

        $enabled = (array) Request::input('push_categories', []);
        $prefs = [];
        foreach (array_keys(\App\Core\Notification::pushCategories()) as $key) {
            $prefs[$key] = in_array($key, $enabled, true);
        }

        Database::update('users', ['push_prefs' => json_encode($prefs)], 'id = :id', ['id' => Auth::id()]);
        flash_set('success', 'حُفظت تفضيلات الإشعارات.');
        redirect('/profile');
    }

    /** رفع توقيع شخصي جديد (يظهر لاحقاً كخيار عند توقيع المستندات/الخطابات). */
    public function storeSignature(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }
        $companyId = Auth::companyId();
        $name = trim((string) Request::input('name', '')) ?: 'توقيعي';

        $dir = BASE_PATH . '/storage/uploads/signatures/' . ($companyId ?: 0);
        $img = Uploads::handleImage('image', $dir);
        if ($img['error']) {
            flash_set('error', $img['error']);
            redirect('/profile');
        }
        if (!$img['filename']) {
            flash_set('error', 'يرجى اختيار صورة التوقيع (يُفضّل PNG بخلفية شفافة).');
            redirect('/profile');
        }

        UserSignature::create(Auth::id(), $companyId, $name, $img['filename']);
        ActivityLog::log('profile.signature_add', 'user', Auth::id(), 'إضافة توقيع شخصي');
        flash_set('success', 'تمت إضافة التوقيع.');
        redirect('/profile');
    }

    public function deleteSignature(array $params): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }
        $sig = UserSignature::findForUser((int) $params['id'], Auth::id());
        if ($sig) {
            @unlink(BASE_PATH . '/storage/uploads/signatures/' . (int) $sig['company_id'] . '/' . $sig['image']);
            UserSignature::delete((int) $sig['id'], Auth::id());
            flash_set('success', 'تم حذف التوقيع.');
        }
        redirect('/profile');
    }

    public function update(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }

        $user = Auth::user();
        $name = trim((string) Request::input('name', ''));
        $password = (string) Request::input('password', '');

        $v = Validator::make(['name' => $name], ['name' => 'required|max:150']);
        if ($v->fails()) {
            flash_set('error', $v->firstError());
            redirect('/profile');
        }

        // منطقة زمنية للعرض فقط: قيمة فارغة = توقيت النظام الافتراضي، وأي قيمة
        // تُقبل فقط إن كانت معرّف منطقة زمنية صحيحاً (منع إدخال قيم حرة).
        $timezone = trim((string) Request::input('timezone', ''));
        if ($timezone !== '' && !in_array($timezone, timezone_identifiers_list(), true)) {
            flash_set('error', 'منطقة زمنية غير صحيحة.');
            redirect('/profile');
        }

        $update = [
            'name' => $name,
            'timezone' => $timezone !== '' ? $timezone : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($password !== '') {
            if (strlen($password) < 8) {
                flash_set('error', 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.');
                redirect('/profile');
            }
            $update['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', $update, 'id = :id', ['id' => $user['id']]);
        ActivityLog::log('profile.update', 'user', $user['id'], 'تحديث الملف الشخصي');
        flash_set('success', 'تم تحديث ملفك الشخصي.');
        redirect('/profile');
    }
}
