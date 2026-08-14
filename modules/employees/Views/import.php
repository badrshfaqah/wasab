<div class="page-head">
    <div><h1>استيراد موظفين</h1><p>أضف ملفات وظيفية كثيرة دفعة واحدة من ملف CSV.</p></div>
    <a class="btn btn-outline" href="<?= route('/employees') ?>">← الملف الوظيفي</a>
</div>

<div class="card" style="max-width:680px;">
    <form method="post" action="<?= route('/employees/import') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label>ملف CSV <span class="req">*</span></label>
            <input type="file" name="file" accept=".csv,text/csv" required>
            <p class="hint">الحد الأقصى 2 ميجابايت. يُنشأ كل موظف بحالة «نشط»، وتُكمل بقية بياناته لاحقاً من ملفه.</p>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">⬆️ استيراد</button>
            <a class="btn btn-outline" href="<?= route('/employees') ?>">إلغاء</a>
        </div>
    </form>

    <div class="form-section">تنسيق الملف</div>
    <p class="hint" style="margin-bottom:10px;">رتّب الأعمدة بالترتيب التالي (يمكن ترك أي عمود بعد الاسم فارغاً). إن احتوى السطر الأول كلمة «الاسم» أو «name» يُعتبر عنواناً ويُتخطّى.</p>
    <div class="table-wrap">
    <table style="font-size:13px;">
        <thead><tr><th>الاسم *</th><th>رقم الهوية</th><th>المسمى الوظيفي</th><th>القسم</th><th>الجوال</th><th>تاريخ التعيين</th><th>نوع التوظيف</th></tr></thead>
        <tbody>
            <tr><td>أحمد محمد</td><td>1012345678</td><td>محاسب</td><td>المالية</td><td>0501234567</td><td>2024-05-01</td><td>full_time</td></tr>
            <tr><td>سارة خالد</td><td></td><td>مصممة</td><td>التسويق</td><td></td><td></td><td></td></tr>
        </tbody>
    </table>
    </div>
    <p class="hint" style="margin-top:10px;">نوع التوظيف: <code>full_time</code> أو <code>part_time</code> أو <code>contract</code> (يُفترض دوام كامل إن تُرك فارغاً).</p>
</div>
