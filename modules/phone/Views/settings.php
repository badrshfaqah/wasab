<div class="page-head">
    <div><h1>إعدادات الهاتف</h1><p>أدخل بيانات تحويلتك الخاصة (تُنشأ من لوحة تحكم مزوّد الخدمة).</p></div>
</div>

<?php if (!$companyConfigured): ?>
    <div class="alert alert-warning">لم يُفعّل مدير النظام خدمة الهاتف لشركتك بعد، لن يعمل الاتصال حتى يتم ذلك.</div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
    <form method="post" action="<?= route('/phone/settings') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>رقم التحويلة (Extension)</label>
            <input type="text" name="extension" value="<?= e($phoneUser['extension'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label>كلمة مرور التحويلة (WebRTC Secret) <?= $phoneUser ? '(اتركها فارغة لعدم التغيير)' : '' ?></label>
            <input type="password" name="secret" <?= $phoneUser ? '' : 'required' ?>>
        </div>
        <div class="field">
            <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                <input type="checkbox" name="enabled" value="1" style="width:auto;" <?= (!$phoneUser || $phoneUser['enabled']) ? 'checked' : '' ?>>
                تفعيل الهاتف لحسابي
            </label>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">حفظ</button>
        </div>
    </form>
</div>
