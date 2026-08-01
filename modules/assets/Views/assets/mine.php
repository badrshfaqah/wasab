<?php $badgeColor = ['available' => 'success', 'assigned' => 'info', 'maintenance' => 'warning', 'retired' => 'muted', 'lost' => 'danger']; ?>
<div class="page-head">
    <div><h1>عهدي</h1><p>الأصول المسندة إليك حالياً.</p></div>
</div>

<?php if (!empty($pendingAck)): ?>
<div class="card" style="border-inline-start:4px solid var(--warning);">
    <div class="card-title"><span>✍️ بانتظار إقرارك بالاستلام</span></div>
    <p class="hint" style="margin:0 0 12px;">سُجّلت العهد التالية باسمك ولم تُقرّ باستلامها بعد. الإقرار يؤكد استلامك لها.</p>
    <?php foreach ($pendingAck as $h): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:10px 0;border-bottom:1px solid var(--border);">
            <div>
                <strong>محضر #<?= (int) $h['id'] ?></strong> — <?= (int) $h['items_count'] ?> أصل
                <div class="hint">بتاريخ <?= format_date($h['handover_date'], 'Y-m-d') ?></div>
            </div>
            <form method="post" action="<?= route('/custody/handovers/' . $h['id'] . '/acknowledge') ?>" onsubmit="return confirm('تأكيد إقرارك باستلام عهدة هذا المحضر؟');">
                <?= csrf_field() ?>
                <button class="btn btn-sm" type="submit">✅ أقرّ بالاستلام</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

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
