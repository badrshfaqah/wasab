<?php
$weekdays = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
$monthStartTs = strtotime($month . '-01');
$colorClass = [
    'tasks' => 'cal-event-tasks',
    'archive' => 'cal-event-archive',
    'meetings' => 'cal-event-meetings',
    'documents' => 'cal-event-documents',
];
?>
<div class="page-head">
    <div><h1>التقويم</h1><p>كل التواريخ المحدَّدة من الإضافات المفعّلة: مهام، ملفات، اجتماعات، وغيرها مستقبلاً.</p></div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a class="btn btn-outline btn-sm" href="<?= route('/calendar?month=' . $prevMonth) ?>">‹ السابق</a>
        <a class="btn btn-outline btn-sm" href="<?= route('/calendar') ?>">اليوم</a>
        <a class="btn btn-outline btn-sm" href="<?= route('/calendar?month=' . $nextMonth) ?>">التالي ›</a>
    </div>
</div>

<div class="card">
    <div class="card-title"><span><?= e($monthLabel) ?></span></div>

    <div class="cal-grid">
        <?php foreach ($weekdays as $w): ?>
            <div class="cal-weekday"><?= e($w) ?></div>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $leadingBlanks; $i++): ?>
            <div class="cal-cell cal-cell-blank"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <?php
            $dateStr = date('Y-m-d', mktime(0, 0, 0, (int) date('n', $monthStartTs), $day, (int) date('Y', $monthStartTs)));
            $dayEvents = $eventsByDay[$dateStr] ?? [];
            $isToday = $dateStr === $today;
            ?>
            <div class="cal-cell <?= $isToday ? 'cal-cell-today' : '' ?>">
                <div class="cal-day-num"><?= $day ?></div>
                <?php foreach (array_slice($dayEvents, 0, 4) as $ev): ?>
                    <a class="cal-event <?= $colorClass[$ev['module']] ?? '' ?>" href="<?= e($ev['url']) ?>" title="<?= e($ev['title']) ?>">
                        <?= !empty($ev['time']) ? e($ev['time']) . ' ' : '' ?><?= e($ev['title']) ?>
                    </a>
                <?php endforeach; ?>
                <?php if (count($dayEvents) > 4): ?>
                    <div class="cal-more">+<?= count($dayEvents) - 4 ?> أخرى</div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <div style="display:flex;gap:16px;margin-top:16px;font-size:12px;color:var(--muted);flex-wrap:wrap;">
        <span><span class="cal-legend-dot cal-event-tasks"></span> المهام</span>
        <span><span class="cal-legend-dot cal-event-archive"></span> أرشيف الملفات</span>
        <span><span class="cal-legend-dot cal-event-meetings"></span> الاجتماعات</span>
        <span><span class="cal-legend-dot cal-event-documents"></span> المستندات</span>
    </div>
</div>
