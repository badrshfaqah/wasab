<?php /** @var array $runs */ ?>
<div class="page-head">
    <div><h1>مسير الرواتب</h1><p>مسير شهري: أساسي + بدلات − خصومات (تُخصم إجازات «بدون راتب» تلقائياً).</p></div>
    <a class="btn btn-outline" href="<?= route('/employees') ?>">← الملف الوظيفي</a>
</div>

<div class="card" style="max-width:520px;">
    <form method="post" action="<?= route('/employees/payroll/generate') ?>" style="display:flex;gap:8px;align-items:flex-end;">
        <?= csrf_field() ?>
        <div class="field" style="margin:0;flex:1;"><label>توليد مسير شهر</label><input type="month" name="month" value="<?= date('Y-m') ?>" required></div>
        <button class="btn" type="submit">⚙️ توليد</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>الشهر</th><th>الموظفون</th><th>إجمالي الصافي</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php if (!$runs): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">💵</div><h3>لا مسيرات بعد</h3><p>ولّد أول مسير من الأعلى — يُنشأ بند لكل موظف نشط تلقائياً.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($runs as $r): ?>
            <tr>
                <td><a href="<?= route('/employees/payroll/' . $r['id']) ?>"><strong><?= e($r['month']) ?></strong></a></td>
                <td><?= (int) $r['items_count'] ?></td>
                <td><?= number_format((float) $r['total_net'], 2) ?></td>
                <td><?= $r['status'] === 'approved' ? '<span class="badge badge-success">معتمد</span>' : '<span class="badge badge-warning">مسودة</span>' ?></td>
                <td style="text-align:end;"><a class="btn btn-outline btn-sm" href="<?= route('/employees/payroll/' . $r['id']) ?>">فتح</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
