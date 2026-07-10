<div class="page-head">
    <div><h1>إعدادات الأرشيف</h1></div>
    <a class="btn btn-outline" href="<?= route('/archive') ?>">↩ رجوع للملفات</a>
</div>

<div class="card" style="max-width:520px;">
    <form method="post" action="<?= route('/archive/settings') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>عدد الأيام قبل انتهاء الصلاحية لإظهار التنبيه</label>
            <input type="number" name="expiry_warning_days" min="1" max="365" value="<?= (int) $settings['expiry_warning_days'] ?>">
            <p class="hint">تظهر الملفات ضمن "الملفات التي قاربت على الانتهاء" بالصفحة الرئيسية عندما يتبقى هذا العدد من الأيام أو أقل.</p>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">حفظ الإعدادات</button>
        </div>
    </form>
</div>
