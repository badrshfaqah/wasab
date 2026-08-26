<div class="page-head">
    <div><h1>فرد جديد</h1><p>يُضاف الفرد مستقلاً بلا جهة، وتربطه بجهة أو أكثر متى شئت من صفحته.</p></div>
    <a class="btn btn-outline" href="<?= route('/contacts?tab=people') ?>">↩ الدليل</a>
</div>

<div class="card" style="max-width:760px;">
    <form method="post" action="<?= route('/contacts/people') ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field"><label>الاسم الكامل</label><input type="text" name="full_name" required></div>
            <div class="field"><label>المسمّى العام (اختياري)</label><input type="text" name="job_title" placeholder="مستشار، مصوّر، مدير..."></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>الجوال</label><input type="text" name="mobile"></div>
            <div class="field"><label>هاتف آخر</label><input type="text" name="phone"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>البريد</label><input type="email" name="email"></div>
            <div class="field"><label>المدينة</label><input type="text" name="city"></div>
        </div>
        <div class="field"><label>LinkedIn</label><input type="text" name="linkedin"></div>
        <div class="field"><label>ملاحظات</label><textarea name="notes" rows="2"></textarea></div>
        <div class="form-actions">
            <button class="btn" type="submit">حفظ الفرد</button>
            <a class="btn btn-outline" href="<?= route('/contacts?tab=people') ?>">إلغاء</a>
        </div>
    </form>
</div>
