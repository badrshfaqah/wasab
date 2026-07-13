<div class="page-head">
    <div><h1>التقارير</h1><p>أرقام مجمّعة على مستوى الشركة من كل الإضافات المفعّلة.</p></div>
</div>

<?php if ($coreStats): ?>
<div class="cards-row" style="margin-bottom:20px;">
    <?php foreach ($coreStats as $s): ?>
        <div class="stat-card c-<?= e($s['color'] ?? 'primary') ?>"><div class="n"><?= e((string) $s['value']) ?> <?= e($s['icon'] ?? '') ?></div><div class="l"><?= e($s['label']) ?></div></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
    $grouped = [];
    foreach ($moduleStats as $s) {
        $grouped[$s['module_name']][] = $s;
    }
?>
<?php if (!$grouped && !$coreStats): ?>
    <div class="empty-state">
        <div class="ic">📊</div>
        <h3>لا توجد أرقام لعرضها بعد</h3>
        <p>فعّل إضافات، أو ارجع لاحقاً بعد إضافة بيانات.</p>
    </div>
<?php endif; ?>

<?php foreach ($grouped as $moduleName => $items): ?>
    <div class="card" style="margin-bottom:16px;">
        <div class="card-title"><span>🧩 <?= e($moduleName) ?></span></div>
        <div class="cards-row">
            <?php foreach ($items as $s): ?>
                <?php $card = '<div class="stat-card c-' . e($s['color'] ?? 'primary') . '"><div class="n">' . e((string) $s['value']) . ' ' . e($s['icon'] ?? '') . '</div><div class="l">' . e($s['label']) . '</div></div>'; ?>
                <?php if (!empty($s['url'])): ?>
                    <a href="<?= e($s['url']) ?>"><?= $card ?></a>
                <?php else: ?>
                    <?= $card ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
