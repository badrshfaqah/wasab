<?php
$stats = $summary;
$shortcuts = [];
$lists = [];
foreach ($widgets as $w) {
    if (($w['type'] ?? '') === 'stat') {
        $stats[] = $w;
    } elseif (($w['type'] ?? '') === 'shortcut') {
        $shortcuts[] = $w;
    } elseif (($w['type'] ?? '') === 'list') {
        $lists[] = $w;
    }
}
$name = current_user()['name'] ?? '';
?>
<div class="page-head">
    <div>
        <h1>مرحباً، <?= e($name) ?> 👋</h1>
        <p>هذه لوحتك الرئيسية، تعرض ما يخصك حسب صلاحياتك والإضافات المفعلة.</p>
    </div>
</div>

<?php if ($stats): ?>
<div class="cards-row">
    <?php foreach ($stats as $s): ?>
        <?php $card = '<div class="stat-card c-' . e($s['color'] ?? 'primary') . '"><div class="n">' . e((string)$s['value']) . ' ' . e($s['icon'] ?? '') . '</div><div class="l">' . e($s['label']) . '</div></div>'; ?>
        <?php if (!empty($s['url'])): ?>
            <a href="<?= e($s['url']) ?>"><?= $card ?></a>
        <?php else: ?>
            <?= $card ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($shortcuts): ?>
<div class="card">
    <div class="card-title">اختصارات سريعة</div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <?php foreach ($shortcuts as $sc): ?>
            <a class="btn btn-outline" href="<?= e($sc['url']) ?>"><?= e($sc['icon'] ?? '') ?> <?= e($sc['label']) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($lists): ?>
<div class="grid-2">
    <?php foreach ($lists as $list): ?>
        <div class="card">
            <div class="card-title"><span><?= e($list['icon'] ?? '') ?> <?= e($list['title']) ?></span></div>
            <?php if (empty($list['items'])): ?>
                <div class="notif-empty"><?= e($list['empty_text'] ?? 'لا توجد عناصر') ?></div>
            <?php else: ?>
                <?php foreach ($list['items'] as $item): ?>
                    <a class="notif-item" style="display:flex;justify-content:space-between;" href="<?= e($item['url'] ?? '#') ?>">
                        <span><?= e($item['label']) ?></span>
                        <?php if (!empty($item['meta'])): ?><span style="color:var(--muted);font-size:12px;"><?= e($item['meta']) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$stats && !$shortcuts && !$lists): ?>
    <div class="empty-state">
        <div class="ic">✨</div>
        <h3>لا توجد عناصر لعرضها بعد</h3>
        <p>ستظهر هنا البطاقات والاختصارات تلقائياً بمجرد تفعيل إضافات جديدة.</p>
    </div>
<?php endif; ?>
