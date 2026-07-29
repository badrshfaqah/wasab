<?php
$badgeColor = ['available' => 'success', 'assigned' => 'info', 'maintenance' => 'warning', 'retired' => 'muted', 'lost' => 'danger'];
$assetsQuery = function (array $filters, array $ov = []) {
    $p = array_filter(array_merge($filters, $ov), fn ($v) => $v !== null && $v !== '');
    return route('/custody' . ($p ? '?' . http_build_query($p) : ''));
};
?>
<div class="page-head">
    <div><h1>العهد والأصول</h1><p>سجل أصول الشركة والعهد المسندة لحامليها.</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($canManage): ?><a class="btn btn-outline" href="<?= route('/custody/categories') ?>">🏷️ التصنيفات</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= route('/custody/handovers') ?>">📋 محاضر التسليم</a>
        <?php if ($canAssign): ?><a class="btn btn-outline" href="<?= route('/custody/handovers/create') ?>">🤝 تسليم عهدة</a><?php endif; ?>
        <?php if ($canCreate): ?><a class="btn" href="<?= route('/custody/create') ?>">+ إضافة أصل</a><?php endif; ?>
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
        <thead><tr><th>الأصل</th><th>التصنيف</th><th>الحالة</th><th>الحامل الحالي</th><th>الرقم التسلسلي</th></tr></thead>
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
