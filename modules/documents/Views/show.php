<?php
use Modules\Documents\Models\Document;

$typeLabels = Document::typeLabels();
$canSubmit = $document['status'] === 'draft' && $document['visibility'] === 'public'
    && ($canManage || (int) $document['created_by'] === (int) current_user()['id']);
$canApproveNow = $document['status'] === 'pending_approval' && ($canManage || $canApprove);
$canSignNow = $document['status'] === 'approved' && ($canManage || $canSign);
$canArchive = $document['status'] !== 'archived'
    && ($canManage || (int) $document['created_by'] === (int) current_user()['id']);
?>
<div class="page-head">
    <div>
        <h1><?= e($document['title']) ?></h1>
        <p>
            <?= e($typeLabels[$document['type']] ?? $document['type']) ?>
            <?php if ($document['number']): ?> · رقم: <strong><?= e($document['number']) ?></strong><?php endif; ?>
            · <?= status_badge($document['status']) ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/documents/' . $document['id'] . '/print') ?>" target="_blank">🖨️ طباعة / حفظ PDF</a>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline" href="<?= route('/documents/' . $document['id'] . '/edit') ?>">تعديل</a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <form method="post" action="<?= route('/documents/' . $document['id']) ?>" data-confirm="سيتم حذف المستند نهائياً. متابعة؟">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit">حذف</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>محتوى المستند</span></div>
        <?php if ($document['content']): ?>
            <div class="doc-content-preview"><?= $document['content'] ?></div>
        <?php else: ?>
            <p class="hint">لا يوجد محتوى.</p>
        <?php endif; ?>

        <?php if ($canSubmit || $canApproveNow || $canSignNow || $canArchive): ?>
            <div class="form-actions" style="margin-top:20px;">
                <?php if ($canSubmit): ?>
                    <form method="post" action="<?= route('/documents/' . $document['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="submit">
                        <button class="btn" type="submit">📤 إرسال للاعتماد</button>
                    </form>
                <?php endif; ?>
                <?php if ($canApproveNow): ?>
                    <form method="post" action="<?= route('/documents/' . $document['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve">
                        <button class="btn" type="submit">✅ اعتماد</button>
                    </form>
                <?php endif; ?>
                <?php if ($canSignNow): ?>
                    <form method="post" action="<?= route('/documents/' . $document['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="sign">
                        <button class="btn" type="submit">✍️ توقيع</button>
                    </form>
                <?php endif; ?>
                <?php if ($canArchive): ?>
                    <form method="post" action="<?= route('/documents/' . $document['id'] . '/status') ?>" data-confirm="أرشفة هذا المستند؟">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="archive">
                        <button class="btn btn-outline" type="submit">🗄️ أرشفة</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>سجل العمليات</span></div>
        <?php if (!$logs): ?>
            <p class="hint">لا يوجد سجل بعد.</p>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
            <div class="doc-log">
                <div><?= e($log['description']) ?></div>
                <div class="doc-log-meta"><?= e($log['user_name'] ?? 'النظام') ?> · <?= format_date($log['created_at'], 'Y-m-d H:i') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
