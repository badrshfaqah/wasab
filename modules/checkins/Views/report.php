<?php
$dayNames = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
$today = date('Y-m-d');
// إجماليات الفريق
$teamEntries = array_sum(array_map(fn ($r) => (int) $r['entries_count'], $rows));
$teamBlockers = array_sum(array_map(fn ($r) => (int) $r['blockers_count'], $rows));
$moodVals = array_filter(array_map(fn ($r) => $r['avg_mood'] !== null ? (float) $r['avg_mood'] : null, $rows), fn ($v) => $v !== null);
$teamMood = $moodVals ? round(array_sum($moodVals) / count($moodVals), 1) : null;
$moodEmoji = function (?float $avg) use ($moodScale) {
    if ($avg === null) {
        return '<span class="hint">—</span>';
    }
    $rounded = max(1, min(5, (int) round($avg)));
    return '<span title="' . number_format($avg, 1) . '">' . $moodScale[$rounded]['emoji'] . ' ' . number_format($avg, 1) . '</span>';
};
?>
<div class="page-head">
    <div>
        <h1>📊 التقرير الأسبوعي</h1>
        <p>الأسبوع من <?= e($from) ?> إلى <?= e($to) ?><?= $isCurrentWeek ? ' <span class="badge badge-info">الأسبوع الحالي</span>' : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <a class="btn btn-outline btn-sm" href="<?= route('/checkins/report?from=' . $prevFrom) ?>">← الأسبوع السابق</a>
        <?php if (!$isCurrentWeek): ?>
            <a class="btn btn-outline btn-sm" href="<?= route('/checkins/report?from=' . $nextFrom) ?>">الأسبوع التالي →</a>
        <?php endif; ?>
        <a class="btn btn-outline btn-sm" href="<?= route('/checkins/team') ?>">👥 لوحة اليوم</a>
    </div>
</div>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px;">
    <div class="card" style="text-align:center;"><div class="hint">إجمالي التسجيلات</div><div style="font-size:26px;font-weight:800;"><?= $teamEntries ?></div></div>
    <div class="card" style="text-align:center;"><div class="hint">المعوقات</div><div style="font-size:26px;font-weight:800;color:<?= $teamBlockers ? 'var(--warning)' : 'inherit' ?>;"><?= $teamBlockers ?></div></div>
    <div class="card" style="text-align:center;"><div class="hint">متوسط المعنويات</div><div style="font-size:26px;font-weight:800;"><?= $teamMood !== null ? $moodScale[max(1, min(5, (int) round($teamMood)))]['emoji'] . ' ' . number_format($teamMood, 1) : '—' ?></div></div>
    <div class="card" style="text-align:center;"><div class="hint">عدد الموظفين</div><div style="font-size:26px;font-weight:800;"><?= count($rows) ?></div></div>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table-cards">
        <thead>
            <tr>
                <th>الموظف</th>
                <?php foreach ($days as $d): ?>
                    <?php $dow = (int) date('w', strtotime($d)); $isWork = in_array($dow, $workdays, true); ?>
                    <th style="text-align:center;<?= $isWork ? '' : 'opacity:.5;' ?>" title="<?= e($d) ?>">
                        <?= $dayNames[$dow] ?><br><span class="hint" style="font-weight:400;"><?= (int) date('d', strtotime($d)) ?></span>
                    </th>
                <?php endforeach; ?>
                <th style="text-align:center;">أيام</th>
                <th style="text-align:center;">معوقات</th>
                <th style="text-align:center;">المعنويات</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="11"><div class="empty-state"><div class="ic">📊</div>لا موظفون نشطون</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <?php $uid = (int) $r['user_id']; $avg = $r['avg_mood'] !== null ? (float) $r['avg_mood'] : null; ?>
            <tr>
                <td><strong><?= e($r['user_name']) ?></strong></td>
                <?php foreach ($days as $d): ?>
                    <?php
                    $cell = $grid[$uid][$d] ?? null;
                    $future = $d > $today;
                    ?>
                    <td style="text-align:center;">
                        <?php if ($cell): ?>
                            <?php if (!empty($cell['mood']) && isset($moodScale[(int) $cell['mood']])): ?>
                                <span title="<?= e($moodScale[(int) $cell['mood']]['label']) ?>"><?= $moodScale[(int) $cell['mood']]['emoji'] ?></span>
                            <?php else: ?>
                                <span style="color:var(--success);">✓</span>
                            <?php endif; ?>
                            <?= !empty($cell['has_blocker']) ? '<span title="معوق">🚧</span>' : '' ?>
                        <?php elseif ($future): ?>
                            <span class="hint">·</span>
                        <?php else: ?>
                            <span class="hint" style="color:var(--danger);opacity:.5;">✗</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <td style="text-align:center;"><strong><?= (int) $r['entries_count'] ?></strong></td>
                <td style="text-align:center;"><?= (int) $r['blockers_count'] > 0 ? '<span class="badge badge-warning">' . (int) $r['blockers_count'] . '</span>' : '<span class="hint">0</span>' ?></td>
                <td style="text-align:center;"><?= $moodEmoji($avg) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="hint" style="margin-top:12px;">✓ سجّل بلا تحديد معنويات · الوجه = المعنويات · 🚧 يوم فيه معوق · ✗ يوم عمل لم يُسجَّل فيه.</p>
</div>
