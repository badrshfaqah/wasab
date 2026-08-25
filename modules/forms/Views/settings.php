<?php $companyId = current_user()['company_id']; $img = fn ($f) => $settings[$f] ? route('/media/forms/' . $companyId . '/' . $settings[$f]) : null; ?>
<div class="page-head">
    <div><h1>إعدادات النماذج</h1><p>ترويسة الخطابات وخلفيتها وتوقيعها وختمها وبادئة الترقيم.</p></div>
    <a class="btn btn-outline" href="<?= route('/forms') ?>">← النماذج</a>
</div>

<div class="card" style="max-width:640px;">
    <form method="post" action="<?= route('/forms/settings') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label>بادئة رقم الخطاب</label>
                <input type="text" name="number_prefix" maxlength="30" value="<?= e($settings['number_prefix'] ?? '') ?>" placeholder="مثل: HR" dir="ltr">
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>اسم الموقّع</label>
                <input type="text" name="signer_name" maxlength="120" value="<?= e($settings['signer_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>مسمى الموقّع</label>
                <input type="text" name="signer_title" maxlength="120" value="<?= e($settings['signer_title'] ?? '') ?>" placeholder="مدير الموارد البشرية">
            </div>
        </div>
        <div class="field">
            <label>هوامش نص الخطاب (بالمليمتر)</label>
            <div class="grid-2" style="gap:10px;">
                <div>
                    <label class="hint" style="display:block;margin-bottom:4px;">من الأعلى</label>
                    <input type="number" name="margin_top" min="0" max="80" value="<?= (int) ($settings['margin_top'] ?? 35) ?>">
                </div>
                <div>
                    <label class="hint" style="display:block;margin-bottom:4px;">من الأسفل</label>
                    <input type="number" name="margin_bottom" min="0" max="80" value="<?= (int) ($settings['margin_bottom'] ?? 28) ?>">
                </div>
            </div>
            <div style="margin-top:10px;">
                <label class="hint" style="display:block;margin-bottom:4px;">يميناً ويساراً</label>
                <input type="number" name="margin_x" min="0" max="80" value="<?= (int) ($settings['margin_x'] ?? 25) ?>">
            </div>
            <p class="hint">اضبطها لتناسب ترويسة خطاباتك — وتتكرر مع كل صفحة جديدة عند طول الخطاب.</p>
        </div>

        <div class="field">
            <label>خلفية/ترويسة الخطاب (صورة A4)</label>
            <input type="file" name="background_image" accept="image/png,image/jpeg,image/webp">
            <?php if ($img('background_image')): ?><div style="margin-top:8px;"><img src="<?= e($img('background_image')) ?>" style="max-height:120px;border:1px solid var(--border);border-radius:6px;"></div><?php endif; ?>
        </div>
        <div class="grid-2">
            <div class="field">
                <label>صورة التوقيع</label>
                <input type="file" name="signature_image" accept="image/png,image/jpeg,image/webp">
                <?php if ($img('signature_image')): ?><div style="margin-top:8px;"><img src="<?= e($img('signature_image')) ?>" style="max-height:60px;"></div><?php endif; ?>
            </div>
            <div class="field">
                <label>صورة الختم</label>
                <input type="file" name="stamp_image" accept="image/png,image/jpeg,image/webp">
                <?php if ($img('stamp_image')): ?><div style="margin-top:8px;"><img src="<?= e($img('stamp_image')) ?>" style="max-height:60px;"></div><?php endif; ?>
            </div>
        </div>
        <div class="field">
            <label>رأس مخصص (HTML اختياري)</label>
            <textarea name="header_html" rows="2"><?= e($settings['header_html'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label>تذييل مخصص (HTML اختياري)</label>
            <textarea name="footer_html" rows="2"><?= e($settings['footer_html'] ?? '') ?></textarea>
        </div>
        <button class="btn" type="submit">حفظ الإعدادات</button>
    </form>
</div>
