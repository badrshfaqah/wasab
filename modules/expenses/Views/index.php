<?php
/** @var array $rows @var string $month @var float $approvedTotal @var bool $canManage @var bool $canSubmit */
$stBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
$stLabel = ['pending' => 'بالانتظار', 'approved' => 'معتمد', 'rejected' => 'مرفوض'];
?>
<div class="page-head">
    <div><h1>المصروفات</h1><p>شهر <?= e($month) ?> — المعتمد: <strong><?= number_format($approvedTotal, 2) ?></strong></p></div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <form method="get" action="<?= route('/expenses') ?>" style="display:flex;gap:6px;">
            <input type="month" name="month" value="<?= e($month) ?>">
            <button class="btn btn-sm btn-outline" type="submit">عرض</button>
        </form>
        <?php if ($canSubmit): ?><a class="btn" href="<?= route('/expenses/new') ?>">+ طلب مصروف</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr>
            <?php if ($canManage): ?><th>الموظف</th><?php endif; ?>
            <th>المبلغ</th><th>التاريخ</th><th>الوصف</th><th>الفاتورة</th><th>الحالة</th>
            <?php if ($canManage): ?><th style="text-align:end;">القرار</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="ic">💰</div><h3>لا مصروفات هذا الشهر</h3><p><?= $canSubmit ? 'قدّم أول طلب من زر «طلب مصروف».' : '' ?></p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $x): ?>
            <tr>
                <?php if ($canManage): ?><td><strong><?= e($x['user_name']) ?></strong></td><?php endif; ?>
                <td><strong><?= number_format((float) $x['amount'], 2) ?></strong></td>
                <td style="white-space:nowrap;"><?= format_date($x['expense_date']) ?></td>
                <td style="max-width:260px;"><?= e($x['description']) ?></td>
                <td>
                    <?php if ($x['receipt_image']): ?>
                        <a href="<?= route('/media/expenses/' . $x['company_id'] . '/' . $x['receipt_image']) ?>" target="_blank" rel="noopener">🧾 عرض</a>
                    <?php else: ?><span class="hint">—</span><?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-<?= $stBadge[$x['status']] ?? 'muted' ?>"><?= e($stLabel[$x['status']] ?? $x['status']) ?></span>
                    <?php if ($x['status'] !== 'pending'): ?><div class="hint"><?= e($x['decided_by_name'] ?? '') ?><?= $x['decision_note'] ? ' · ' . e($x['decision_note']) : '' ?></div><?php endif; ?>
                </td>
                <?php if ($canManage): ?>
                <td style="text-align:end;white-space:nowrap;">
                    <?php if ($x['status'] === 'pending'): ?>
                        <form method="post" action="<?= route('/expenses/' . $x['id'] . '/approve') ?>" style="display:inline;">
                            <?= csrf_field() ?><button class="btn btn-sm" type="submit" onclick="return confirm('اعتماد هذا المصروف؟');">✅</button>
                        </form>
                        <form method="post" action="<?= route('/expenses/' . $x['id'] . '/reject') ?>" style="display:inline;" onsubmit="var n=prompt('سبب الرفض (اختياري):');if(n===null)return false;this.querySelector('[name=note]').value=n;return true;">
                            <?= csrf_field() ?><input type="hidden" name="note" value="">
                            <button class="btn btn-outline btn-sm" type="submit">❌</button>
                        </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
