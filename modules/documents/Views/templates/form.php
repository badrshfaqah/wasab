<?php
use App\Core\View;

$isEdit = $template !== null;
$positionLabels = [
    'top-right' => 'أعلى اليمين',
    'top-left' => 'أعلى اليسار',
    'bottom-right' => 'أسفل اليمين',
    'bottom-left' => 'أسفل اليسار',
];
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل قالب' : 'قالب جديد' ?></h1></div>
</div>

<div class="card" style="max-width:820px;">
    <form method="post" action="<?= $isEdit ? route('/documents/templates/' . $template['id']) : route('/documents/templates') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label>اسم القالب</label>
            <input type="text" name="name" value="<?= e($template['name'] ?? '') ?>" required>
        </div>

        <div class="field">
            <label>صورة الخلفية (تُعرض كخلفية كاملة لصفحة الطباعة)</label>
            <input type="file" name="background_image" accept="image/png,image/jpeg,image/webp">
            <?php if (!empty($template['background_image'])): ?>
                <div style="margin-top:8px;">
                    <img src="<?= e(route('/media/documents/' . $template['company_id'] . '/' . $template['background_image'])) ?>" alt="" style="max-height:80px;border-radius:6px;border:1px solid var(--border);">
                </div>
            <?php endif; ?>
            <p class="hint">PNG أو JPG أو WEBP، بحد أقصى 2 ميجابايت. يُفضّل مقاس ورقة A4.</p>
        </div>

        <div class="field">
            <label>ختم القالب</label>
            <select name="stamp_id">
                <option value="">— بلا ختم —</option>
                <?php foreach (($stamps ?? []) as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= (int) ($template['stamp_id'] ?? 0) === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">يُطبَّق تلقائياً على كل مستند موقّع مُنشأ من هذا القالب. أضف الأختام من <a href="<?= route('/stamps') ?>">أختام الشركة</a>.</p>
        </div>

        <?php
        $qrBgUrl = !empty($template['background_image'])
            ? route('/media/documents/' . $template['company_id'] . '/' . $template['background_image'])
            : null;
        require BASE_PATH . '/app/Views/partials/qr_fields.php';
        ?>

        <div class="grid-2">
            <div class="field">
                <label>موضع رقم المستند والتاريخ</label>
                <select name="number_position">
                    <?php foreach ($positions as $p): ?>
                        <option value="<?= $p ?>" <?= ($template['number_position'] ?? 'top-right') === $p ? 'selected' : '' ?>><?= e($positionLabels[$p]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label style="display:flex;align-items:center;gap:16px;font-weight:400;margin-top:8px;">
                    <span style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="show_number" value="1" style="width:auto;" <?= !isset($template) || !empty($template['show_number']) ? 'checked' : '' ?>>
                        إظهار الرقم
                    </span>
                    <span style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="show_date" value="1" style="width:auto;" <?= !isset($template) || !empty($template['show_date']) ? 'checked' : '' ?>>
                        إظهار التاريخ
                    </span>
                </label>
            </div>
        </div>

        <div class="field">
            <label>رأس الصفحة (اختياري)</label>
            <?= View::renderPartial('documents::partials.editor', [
                'name' => 'header_html',
                'id' => 'tpl-header',
                'value' => $template['header_html'] ?? '',
            ]) ?>
        </div>
        <div class="field">
            <label>تذييل الصفحة (اختياري)</label>
            <?= View::renderPartial('documents::partials.editor', [
                'name' => 'footer_html',
                'id' => 'tpl-footer',
                'value' => $template['footer_html'] ?? '',
            ]) ?>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إنشاء القالب' ?></button>
            <a class="btn btn-outline" href="<?= route('/documents/templates') ?>">إلغاء</a>
        </div>
    </form>
</div>
