<div class="page-head">
    <div><h1>التحديثات والإصدارات</h1><p>سجل تحديثات النظام حسب كل إصدار.</p></div>
</div>

<?php foreach ($changelog as $entry): ?>
    <div class="card">
        <div class="card-title">
            <span>إصدار <?= e($entry['version']) ?></span>
            <span class="hint"><?= e($entry['date']) ?></span>
        </div>
        <ul style="margin:8px 0 0;padding-inline-start:20px;line-height:1.9;">
            <?php foreach ($entry['changes'] as $change): ?>
                <li><?= e($change) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endforeach; ?>
<?php if (!$changelog): ?>
    <div class="empty-state"><div class="ic">📋</div>لا يوجد سجل تحديثات بعد.</div>
<?php endif; ?>
