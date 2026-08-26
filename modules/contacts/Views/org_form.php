<div class="page-head">
    <div><h1>جهة جديدة</h1><p>الجهة لا تُنشأ بلا شخص نتواصل معه — أضفه في الأسفل، ويمكنك إضافة غيره لاحقاً.</p></div>
    <a class="btn btn-outline" href="<?= route('/contacts') ?>">↩ الدليل</a>
</div>

<div class="card" style="max-width:820px;">
    <form method="post" action="<?= route('/contacts/orgs') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="card-title divided"><span>🏢 بيانات الجهة</span></div>
        <div class="grid-2">
            <div class="field"><label>اسم الجهة</label><input type="text" name="name" required></div>
            <div class="field"><label>الاسم التجاري (اختياري)</label><input type="text" name="trade_name"></div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>نوع الجهة</label>
                <select name="kind">
                    <option value="">—</option>
                    <?php foreach ($kinds as $k): ?><option value="<?= e($k) ?>"><?= e($k) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>القطاع</label><input type="text" name="sector" placeholder="فعاليات، صحة، تقنية..."></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الدولة</label><input type="text" name="country"></div>
            <div class="field"><label>المدينة</label><input type="text" name="city"></div>
        </div>
        <div class="field"><label>العنوان</label><input type="text" name="address"></div>
        <div class="grid-2">
            <div class="field"><label>هاتف الجهة</label><input type="text" name="phone"></div>
            <div class="field"><label>البريد العام</label><input type="email" name="email"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الموقع الإلكتروني</label><input type="text" name="website" placeholder="https://"></div>
            <div class="field"><label>الشعار</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div>
        </div>
        <div class="field"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>

        <div class="card-title divided" style="margin-top:16px;"><span>👤 شخص التواصل (مطلوب)</span></div>
        <div class="grid-2">
            <div class="field"><label>الاسم</label><input type="text" name="person_name" required></div>
            <div class="field"><label>المسمّى في هذه الجهة</label><input type="text" name="person_job" placeholder="مثال: مدير التسويق"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الجوال</label><input type="text" name="person_mobile"></div>
            <div class="field"><label>البريد</label><input type="email" name="person_email"></div>
        </div>
        <p class="hint">يُسجَّل هذا الشخص في الدليل كفرد مستقل مرتبط بالجهة — فإن انتقل لجهة أخرى ربطتَه بها دون إعادة إدخال بياناته.</p>

        <div class="form-actions">
            <button class="btn" type="submit">حفظ الجهة</button>
            <a class="btn btn-outline" href="<?= route('/contacts') ?>">إلغاء</a>
        </div>
    </form>
</div>
