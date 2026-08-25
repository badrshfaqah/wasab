<?php
$isManager = !empty($isManager);
$me = (int) current_user()['id'];
?>
<div class="page-head">
    <div><h1>قوالب المستندات</h1><p>قوالبك الخاصة (خلفية ورأس/تذييل وختم ورمز تحقق) تُختار عند كتابة مستند - ويمكنك مشاركة أي قالب مع زملاء ليستخدموه.</p></div>
    <div>
        <a class="btn btn-outline" href="<?= route('/documents') ?>">↩ رجوع للمستندات</a>
        <a class="btn" href="<?= route('/documents/templates/create') ?>">+ قالب جديد</a>
    </div>
</div>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
<?php if (!$templates): ?>
    <div class="empty-state"><div class="ic">🎨</div>لا توجد قوالب بعد — أنشئ قالبك الأول.</div>
<?php endif; ?>
<?php foreach ($templates as $t): ?>
    <?php
    $isMine = !empty($t['created_by']) && (int) $t['created_by'] === $me;
    $isSharedToMe = !empty($t['owner_name']) && !$isMine;
    $canEdit = $isManager || $isMine;
    $sharedWith = $t['shared_with'] ?? [];
    ?>
    <div class="card doc-template-card" style="margin-bottom:0;">
        <?php if ($t['background_image']): ?>
            <div class="doc-template-preview" style="background-image:url('<?= e(route('/media/documents/' . $t['company_id'] . '/' . $t['background_image'])) ?>');"></div>
        <?php else: ?>
            <div class="doc-template-preview">بدون صورة خلفية</div>
        <?php endif; ?>
        <div class="card-title"><span><?= e($t['name']) ?></span></div>
        <p class="hint" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <?php if (empty($t['created_by'])): ?>
                <span class="badge badge-muted">🏢 قالب شركة</span>
            <?php elseif ($isMine): ?>
                <span class="badge badge-info">✨ قالبي</span>
            <?php elseif (!empty($t['owner_name'])): ?>
                <span class="badge badge-muted">🤝 مشاركة من <?= e($t['owner_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($t['qr_enabled'])): ?><span class="badge badge-success">🔳 QR</span><?php endif; ?>
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($canEdit): ?>
            <a class="btn btn-outline btn-sm" href="<?= route('/documents/templates/' . $t['id'] . '/edit') ?>">تعديل</a>
            <form method="post" action="<?= route('/documents/templates/' . $t['id'] . '/delete') ?>" data-confirm="سيتم حذف القالب. المستندات المرتبطة به ستبقى لكن بلا قالب. متابعة؟">
                <?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit">حذف</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($canEdit && !$isSharedToMe && !empty($companyUsers)): ?>
        <details style="margin-top:8px;">
            <summary class="hint" style="cursor:pointer;">🤝 مشاركة القالب<?= $sharedWith ? ' (' . count($sharedWith) . ')' : '' ?></summary>
            <form method="post" action="<?= route('/documents/templates/' . $t['id'] . '/share') ?>" style="margin-top:6px;">
                <?= csrf_field() ?>
                <div style="max-height:140px;overflow:auto;display:flex;flex-direction:column;gap:4px;">
                    <?php foreach ($companyUsers as $u): ?>
                        <?php if ((int) $u['id'] === (int) ($t['created_by'] ?? 0)) continue; ?>
                        <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:.85em;">
                            <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" style="width:auto;"
                                <?= in_array((int) $u['id'], $sharedWith, true) ? 'checked' : '' ?>>
                            <?= e($u['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-outline btn-sm" type="submit" style="margin-top:6px;">حفظ المشاركة</button>
            </form>
        </details>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
