<?php /** @var array $sections */ ?>
<div class="page-head">
    <div><h1>بانتظار قرارك</h1><p>كل الموافقات والردود المطلوبة منك، من كل الإضافات، في مكان واحد.</p></div>
</div>

<?php $total = array_sum(array_map(fn ($s) => count($s['rows']), $sections)); ?>
<?php if ($total === 0): ?>
    <div class="card"><div class="empty-state"><div class="ic">🎉</div><h3>لا شيء ينتظرك</h3><p>أنجزت كل الموافقات المطلوبة منك — عمل رائع.</p></div></div>
<?php endif; ?>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));align-items:start;">
<?php foreach ($sections as $s): ?>
    <?php if (!$s['rows']) { continue; } ?>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title divided">
            <span><?= $s['title'] ?> <span class="badge badge-warning"><?= count($s['rows']) ?></span></span>
            <a class="btn btn-ghost btn-sm" href="<?= $s['url'] ?>">فتح ←</a>
        </div>
        <?php foreach ($s['rows'] as $r): ?>
            <div style="display:flex;justify-content:space-between;gap:8px;padding:7px 0;border-top:1px solid var(--border);font-size:13px;">
                <?php if (!empty($s['itemUrl'])): ?>
                    <a href="<?= route($s['itemUrl'] . $r['id']) ?>" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($r['label']) ?></a>
                <?php else: ?>
                    <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($r['label']) ?></span>
                <?php endif; ?>
                <span class="hint" style="white-space:nowrap;"><?= e((string) $r['meta']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
</div>
