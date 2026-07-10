<?php
use App\Core\View;
?>
<div class="page-head">
    <div><h1>إعدادات المستندات</h1><p>إعدادات عامة للشركة: الترقيم، التوقيع، والختم.</p></div>
    <a class="btn btn-outline" href="<?= route('/documents') ?>">↩ رجوع للمستندات</a>
</div>

<div class="card" style="max-width:820px;">
    <form method="post" action="<?= route('/documents/settings') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="field">
            <label>بادئة ترقيم المستندات</label>
            <input type="text" name="number_prefix" value="<?= e($settings['number_prefix']) ?>" maxlength="20">
            <p class="hint">مثال: DOC تعطي أرقاماً مثل DOC-<?= date('Y') ?>-001. التسلسل الحالي: <?= (int) $settings['last_sequence'] ?></p>
        </div>

        <div class="grid-2">
            <div class="field">
                <label>اسم المعتمِد/الموقّع الافتراضي</label>
                <input type="text" name="signer_name" value="<?= e($settings['signer_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>المسمى الوظيفي</label>
                <input type="text" name="signer_title" value="<?= e($settings['signer_title'] ?? '') ?>">
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label>صورة التوقيع</label>
                <input type="file" name="signature_image" accept="image/png,image/jpeg,image/webp">
                <?php if (!empty($settings['signature_image'])): ?>
                    <div style="margin-top:8px;"><img src="<?= e(base_url('storage/uploads/documents/' . $settings['signature_image'])) ?>" alt="" style="max-height:60px;"></div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label>صورة الختم</label>
                <input type="file" name="stamp_image" accept="image/png,image/jpeg,image/webp">
                <?php if (!empty($settings['stamp_image'])): ?>
                    <div style="margin-top:8px;"><img src="<?= e(base_url('storage/uploads/documents/' . $settings['stamp_image'])) ?>" alt="" style="max-height:60px;"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="field">
            <label>رأس افتراضي (يُستخدم عند عدم وجود قالب أو عدم تحديد رأس بالقالب)</label>
            <?= View::renderPartial('documents::partials.editor', [
                'name' => 'header_html',
                'id' => 'settings-header',
                'value' => $settings['header_html'] ?? '',
            ]) ?>
        </div>
        <div class="field">
            <label>تذييل افتراضي</label>
            <?= View::renderPartial('documents::partials.editor', [
                'name' => 'footer_html',
                'id' => 'settings-footer',
                'value' => $settings['footer_html'] ?? '',
            ]) ?>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">حفظ الإعدادات</button>
        </div>
    </form>
</div>
