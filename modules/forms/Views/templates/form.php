<?php
$isEdit = $template !== null;
$action = $isEdit ? route('/forms/templates/' . $template['id']) : route('/forms/templates');
?>
<div class="page-head">
    <div><h1><?= $isEdit ? 'تعديل القالب' : 'قالب جديد' ?></h1></div>
    <a class="btn btn-outline" href="<?= route('/forms/templates') ?>">← القوالب</a>
</div>

<div class="grid-2">
    <div class="card">
        <form method="post" action="<?= $action ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label>اسم القالب *</label>
                <input type="text" name="name" required maxlength="160" value="<?= e($template['name'] ?? '') ?>" placeholder="مثال: تعريف بالراتب">
            </div>
            <div class="field">
                <label>نص القالب * (استخدم حقول الدمج بين الأقواس)</label>
                <textarea name="body" rows="16" required style="line-height:2;"><?= e($template['body'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label>ختم القالب</label>
                <select name="stamp_id">
                    <option value="">— بلا ختم —</option>
                    <?php foreach (($stamps ?? []) as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= (int) ($template['stamp_id'] ?? 0) === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint">يُطبَّق تلقائياً على كل خطاب مُولَّد من هذا القالب. أضف الأختام من <a href="<?= route('/stamps') ?>">أختام الشركة</a>.</p>
            </div>

            <?php require BASE_PATH . '/app/Views/partials/qr_fields.php'; ?>
            <div class="field">
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                    <input type="checkbox" name="is_active" value="1" <?= ($template['is_active'] ?? 1) ? 'checked' : '' ?> style="width:auto;">
                    مفعّل (يظهر في قائمة التوليد)
                </label>
            </div>
            <button class="btn" type="submit"><?= $isEdit ? 'حفظ' : 'إضافة القالب' ?></button>
        </form>
    </div>

    <div class="card">
        <div class="card-title"><span>🏷️ حقول الدمج المعروفة</span></div>
        <p class="hint" style="margin:0 0 10px;">تُملأ تلقائياً من الملف الوظيفي عند اختيار موظف. انسخ الحقل بأقواسه إلى نص القالب. أي حقل تكتبه بأقواس وليس بالقائمة يصبح إدخالاً يدوياً وقت التوليد.</p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($knownFields as $f): ?>
                <code style="background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:3px 8px;font-size:12.5px;">{<?= e($f) ?>}</code>
            <?php endforeach; ?>
        </div>
    </div>
</div>
