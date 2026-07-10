<?php
use Modules\Archive\Models\ArchiveFile;

$isEdit = $category !== null;
$preselectedParent = $parentId;
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل تصنيف' : 'تصنيف جديد' ?></h1></div>
</div>

<div class="card" style="max-width:680px;">
    <form method="post" action="<?= $isEdit ? route('/archive/categories/' . $category['id']) : route('/archive/categories') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم التصنيف</label>
            <input type="text" name="name" value="<?= e($category['name'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label>التصنيف الأب (اختياري - لجعله تصنيفاً فرعياً)</label>
            <select name="parent_id">
                <option value="">— تصنيف رئيسي —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $preselectedParent === (int) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>من يستطيع رؤية ملفات هذا التصنيف؟</label>
            <select name="visibility_type" id="cat-visibility">
                <?php foreach (ArchiveFile::visibilityLabels() as $key => $label): ?>
                    <?php if ($key === 'inherit') continue; ?>
                    <option value="<?= $key ?>" <?= ($category['visibility_type'] ?? 'all') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">أي ملف داخل هذا التصنيف يرث هذا الإعداد ما لم تُحدَّد له صلاحية خاصة به.</p>
        </div>
        <div class="field" id="cat-access-users-box" style="display:none;">
            <label>المستخدمون المسموح لهم</label>
            <select name="access_users[]" multiple size="6">
                <?php foreach ($companyUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= in_array((int) $u['id'], $accessUserIds, true) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء التصنيف' ?></button>
            <a class="btn btn-outline" href="<?= route('/archive/categories') ?>">إلغاء</a>
        </div>
    </form>
</div>
<script>
(function () {
    var sel = document.getElementById('cat-visibility');
    var box = document.getElementById('cat-access-users-box');
    if (!sel || !box) return;
    var sync = function () { box.style.display = sel.value === 'specific_users' ? '' : 'none'; };
    sel.addEventListener('change', sync);
    sync();
})();
</script>
