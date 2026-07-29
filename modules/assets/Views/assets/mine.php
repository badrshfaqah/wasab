<?php $badgeColor = ['available' => 'success', 'assigned' => 'info', 'maintenance' => 'warning', 'retired' => 'muted', 'lost' => 'danger']; ?>
<div class="page-head">
    <div><h1>عهدي</h1><p>الأصول المسندة إليك حالياً.</p></div>
</div>

<div class="card">
    <?php if (!$assets): ?>
        <div class="empty-state"><div class="ic">📦</div><h3>لا عهد مسندة إليك</h3><p>ستظهر هنا الأصول التي تُسنَد لعهدتك.</p></div>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الأصل</th><th>التصنيف</th><th>الرقم التسلسلي</th><th>منذ</th></tr></thead>
        <tbody>
        <?php foreach ($assets as $a): ?>
            <tr>
                <td><strong><?= e($a['name']) ?></strong><?= $a['asset_code'] ? ' <span class="hint">(' . e($a['asset_code']) . ')</span>' : '' ?></td>
                <td><?= e($a['category_name'] ?? '—') ?></td>
                <td dir="ltr" style="text-align:end;"><?= e($a['serial_number'] ?: '—') ?></td>
                <td><?= $a['assigned_at'] ? format_date($a['assigned_at'], 'Y-m-d') : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
