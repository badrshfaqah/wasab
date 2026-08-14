<?php
/** @var array $leaves @var ?array $own @var bool $canManage */
$stBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
?>
<div class="page-head">
    <div><h1>الإجازات والأذونات</h1>
        <p><?= $canManage ? 'طلبات موظفي الشركة — المعلّقة أولاً.' : 'طلباتك وحالتها.' ?><?php if ($own): ?> رصيدك السنوي الحالي: <strong><?= (int) $own['annual_leave_balance'] ?> يوم</strong>.<?php endif; ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($canManage): ?><a class="btn btn-outline" href="<?= route('/employees') ?>">← الملف الوظيفي</a><?php endif; ?>
        <?php if ($own || $canManage): ?><a class="btn" href="<?= route('/employees/leaves/request') ?>">+ طلب جديد</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr>
            <?php if ($canManage): ?><th>الموظف</th><?php endif; ?>
            <th>النوع</th><th>الفترة</th><th>المدة</th><th>السبب</th><th>الحالة</th>
            <?php if ($canManage): ?><th style="text-align:end;">القرار</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if (!$leaves): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="ic">🌴</div><h3>لا توجد طلبات بعد</h3><p>قدّم أول طلب إجازة أو إذن من زر «طلب جديد».</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($leaves as $l): ?>
            <tr>
                <?php if ($canManage): ?><td><strong><?= e($l['full_name'] ?? '') ?></strong></td><?php endif; ?>
                <td><?= e($typeLabels[$l['type']] ?? $l['type']) ?></td>
                <td style="white-space:nowrap;"><?= format_date($l['start_date']) ?><?= $l['start_date'] !== $l['end_date'] ? ' ← ' . format_date($l['end_date']) : '' ?></td>
                <td><?= $l['type'] === 'hours' ? rtrim(rtrim((string) $l['hours'], '0'), '.') . ' ساعة' : (int) $l['days_count'] . ' يوم' ?></td>
                <td style="max-width:220px;"><?= e($l['reason'] ?: '—') ?></td>
                <td>
                    <span class="badge badge-<?= $stBadge[$l['status']] ?? 'muted' ?>"><?= e($statusLabels[$l['status']] ?? $l['status']) ?></span>
                    <?php if ($l['status'] !== 'pending'): ?>
                        <div class="hint"><?= e($l['decided_by_name'] ?? '') ?><?= $l['decision_note'] ? ' · ' . e($l['decision_note']) : '' ?></div>
                    <?php endif; ?>
                </td>
                <?php if ($canManage): ?>
                <td style="text-align:end;white-space:nowrap;">
                    <?php if ($l['status'] === 'pending'): ?>
                        <form method="post" action="<?= route('/employees/leaves/' . $l['id'] . '/approve') ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm" type="submit" onclick="return confirm('اعتماد هذا الطلب؟<?= $l['type'] === 'annual' ? ' سيُخصم ' . (int) $l['days_count'] . ' يوم من الرصيد.' : '' ?>');">✅ اعتماد</button>
                        </form>
                        <form method="post" action="<?= route('/employees/leaves/' . $l['id'] . '/reject') ?>" style="display:inline;" onsubmit="var n=prompt('سبب الرفض (اختياري):');if(n===null)return false;this.querySelector('[name=note]').value=n;return true;">
                            <?= csrf_field() ?><input type="hidden" name="note" value="">
                            <button class="btn btn-outline btn-sm" type="submit">❌ رفض</button>
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
