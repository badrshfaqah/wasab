<?php use Modules\Archive\Models\ArchiveFile; ?>
<?php $isEdit = $file !== null; ?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل بيانات ملف' : 'رفع ملف جديد' ?></h1></div>
</div>

<div class="card" style="max-width:760px;">
    <form method="post" action="<?= $isEdit ? route('/archive/' . $file['id']) : route('/archive/upload') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <?php if (!$isEdit): ?>
            <div class="field">
                <label>الملف</label>
                <input type="file" name="file" required>
                <p class="hint">الصيغ المدعومة: PDF, Word, Excel, PowerPoint, صور (PNG/JPG/WEBP/GIF), ZIP. الحد الأقصى 25 ميجابايت.</p>
            </div>
        <?php else: ?>
            <p class="hint">اسم الملف الحالي: <strong><?= e($file['original_name']) ?></strong> (الإصدار <?= (int) $file['version'] ?>). لرفع نسخة جديدة من نفس الملف استخدم "استبدال الملف" في صفحة الملف.</p>
        <?php endif; ?>

        <div class="field">
            <label>عنوان اختياري</label>
            <input type="text" name="title" value="<?= e($file['title'] ?? '') ?>">
        </div>
        <div class="field">
            <label>الوصف</label>
            <textarea name="description"><?= e($file['description'] ?? '') ?></textarea>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>التصنيف</label>
                <select name="category_id" required>
                    <option value="">اختر تصنيفاً</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int) ($file['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>الكلمات المفتاحية</label>
                <input type="text" name="keywords" value="<?= e($file['keywords'] ?? '') ?>" placeholder="افصل بينها بفاصلة">
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>من يستطيع رؤية هذا الملف؟</label>
                <select name="visibility_type" id="file-visibility">
                    <?php foreach (ArchiveFile::visibilityLabels() as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($file['visibility_type'] ?? 'inherit') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>تاريخ انتهاء الصلاحية (اختياري)</label>
                <input type="date" name="expires_at" value="<?= e($file['expires_at'] ?? '') ?>">
            </div>
        </div>
        <div class="field" id="file-access-users-box" style="display:none;">
            <label>المستخدمون المسموح لهم</label>
            <select name="access_users[]" multiple size="6">
                <?php foreach ($companyUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], $accessUserIds, true) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>ملاحظات</label>
            <textarea name="notes"><?= e($file['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'رفع الملف' ?></button>
            <a class="btn btn-outline" href="<?= $isEdit ? route('/archive/' . $file['id']) : route('/archive') ?>">إلغاء</a>
        </div>
    </form>
</div>
<script>
(function () {
    var sel = document.getElementById('file-visibility');
    var box = document.getElementById('file-access-users-box');
    if (!sel || !box) return;
    var sync = function () { box.style.display = sel.value === 'specific_users' ? '' : 'none'; };
    sel.addEventListener('change', sync);
    sync();
})();
</script>
