<?php $holderTypeLabels = ['employee' => 'ملف وظيفي', 'user' => 'مستخدم', 'manual' => 'يدوي']; ?>
<div class="page-head">
    <div><h1>محاضر التسليم</h1><p>سجل كل عمليات إسناد العهد.</p></div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline" href="<?= route('/custody') ?>">← الأصول</a>
        <?php if ($canAssign): ?><a class="btn" href="<?= route('/custody/handovers/create') ?>">+ محضر جديد</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الحامل</th><th>النوع</th><th>التاريخ</th><th>الأصول</th><th>غير مُرجعة</th></tr></thead>
        <tbody>
        <?php if (!$handovers): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">📋</div>لا محاضر بعد</div></td></tr>
        <?php endif; ?>
        <?php foreach ($handovers as $h): ?>
            <tr>
                <td><a href="<?= route('/custody/handovers/' . $h['id']) ?>"><strong><?= e($h['holder_name']) ?></strong></a></td>
                <td><span class="badge badge-muted"><?= e($holderTypeLabels[$h['holder_type']] ?? $h['holder_type']) ?></span></td>
                <td><?= format_date($h['handover_date']) ?></td>
                <td><?= (int) $h['items_count'] ?></td>
                <td><?= (int) $h['open_count'] > 0 ? '<span class="badge badge-warning">' . (int) $h['open_count'] . '</span>' : '<span class="badge badge-success">الكل مُرجع</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, route('/custody/handovers')) ?>
</div>
