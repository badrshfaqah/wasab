<?php
$badgeColor = ['available' => 'success', 'assigned' => 'info', 'maintenance' => 'warning', 'retired' => 'muted', 'lost' => 'danger'];
$assetsQuery = function (array $filters, array $ov = []) use ($sort, $dir) {
    $p = array_filter(array_merge($filters, ['sort' => $sort, 'dir' => $dir], $ov), fn ($v) => $v !== null && $v !== '');
    return route('/custody' . ($p ? '?' . http_build_query($p) : ''));
};
// رأس عمود قابل للفرز: ينقر ليفرز تصاعدياً/تنازلياً مع سهم يوضح الاتجاه الحالي
$sortHead = function (string $key, string $label) use ($sort, $dir, $filters, $assetsQuery) {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $url = $assetsQuery($filters, ['sort' => $key, 'dir' => $nextDir, 'page' => null]);
    return '<a href="' . $url . '" style="color:inherit;white-space:nowrap;">' . e($label) . $arrow . '</a>';
};
?>
<div class="page-head">
    <div><h1>العهد والأصول</h1><p>سجل أصول الشركة والعهد المسندة لحامليها.</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php $exq = http_build_query($filters); ?>
        <a class="btn btn-outline btn-sm" href="<?= route('/custody/export/csv' . ($exq ? '?' . $exq : '')) ?>">⬇️ Excel</a>
        <a class="btn btn-outline btn-sm" href="<?= route('/custody/export/print' . ($exq ? '?' . $exq : '')) ?>" target="_blank" rel="noopener">🖨️ PDF</a>
        <?php if ($canManage): ?><a class="btn btn-outline" href="<?= route('/custody/categories') ?>">🏷️ التصنيفات</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= route('/custody/statements') ?>">🧾 كشوف العهد</a>
        <a class="btn btn-outline" href="<?= route('/custody/handovers') ?>">📋 المحاضر</a>
        <?php if ($canCreate): ?><a class="btn btn-outline" href="<?= route('/custody/import') ?>">⬆️ استيراد</a><?php endif; ?>
        <?php if ($canAssign): ?><a class="btn btn-outline" href="<?= route('/custody/handovers/create') ?>">🤝 تسليم</a><?php endif; ?>
        <?php if ($canCreate): ?><a class="btn" href="<?= route('/custody/create') ?>">+ أصل</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <details class="filters-collapse"<?= $filters ? ' open' : '' ?>>
    <summary>🔍 البحث والتصفية<?= $filters ? ' <span class="badge badge-info">مفعّلة</span>' : '' ?></summary>
    <form method="get" action="<?= route('/custody') ?>" class="filters-toolbar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <div class="field" style="margin:0;flex:1;min-width:180px;">
            <label>بحث</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اسم، رمز، رقم تسلسلي، أو اسم الحامل...">
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>الحالة</label>
            <select name="status">
                <option value="">الكل</option>
                <?php foreach ($statusLabels as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>التصنيف</label>
            <select name="category_id">
                <option value="">الكل</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if ($filters): ?><a class="btn btn-outline btn-sm" href="<?= route('/custody') ?>">مسح</a><?php endif; ?>
    </form>
    </details>

    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr>
            <th><?= $sortHead('name', 'الأصل') ?></th>
            <th><?= $sortHead('category', 'التصنيف') ?></th>
            <th><?= $sortHead('status', 'الحالة') ?></th>
            <th><?= $sortHead('holder', 'الحامل الحالي') ?></th>
            <th>الرقم التسلسلي</th>
        </tr></thead>
        <tbody>
        <?php if (!$assets): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">📦</div>لا توجد أصول مطابقة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($assets as $a): ?>
            <tr>
                <td>
                    <a href="<?= route('/custody/' . $a['id']) ?>"><strong><?= e($a['name']) ?></strong></a>
                    <?php if ($a['asset_code']): ?><div class="hint"><?= e($a['asset_code']) ?></div><?php endif; ?>
                </td>
                <td><?= e($a['category_name'] ?? '—') ?></td>
                <td><span class="badge badge-<?= $badgeColor[$a['status']] ?? 'muted' ?>"><?= e($statusLabels[$a['status']] ?? $a['status']) ?></span></td>
                <td><?= $a['current_holder_name'] ? e($a['current_holder_name']) : '<span class="hint">—</span>' ?></td>
                <td dir="ltr" style="text-align:end;"><?= e($a['serial_number'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, $assetsQuery($filters)) ?>
</div>
