<?php
/** ما يرتبط بهذا الطرف عبر وصاب: مهام، ملفات أرشيف، وعلاقاته في CRM. */
$hasAny = !empty($linked['tasks']) || !empty($linked['files']) || !empty($linked['crm']);
?>
<div class="card">
    <div class="card-title"><span>🔗 المرتبط بهذا الطرف</span></div>
    <?php if (!$hasAny): ?>
        <p class="hint" style="margin-top:0;">لا شيء مرتبط بعد — ستظهر هنا المهام وملفات الأرشيف وعلاقات CRM حين تُربط به.</p>
    <?php endif; ?>

    <?php if (!empty($linked['crm'])): ?>
        <div class="card-title divided"><span>🤝 في إدارة العلاقات</span></div>
        <?php foreach ($linked['crm'] as $r): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                <a href="<?= route('/crm/w/' . $r['workspace_id'] . '/orgs/' . (int) ($org['id'] ?? 0)) ?>">
                    <?= e($r['icon'] ?? '') ?> <?= e($r['workspace_name']) ?>
                </a>
                <span class="hint">
                    <?= $r['last_activity_at'] ? 'آخر تواصل ' . format_date($r['last_activity_at'], 'Y-m-d') : 'لم يبدأ التواصل' ?>
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($linked['tasks'])): ?>
        <div class="card-title divided"><span>📋 مهام</span></div>
        <?php foreach ($linked['tasks'] as $t): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                <a href="<?= route('/tasks/' . $t['id']) ?>"><?= e($t['title']) ?></a>
                <?php if (!empty($t['due_date'])): ?>
                    <span class="badge badge-<?= $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done' ? 'danger' : 'muted' ?>"><?= format_date($t['due_date'], 'Y-m-d') ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($linked['files'])): ?>
        <div class="card-title divided"><span>📎 ملفات الأرشيف</span></div>
        <?php foreach ($linked['files'] as $f): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                <a href="<?= route('/archive/files/' . $f['id']) ?>"><?= e($f['title']) ?></a>
                <span class="hint"><?= format_date($f['created_at'], 'Y-m-d') ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
