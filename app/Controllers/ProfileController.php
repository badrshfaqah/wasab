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
        $companyId = Auth::companyId();
        $signatures = UserSignature::forUser(Auth::id());
        foreach ($signatures as &$sig) {
            $sig['shared_with'] = UserSignature::shareUserIds((int) $sig['id']);
        }
        unset($sig);

        $stamps = \App\Core\CompanyStamp::forUser(Auth::id());
        foreach ($stamps as &$st) {
            $st['shared_with'] = \App\Core\CompanyStamp::shareUserIds((int) $st['id']);
        }
        unset($st);

        View::render('profile.index', [
            'pageTitle' => 'الملف الشخصي',
            'signatures' => $signatures,
            'stamps' => $stamps,
            'companyUsers' => $companyId ? Database::select(
                "SELECT id, name FROM users WHERE company_id = :c AND status = 'active' AND id != :me ORDER BY name",
                ['c' => $companyId, 'me' => Auth::id()]
            ) : [],
        ]);
    }

    /**
     * تحديث من يحق لهم استخدام توقيع معيّن (توكيل توقيع): صاحب التوقيع يختار
     * زملاء محددين فيظهر توقيعه ضمن خياراتهم عند توقيع المستندات والخطابات.
     */
    public function shareSignature(array $params): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }
        $sig = UserSignature::findForUser((int) $params['id'], Auth::id());
        if (!$sig) {
            flash_set('error', 'التوقيع غير موجود.');
            redirect('/profile');
        }

        $valid = $this->validCompanyUserIds((array) Request::input('user_ids', []));
        $before = UserSignature::shareUserIds((int) $sig['id']);
        UserSignature::setShares((int) $sig['id'], $valid);
        // تنبيه من أُتيح له التوقيع حديثاً
        foreach (array_diff($valid, $before) as $uid) {
            \App\Core\Notification::send(
                $uid,
                '🖋️ شُورك معك توقيع',
                Auth::user()['name'] . ' أتاح لك استخدام توقيعه «' . $sig['name'] . '» عند توقيع المستندات والخطابات.',
                route('/profile')
            );
        }

        ActivityLog::log('profile.signature_share', 'user', Auth::id(), 'تحديث مشاركة توقيع «' . $sig['name'] . '» (' . count($valid) . ' مستخدم)');
        flash_set('success', $valid ? 'حُدّثت مشاركة التوقيع.' : 'أُلغيت مشاركة التوقيع.');
        redirect('/profile');
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

    /** رفع ختم شخصي جديد (يظهر كخيار عند كتابة المستندات، ويمكن مشاركته). */
    public function storeStamp(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }
        $companyId = (int) (Auth::companyId() ?? 0);
        if (!$companyId) {
            flash_set('error', 'الأختام متاحة داخل شركة فقط.');
            redirect('/profile');
        }
        $name = trim((string) Request::input('name', '')) ?: 'ختمي';

        $img = Uploads::handleImage('image', BASE_PATH . '/storage/uploads/stamps/' . $companyId);
        if ($img['error']) {
            flash_set('error', $img['error']);
            redirect('/profile');
        }
        if (!$img['filename']) {
            flash_set('error', 'يرجى اختيار صورة الختم (يُفضّل PNG بخلفية شفافة).');
            redirect('/profile');
        }

        \App\Core\CompanyStamp::create($companyId, $name, $img['filename'], Auth::id());
        ActivityLog::log('profile.stamp_add', 'user', Auth::id(), 'إضافة ختم شخصي');
        flash_set('success', 'تمت إضافة الختم.');
        redirect('/profile');
    }

    public function deleteStamp(array $params): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }
        $stamp = Database::first(
            'SELECT * FROM company_stamps WHERE id = :id AND user_id = :u',
            ['id' => (int) $params['id'], 'u' => Auth::id()]
        );
        if ($stamp) {
            @unlink(BASE_PATH . '/storage/uploads/stamps/' . (int) $stamp['company_id'] . '/' . $stamp['image']);
            Database::delete('company_stamps', 'id = :id AND user_id = :u', ['id' => $stamp['id'], 'u' => Auth::id()]);
            flash_set('success', 'تم حذف الختم.');
        }
        redirect('/profile');
    }

    /** تحديث من يحق لهم استخدام ختم شخصي معيّن (كمشاركة التواقيع). */
    public function shareStamp(array $params): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }
        $stamp = Database::first(
            'SELECT * FROM company_stamps WHERE id = :id AND user_id = :u',
            ['id' => (int) $params['id'], 'u' => Auth::id()]
        );
        if (!$stamp) {
            flash_set('error', 'الختم غير موجود.');
            redirect('/profile');
        }

        $valid = $this->validCompanyUserIds((array) Request::input('user_ids', []));
        $before = \App\Core\CompanyStamp::shareUserIds((int) $stamp['id']);
        \App\Core\CompanyStamp::setShares((int) $stamp['id'], $valid);
        foreach (array_diff($valid, $before) as $uid) {
            \App\Core\Notification::send(
                $uid,
                '🪧 شُورك معك ختم',
                Auth::user()['name'] . ' أتاح لك استخدام ختمه «' . $stamp['name'] . '» على المستندات.',
                route('/profile')
            );
        }

        ActivityLog::log('profile.stamp_share', 'user', Auth::id(), 'تحديث مشاركة ختم «' . $stamp['name'] . '» (' . count($valid) . ' مستخدم)');
        flash_set('success', $valid ? 'حُدّثت مشاركة الختم.' : 'أُلغيت مشاركة الختم.');
        redirect('/profile');
    }

    /** يقصر قائمة معرّفات على زملاء فعليين نشطين في شركة المستخدم (عداه هو). */
    private function validCompanyUserIds(array $requested): array
    {
        $companyId = (int) (Auth::companyId() ?? 0);
        $requested = array_map('intval', $requested);
        if (!$requested || !$companyId) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($requested), '?'));
        $rows = Database::select(
            "SELECT id FROM users WHERE company_id = ? AND status = 'active' AND id IN ({$placeholders})",
            array_merge([$companyId], $requested)
        );
        return array_values(array_diff(array_map(fn ($r) => (int) $r['id'], $rows), [Auth::id()]));
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
