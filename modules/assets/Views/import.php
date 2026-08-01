<div class="page-head">
    <div><h1>استيراد أصول</h1><p>أضف عدداً كبيراً من الأصول دفعة واحدة عبر ملف CSV.</p></div>
    <div><a class="btn btn-outline" href="<?= route('/custody') ?>">← العهد والأصول</a></div>
</div>

<div class="card" style="max-width:680px;">
    <form method="post" action="<?= route('/custody/import') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label>ملف CSV</label>
            <input type="file" name="file" accept=".csv,text/csv" required>
            <div class="hint">الحد الأقصى 2 ميجابايت. تُضاف كل الأصول بحالة «متاح».</div>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">⬆️ استيراد</button>
            <a class="btn btn-outline" href="<?= route('/custody') ?>">إلغاء</a>
        </div>
    </form>

    <hr style="margin:22px 0;border:0;border-top:1px solid var(--border);">

    <h3 style="margin:0 0 8px;">تنسيق الملف</h3>
    <p class="hint" style="margin-bottom:10px;">رتّب الأعمدة بالترتيب التالي (يمكن ترك أي عمود بعد الاسم فارغاً). إن احتوى السطر الأول كلمة «الاسم» أو «name» يُعتبر عنواناً ويُتخطّى.</p>
    <div class="table-wrap">
    <table class="table" style="font-size:13px;">
        <thead><tr><th>الاسم *</th><th>التصنيف</th><th>الرمز</th><th>الرقم التسلسلي</th><th>القيمة</th><th>تاريخ الشراء</th></tr></thead>
        <tbody>
            <tr><td>لابتوب Dell</td><td>أجهزة حاسب</td><td>LT-001</td><td>SN12345</td><td>3500</td><td>2025-01-15</td></tr>
            <tr><td>كرسي مكتب</td><td>أثاث</td><td>CH-014</td><td></td><td>450</td><td></td></tr>
        </tbody>
    </table>
    </div>
    <p class="hint" style="margin-top:10px;">التصنيفات غير الموجودة تُنشأ تلقائياً. الحقل المُعلَّم بـ * إلزامي؛ السطور بدون اسم تُتخطّى.</p>
</div>
