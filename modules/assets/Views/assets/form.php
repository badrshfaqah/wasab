<?php
$isEdit = $asset !== null;
$action = $isEdit ? route('/custody/' . $asset['id']) : route('/custody');
$v = fn ($k) => e($asset[$k] ?? '');
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل الأصل' : 'إضافة أصل' ?></h1></div>
    <a class="btn btn-outline" href="<?= $isEdit ? route('/custody/' . $asset['id']) : route('/custody') ?>">← رجوع</a>
</div>

<div class="card" style="max-width:680px;">
    <form method="post" action="<?= $action ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم الأصل *</label>
            <input type="text" name="name" required maxlength="180" value="<?= $v('name') ?>" placeholder="مثال: لابتوب Dell Latitude">
        </div>
        <div class="grid-2">
            <div class="field">
                <label>التصنيف</label>
                <select name="category_id">
                    <option value="">— بلا تصنيف —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int) ($asset['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>الحالة المادية</label>
                <input type="text" name="condition_note" maxlength="60" value="<?= $v('condition_note') ?>" placeholder="جديد / جيد / مستعمل">
            </div>
        </div>

        <?php
        // الحقول المخصصة لكل تصنيف: تُرسم كلها وتُظهر مجموعة التصنيف المختار فقط
        $customValues = [];
        if (!empty($asset['custom_json'])) {
            $decoded = json_decode((string) $asset['custom_json'], true);
            if (is_array($decoded)) {
                $customValues = $decoded;
            }
        }
        ?>
        <?php foreach ($categories as $c): ?>
            <?php $catFields = \Modules\Assets\Models\AssetCategory::fields($c); ?>
            <?php if ($catFields): ?>
            <div class="asset-custom-fields" data-category="<?= $c['id'] ?>" style="display:none;border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:12px;background:var(--bg);">
                <div class="hint" style="margin-bottom:8px;font-weight:600;">حقول «<?= e($c['name']) ?>»</div>
                <div class="grid-2">
                    <?php foreach ($catFields as $cf): ?>
                        <div class="field" style="margin:0;">
                            <label><?= e($cf) ?></label>
                            <input type="text" name="custom[<?= e($cf) ?>]" maxlength="255" value="<?= e((string) ($customValues[$cf] ?? '')) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="grid-2">
            <div class="field">
                <label>الرمز الداخلي / الباركود</label>
                <input type="text" name="asset_code" maxlength="80" value="<?= $v('asset_code') ?>" dir="ltr">
            </div>
            <div class="field">
                <label>الرقم التسلسلي (Serial)</label>
                <input type="text" name="serial_number" maxlength="120" value="<?= $v('serial_number') ?>" dir="ltr">
            </div>
        </div>
        <div class="grid-3">
            <div class="field">
                <label>تاريخ الشراء</label>
                <input type="date" name="purchase_date" value="<?= $v('purchase_date') ?>">
            </div>
            <div class="field">
                <label>قيمة الشراء</label>
                <input type="number" step="0.01" name="purchase_cost" value="<?= $v('purchase_cost') ?>" dir="ltr">
            </div>
            <div class="field">
                <label>انتهاء الضمان</label>
                <input type="date" name="warranty_expiry" value="<?= $v('warranty_expiry') ?>">
            </div>
        </div>
        <?php if (!$isEdit): ?>
        <div class="field">
            <label>الحالة الأولية</label>
            <select name="status">
                <option value="available">متاح</option>
                <option value="maintenance">صيانة</option>
                <option value="retired">خارج الخدمة</option>
            </select>
            <p class="hint">إسناد العهدة يتم لاحقاً عبر محضر تسليم، لا من هنا.</p>
        </div>
        <?php endif; ?>
        <div class="field">
            <label>صورة الأصل (اختياري)</label>
            <input type="file" name="photo" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="field">
            <label>ملاحظات</label>
            <textarea name="notes" rows="2" maxlength="1000"><?= $v('notes') ?></textarea>
        </div>
        <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إضافة الأصل' ?></button>
    </form>
</div>
<script>
(function () {
    var sel = document.querySelector('select[name="category_id"]');
    if (!sel) return;
    var sync = function () {
        document.querySelectorAll('.asset-custom-fields').forEach(function (box) {
            box.style.display = box.getAttribute('data-category') === sel.value ? '' : 'none';
        });
    };
    sel.addEventListener('change', sync);
    sync();
})();
</script>
