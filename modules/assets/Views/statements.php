<?php
/** @var array $holders */
$typeLabels = ['employee' => 'موظف', 'user' => 'مستخدم', 'manual' => 'يدوي'];
?>
<div class="page-head">
    <div><h1>كشوف العهد</h1><p>كشف مطبوع بكل ما بعهدة كل حامل حالياً، جاهز للتوقيع.</p></div>
    <div><a class="btn btn-outline" href="<?= route('/custody') ?>">← العهد والأصول</a></div>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الحامل</th><th>النوع</th><th>عدد الأصول</th><th></th></tr></thead>
        <tbody>
        <?php if (!$holders): ?>
            <tr><td colspan="4"><div class="empty-state"><div class="ic">🧾</div>لا توجد عهد مسندة حالياً</div></td></tr>
        <?php endif; ?>
        <?php foreach ($holders as $h): ?>
            <tr>
                <td><strong><?= e($h['holder_name'] ?: '—') ?></strong></td>
                <td><span class="badge badge-muted"><?= e($typeLabels[$h['holder_type']] ?? $h['holder_type']) ?></span></td>
                <td><?= (int) $h['assets_count'] ?></td>
                <td style="text-align:end;">
                    <a class="btn btn-outline btn-sm" target="_blank" rel="noopener"
                       href="<?= route('/custody/statements/' . e($h['holder_type']) . '/' . (int) $h['holder_ref'] . '/print') ?>">🖨️ كشف مطبوع</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
