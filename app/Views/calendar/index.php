<?php
$weekdays = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
$monthStartTs = strtotime($month . '-01');
$colorClass = [
    'tasks' => 'cal-event-tasks',
    'archive' => 'cal-event-archive',
    'meetings' => 'cal-event-meetings',
    'documents' => 'cal-event-documents',
    'company' => 'cal-event-company',
];
$monthCompanyEvents = array_filter($companyEvents, fn ($ce) => substr($ce['event_date'], 0, 7) === $month);
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

    <div class="cal-grid-scroll">
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
                        <?php if (!empty($ev['time'])): ?><bdi><?= e($ev['time']) ?></bdi> <?php endif; ?><?= e($ev['title']) ?>
                    </a>
                <?php endforeach; ?>
                <?php if (count($dayEvents) > 4): ?>
                    <div class="cal-more">+<?= count($dayEvents) - 4 ?> أخرى</div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
    </div>

    <div style="display:flex;gap:16px;margin-top:16px;font-size:12px;color:var(--muted);flex-wrap:wrap;">
        <span><span class="cal-legend-dot cal-event-tasks"></span> المهام</span>
        <span><span class="cal-legend-dot cal-event-archive"></span> أرشيف الملفات</span>
        <span><span class="cal-legend-dot cal-event-meetings"></span> الاجتماعات</span>
        <span><span class="cal-legend-dot cal-event-documents"></span> المستندات</span>
        <span><span class="cal-legend-dot cal-event-company"></span> أحداث الشركة</span>
    </div>
</div>

<?php if ($canManageEvents): ?>
<div class="card">
    <div class="card-title"><span>إضافة حدث خاص بالشركة</span></div>
    <form method="post" action="<?= route('/calendar/events') ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label>عنوان الحدث</label>
                <input type="text" name="title" required maxlength="200">
            </div>
            <div class="field">
                <label>التاريخ</label>
                <input type="date" name="event_date" required value="<?= e($month . '-01') ?>">
            </div>
        </div>
        <div class="field">
            <label>وصف (اختياري)</label>
            <textarea name="description" rows="2" maxlength="500"></textarea>
        </div>
        <div class="field">
            <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                <input type="checkbox" name="send_reminder" value="1" checked style="width:auto;">
                🔔 إرسال تنبيه لجميع أعضاء الشركة يوم الحدث
            </label>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">إضافة الحدث</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($monthCompanyEvents): ?>
<div class="card">
    <div class="card-title"><span>أحداث الشركة هذا الشهر</span></div>
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th>التاريخ</th><th>العنوان</th><th>الوصف</th><th>التنبيه</th><th>أضافها</th><?php if ($canManageEvents): ?><th></th><?php endif; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($monthCompanyEvents as $ce): ?>
                <tr id="event-<?= (int) $ce['id'] ?>">
                    <td><?= e($ce['event_date']) ?></td>
                    <td><?= e($ce['title']) ?></td>
                    <td><?= e($ce['description'] ?? '') ?></td>
                    <td><?= !empty($ce['send_reminder']) ? '🔔 مفعّل' : '🔕 معطّل' ?></td>
                    <td><?= e($ce['creator_name'] ?? '-') ?></td>
                    <?php if ($canManageEvents): ?>
                    <td>
                        <form method="post" action="<?= route('/calendar/events/' . $ce['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا الحدث؟');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
