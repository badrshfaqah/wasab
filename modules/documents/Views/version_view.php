<div class="page-head">
    <div>
        <h1>إصدار سابق #<?= (int) $version['version_no'] ?></h1>
        <p><?= e($version['title']) ?> · حُفظ <?= format_date($version['created_at'], 'Y-m-d H:i') ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/documents/' . $document['id']) ?>">↩ عودة للمستند</a>
        <?php if ($canEdit): ?>
        <form method="post" action="<?= route('/documents/' . $document['id'] . '/versions/' . $version['id'] . '/restore') ?>" data-confirm="استعادة هذا الإصدار؟ سيُحفظ المحتوى الحالي كإصدار قبل الاستبدال.">
            <?= csrf_field() ?>
            <button type="submit" class="btn">♻️ استعادة هذا الإصدار</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-title"><span>محتوى الإصدار</span></div>
    <?php if ($version['content']): ?>
        <div class="doc-content-preview"><?= $version['content'] ?></div>
    <?php else: ?>
        <p class="hint">لا يوجد محتوى في هذا الإصدار.</p>
    <?php endif; ?>
</div>
