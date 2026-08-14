<?php
/** @var array $sections */
$stBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
$taskSt = ['todo' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'in_review' => 'قيد المراجعة'];
$leaveTypes = ['annual' => 'سنوية', 'sick' => 'مرضية', 'hours' => 'إذن ساعات', 'unpaid' => 'بدون راتب', 'other' => 'أخرى'];
$leaveSt = ['pending' => 'بالانتظار', 'approved' => 'معتمدة', 'rejected' => 'مرفوضة'];
?>
<div class="page-head">
    <div><h1>ملفي</h1><p>كل ما يخصّك في مكان واحد — من الإضافات المفعّلة.</p></div>
    <a class="btn btn-outline" href="<?= route('/profile') ?>">⚙️ ملفي الشخصي</a>
</div>

<?php if (!$sections): ?>
    <div class="card"><div class="empty-state"><div class="ic">👤</div><h3>لا بيانات لعرضها بعد</h3><p>ستظهر هنا مهامك وعهدك وإجازاتك وخطاباتك حال توفرها.</p></div></div>
<?php endif; ?>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));align-items:start;">

<?php if (!empty($sections['attendance'])): $a = $sections['attendance']; ?>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title divided"><span>🕐 حضوري</span><a class="btn btn-ghost btn-sm" href="<?= route('/checkins/attendance') ?>">السجل</a></div>
        <p style="margin:0 0 6px;">
            <?php if (empty($a['today'])): ?>لم تسجّل حضورك اليوم — <a href="<?= route('/checkins') ?>">سجّل الآن</a>
            <?php elseif (empty($a['today']['out_at'])): ?>حضرت الساعة <strong><?= format_date($a['today']['in_at'], 'H:i') ?></strong> 💪
            <?php else: ?>حضرت <?= format_date($a['today']['in_at'], 'H:i') ?> وانصرفت <?= format_date($a['today']['out_at'], 'H:i') ?>.<?php endif; ?>
        </p>
        <p class="hint" style="margin:0;">أيام حضورك هذا الشهر: <strong><?= (int) $a['monthDays'] ?></strong></p>
    </div>
<?php endif; ?>

<?php if (!empty($sections['leaves'])): $lv = $sections['leaves']; ?>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title divided"><span>🌴 إجازاتي</span><a class="btn btn-ghost btn-sm" href="<?= route('/employees/leaves/request') ?>">+ طلب</a></div>
        <p style="margin:0 0 8px;">رصيدك السنوي: <strong><?= (int) $lv['balance'] ?> يوم</strong></p>
        <?php if (!$lv['rows']): ?><p class="hint" style="margin:0;">لا طلبات بعد.</p><?php endif; ?>
        <?php foreach ($lv['rows'] as $l): ?>
            <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-top:1px solid var(--border);font-size:13px;">
                <span><?= e($leaveTypes[$l['type']] ?? $l['type']) ?> · <?= format_date($l['start_date']) ?></span>
                <span class="badge badge-<?= $stBadge[$l['status']] ?? 'muted' ?>"><?= e($leaveSt[$l['status']] ?? $l['status']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($sections['tasks'])): $t = $sections['tasks']; ?>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title divided"><span>📋 مهامي المفتوحة<?= $t['late'] > 0 ? ' <span class="badge badge-danger">' . (int) $t['late'] . ' متأخرة</span>' : '' ?></span><a class="btn btn-ghost btn-sm" href="<?= route('/tasks') ?>">الكل</a></div>
        <?php if (!$t['rows']): ?><p class="hint" style="margin:0;">لا مهام مفتوحة 🎉</p><?php endif; ?>
        <?php foreach ($t['rows'] as $task): ?>
            <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-top:1px solid var(--border);font-size:13px;">
                <a href="<?= route('/tasks/' . $task['id']) ?>" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($task['title']) ?></a>
                <span class="hint" style="white-space:nowrap;"><?= $task['due_date'] ? format_date($task['due_date'], 'm-d') : e($taskSt[$task['status']] ?? '') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($sections['assets'])): $as = $sections['assets']; ?>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title divided"><span>📦 عهدي (<?= count($as['rows']) ?>)</span><a class="btn btn-ghost btn-sm" href="<?= route('/custody/my') ?>">التفاصيل</a></div>
        <?php if (!$as['rows']): ?><p class="hint" style="margin:0;">لا عهد مسندة إليك.</p><?php endif; ?>
        <?php foreach (array_slice($as['rows'], 0, 5) as $a): ?>
            <div style="padding:6px 0;border-top:1px solid var(--border);font-size:13px;">
                <?= e($a['name']) ?><?= $a['asset_code'] ? ' <span class="hint">(' . e($a['asset_code']) . ')</span>' : '' ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($sections['meetings'])): $mt = $sections['meetings']; ?>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title divided"><span>📅 اجتماعاتي القادمة</span><a class="btn btn-ghost btn-sm" href="<?= route('/meetings') ?>">الكل</a></div>
        <?php if (!$mt['rows']): ?><p class="hint" style="margin:0;">لا اجتماعات قادمة.</p><?php endif; ?>
        <?php foreach ($mt['rows'] as $m): ?>
            <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-top:1px solid var(--border);font-size:13px;">
                <a href="<?= route('/meetings/' . $m['id']) ?>" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($m['title']) ?></a>
                <span class="hint" style="white-space:nowrap;"><?= format_date($m['starts_at'], 'm-d H:i') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($sections['letters'])): $lt = $sections['letters']; ?>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title divided"><span>📨 خطاباتي</span></div>
        <?php if (!$lt['rows']): ?><p class="hint" style="margin:0;">لا خطابات صادرة باسمك.</p><?php endif; ?>
        <?php foreach ($lt['rows'] as $l): ?>
            <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-top:1px solid var(--border);font-size:13px;">
                <a href="<?= route('/forms/' . $l['id']) ?>"><?= e($l['title']) ?></a>
                <span class="hint"><?= e($l['number'] ?? '') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>
