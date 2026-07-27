<?php
use App\Core\Auth;

$weekdays = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
$monthStartTs = strtotime($month . '-01');
$colorClass = [
    'tasks' => 'cal-event-tasks',
    'archive' => 'cal-event-archive',
    'meetings' => 'cal-event-meetings',
    'documents' => 'cal-event-documents',
    'company' => 'cal-event-company',
    'personal' => 'cal-event-personal',
    'employees' => 'cal-event-employees',
];
$monthCompanyEvents = array_filter($companyEvents, fn ($ce) => substr($ce['event_date'], 0, 7) === $month);
?>
<div class="page-head">
    <div><h1>التقويم</h1><p>كل التواريخ المحدَّدة من الإضافات المفعّلة، مع أحداث الشركة وأحداثك الشخصية.</p></div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a class="btn btn-outline btn-sm" href="<?= route('/calendar?month=' . $prevMonth) ?>">‹ السابق</a>
        <a class="btn btn-outline btn-sm" href="<?= route('/calendar') ?>">اليوم</a>
        <a class="btn btn-outline btn-sm" href="<?= route('/calendar?month=' . $nextMonth) ?>">التالي ›</a>
    </div>
</div>

<div class="cal-layout">
    <!-- العمود الرئيسي (يمين بالاتجاه العربي): التقويم -->
    <div class="cal-main">
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

            <div style="display:flex;gap:14px;margin-top:12px;font-size:12px;color:var(--muted);flex-wrap:wrap;">
                <span><span class="cal-legend-dot cal-event-tasks"></span> المهام</span>
                <span><span class="cal-legend-dot cal-event-archive"></span> أرشيف الملفات</span>
                <span><span class="cal-legend-dot cal-event-meetings"></span> الاجتماعات</span>
                <span><span class="cal-legend-dot cal-event-documents"></span> المستندات</span>
                <span><span class="cal-legend-dot cal-event-company"></span> أحداث الشركة</span>
                <span><span class="cal-legend-dot cal-event-personal"></span> أحداثي الشخصية</span>
                <span><span class="cal-legend-dot cal-event-employees"></span> الملف الوظيفي</span>
            </div>
        </div>

        <?php if ($monthCompanyEvents): ?>
        <div class="card">
            <div class="card-title"><span>أحداث هذا الشهر</span></div>
            <div class="table-wrap">
            <table class="table-cards">
                <thead>
                    <tr><th>التاريخ</th><th>العنوان</th><th>النطاق</th><th>التنبيه</th><th>أضافها</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($monthCompanyEvents as $ce): ?>
                        <?php
                        $isPersonal = !empty($ce['user_id']);
                        $canDeleteRow = $canManageEvents || ($isPersonal && (int) $ce['user_id'] === (int) Auth::id());
                        ?>
                        <tr id="event-<?= (int) $ce['id'] ?>">
                            <td><?= e($ce['event_date']) ?></td>
                            <td>
                                <?= e($ce['title']) ?>
                                <?php if (!empty($ce['description'])): ?><div class="hint"><?= e($ce['description']) ?></div><?php endif; ?>
                            </td>
                            <td><?= $isPersonal ? '<span class="badge badge-info">👤 شخصي</span>' : '<span class="badge badge-muted">🏢 الشركة</span>' ?></td>
                            <td><?= !empty($ce['send_reminder']) ? '🔔' : '🔕' ?></td>
                            <td><?= e($ce['creator_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($canDeleteRow): ?>
                                <form method="post" action="<?= route('/calendar/events/' . $ce['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا الحدث؟');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm">حذف</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- العمود الجانبي (يسار): إضافة حدث -->
    <?php if ($canAddEvents): ?>
    <div class="cal-side">
        <div class="card">
            <div class="card-title"><span>➕ إضافة حدث</span></div>
            <form method="post" action="<?= route('/calendar/events') ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label>عنوان الحدث</label>
                    <input type="text" name="title" required maxlength="200" placeholder="مثال: تجديد الرخصة">
                </div>
                <div class="field">
                    <label>التاريخ</label>
                    <input type="date" name="event_date" required value="<?= e($month === date('Y-m') ? date('Y-m-d') : $month . '-01') ?>">
                </div>
                <div class="field">
                    <label>نوع الحدث</label>
                    <select name="scope">
                        <option value="personal">👤 شخصي - يظهر لي فقط</option>
                        <?php if ($canManageEvents): ?>
                            <option value="company">🏢 عام للشركة - يظهر لجميع الموظفين</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$canManageEvents): ?>
                        <p class="hint">الأحداث العامة للشركة يضيفها مدير الشركة.</p>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label>وصف (اختياري)</label>
                    <textarea name="description" rows="2" maxlength="500"></textarea>
                </div>
                <div class="field">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                        <input type="checkbox" name="send_reminder" value="1" checked style="width:auto;">
                        🔔 تنبيه يوم الحدث
                    </label>
                    <p class="hint">الحدث الشخصي يصلك تنبيهه أنت فقط، وحدث الشركة يصل جميع الموظفين.</p>
                </div>
                <button class="btn" type="submit" style="width:100%;justify-content:center;">إضافة الحدث</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
