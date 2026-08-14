<?php
/** @var array $rows @var string $month @var ?array $viewedUser @var array $teamSummary @var bool $canViewTeam */
$fmtMin = function (int $minutes): string {
    if ($minutes <= 0) {
        return '—';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . ' س' . ($m ? ' ' . $m . ' د' : '');
};
$totalMin = 0;
foreach ($rows as $r) {
    if ($r['out_at']) {
        $totalMin += max(0, (int) ((strtotime($r['out_at']) - strtotime($r['in_at'])) / 60));
    }
}
?>
<div class="page-head">
    <div><h1>سجل الحضور</h1><p><?= e($viewedUser['name'] ?? '') ?> — شهر <?= e($month) ?>: <?= count($rows) ?> يوم حضور، إجمالي <?= $fmtMin($totalMin) ?>.</p></div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <form method="get" action="<?= route('/checkins/attendance') ?>" style="display:flex;gap:6px;align-items:center;">
            <input type="month" name="month" value="<?= e($month) ?>">
            <?php if ($canViewTeam && !empty($viewedUser)): ?><input type="hidden" name="user_id" value="<?= (int) $viewedUser['id'] ?>"><?php endif; ?>
            <button class="btn btn-sm" type="submit">عرض</button>
        </form>
        <a class="btn btn-outline" href="<?= route('/checkins') ?>">← المتابعة اليومية</a>
    </div>
</div>

<div class="grid-2" style="align-items:flex-start;">
    <div class="card">
        <div class="card-title"><span>🕐 أيام الشهر</span></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>اليوم</th><th>حضور</th><th>انصراف</th><th>المدة</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4"><div class="empty-state"><div class="ic">🕐</div>لا حضور مسجّلاً هذا الشهر</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= format_date($r['work_date']) ?></td>
                    <td><?= format_date($r['in_at'], 'H:i') ?></td>
                    <td><?= $r['out_at'] ? format_date($r['out_at'], 'H:i') : '<span class="hint">—</span>' ?></td>
                    <td><?= $r['out_at'] ? $fmtMin((int) ((strtotime($r['out_at']) - strtotime($r['in_at'])) / 60)) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if ($canViewTeam && $teamSummary): ?>
    <div class="card">
        <div class="card-title"><span>👥 ملخص الفريق (<?= e($month) ?>)</span></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>الموظف</th><th>أيام الحضور</th><th>إجمالي الساعات</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($teamSummary as $t): ?>
                <tr>
                    <td><?= e($t['name']) ?></td>
                    <td><?= (int) $t['days_present'] ?></td>
                    <td><?= $fmtMin((int) $t['total_minutes']) ?></td>
                    <td style="text-align:end;"><a class="btn btn-ghost btn-sm" href="<?= route('/checkins/attendance?month=' . $month . '&user_id=' . $t['user_id']) ?>">عرض</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>
