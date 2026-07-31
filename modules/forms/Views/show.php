<div class="page-head">
    <div>
        <h1><?= e($letter['title']) ?></h1>
        <p>رقم الخطاب: <strong dir="ltr"><?= e($letter['number']) ?></strong><?= $letter['recipient_name'] ? ' · المستفيد: ' . e($letter['recipient_name']) : '' ?> · <?= format_date($letter['created_at'], 'Y-m-d') ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/forms') ?>">← القائمة</a>
        <a class="btn" href="<?= route('/forms/' . $letter['id'] . '/print') ?>" target="_blank" rel="noopener">🖨️ طباعة / حفظ PDF</a>
        <?php if ($canDelete): ?>
        <form method="post" action="<?= route('/forms/' . $letter['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا الخطاب؟');">
            <?= csrf_field() ?><button class="btn btn-danger" type="submit">حذف</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div style="white-space:pre-wrap;line-height:2.1;font-size:15px;"><?= e($letter['body']) ?></div>
</div>
