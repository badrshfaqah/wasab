<?php
/** @var array $run @var array $items */
$isDraft = $run['status'] === 'draft';
$totalNet = array_sum(array_map(fn ($i) => (float) $i['net'], $items));
?>
<div class="page-head">
    <div><h1>مسير <?= e($run['month']) ?> <?= $isDraft ? '<span class="badge badge-warning">مسودة</span>' : '<span class="badge badge-success">معتمد</span>' ?></h1>
        <p><?= count($items) ?> موظفاً · إجمالي الصافي: <strong><?= number_format($totalNet, 2) ?></strong></p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/employees/payroll') ?>">← المسيرات</a>
        <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= route('/employees/payroll/' . $run['id'] . '/print') ?>">🖨️ طباعة</a>
        <?php if ($isDraft): ?>
            <form method="post" action="<?= route('/employees/payroll/' . $run['id'] . '/approve') ?>" onsubmit="return confirm('اعتماد المسير؟ يُقفل التعديل ويُشعر الموظفون بكشوفهم.');">
                <?= csrf_field() ?><button class="btn" type="submit">✅ اعتماد المسير</button>
            </form>
            <form method="post" action="<?= route('/employees/payroll/' . $run['id'] . '/delete') ?>" onsubmit="return confirm('حذف المسودة لإعادة التوليد؟');">
                <?= csrf_field() ?><button class="btn btn-outline" type="submit">🗑️ حذف المسودة</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if ($isDraft): ?><p class="hint" style="margin-top:0;">عدّل البدلات/الخصومات لأي بند ثم «💾» — الصافي يُحسب تلقائياً. الخصومات المولّدة تشمل أيام «بدون راتب».</p><?php endif; ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>الموظف</th><th>الأساسي</th><th>البدلات</th><th>الخصومات</th><th>ملاحظة الخصم</th><th>الصافي</th><?php if ($isDraft): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
            <tr>
                <td><strong><?= e($i['full_name']) ?></strong><?= $i['job_title'] ? '<div class="hint">' . e($i['job_title']) . '</div>' : '' ?></td>
                <td><?= number_format((float) $i['base_salary'], 2) ?></td>
                <?php if ($isDraft): ?>
                <form method="post" action="<?= route('/employees/payroll/' . $run['id'] . '/items/' . $i['id']) ?>">
                    <?= csrf_field() ?>
                    <td><input type="number" name="allowances" step="0.01" min="0" value="<?= e((string) $i['allowances']) ?>" style="width:100px;padding:5px 8px;"></td>
                    <td><input type="number" name="deductions" step="0.01" min="0" value="<?= e((string) $i['deductions']) ?>" style="width:100px;padding:5px 8px;"></td>
                    <td><input type="text" name="deduction_note" maxlength="255" value="<?= e((string) ($i['deduction_note'] ?? '')) ?>" style="min-width:140px;padding:5px 8px;"></td>
                    <td><strong><?= number_format((float) $i['net'], 2) ?></strong></td>
                    <td><button class="btn btn-ghost btn-sm" type="submit" title="حفظ البند">💾</button></td>
                </form>
                <?php else: ?>
                    <td><?= number_format((float) $i['allowances'], 2) ?></td>
                    <td><?= number_format((float) $i['deductions'], 2) ?></td>
                    <td class="hint"><?= e((string) ($i['deduction_note'] ?? '—')) ?></td>
                    <td><strong><?= number_format((float) $i['net'], 2) ?></strong></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="<?= $isDraft ? 5 : 4 ?>" style="text-align:end;font-weight:700;">الإجمالي</td><td colspan="2"><strong><?= number_format($totalNet, 2) ?></strong></td></tr></tfoot>
    </table>
    </div>
</div>
