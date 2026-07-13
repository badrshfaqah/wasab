<div class="page-head">
    <div><h1>نتائج البحث</h1><p><?= $query !== '' ? 'عن: "' . e($query) . '"' : 'ابحث بكل الإضافات المفعّلة دفعة واحدة.' ?></p></div>
</div>

<div class="card" style="margin-bottom:16px;">
    <form method="get" action="<?= route('/search') ?>" style="display:flex;gap:8px;">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="ابحث عن مهمة، ملف موظف، مستند، اجتماع..." autofocus style="flex:1;">
        <button class="btn btn-sm" type="submit">بحث</button>
    </form>
</div>

<?php if ($query !== '' && mb_strlen($query) < $minLength): ?>
    <div class="empty-state"><div class="ic">🔎</div>اكتب حرفين على الأقل للبحث.</div>
<?php elseif ($query === ''): ?>
    <div class="empty-state"><div class="ic">🔎</div>اكتب كلمة البحث بالأعلى.</div>
<?php elseif (!$results): ?>
    <div class="empty-state"><div class="ic">🔎</div>لا توجد نتائج مطابقة لـ "<?= e($query) ?>".</div>
<?php else: ?>
    <?php
        $grouped = [];
        foreach ($results as $r) {
            $grouped[$r['module_name']][] = $r;
        }
    ?>
    <?php foreach ($grouped as $moduleName => $items): ?>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-title"><span>🧩 <?= e($moduleName) ?></span><span class="badge badge-muted"><?= count($items) ?></span></div>
            <?php foreach ($items as $item): ?>
                <a class="doc-log" style="display:flex;" href="<?= e($item['url']) ?>">
                    <div>
                        <div><?= e($item['icon'] ?? '📄') ?> <strong><?= e($item['title']) ?></strong></div>
                        <?php if (!empty($item['subtitle'])): ?><div class="hint" style="margin-top:2px;"><?= e($item['subtitle']) ?></div><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
