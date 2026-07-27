<?php
$statusLabels = ['todo' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'in_review' => 'قيد المراجعة'];
?>
<div class="page-head">
    <div>
        <h1>المتابعة اليومية</h1>
        <p><?= $entry ? '✓ سجلت متابعة اليوم - تقدر تعدلها حتى نهاية اليوم.' : 'دقيقتان تكفي: ماذا أنجزت؟ ماذا ستعمل؟ هل من معوقات؟' ?></p>
    </div>
    <div style="display:flex;gap:8px;">
        <?php if ($canViewTeam): ?>
            <a class="btn btn-outline" href="<?= route('/checkins/team') ?>">👥 لوحة الفريق</a>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <a class="btn btn-outline" href="<?= route('/checkins/settings') ?>">⚙️ الإعدادات</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($canSubmit): ?>
<div class="card" style="max-width:680px;">
    <div class="card-title"><span>📝 متابعة يوم <?= e($today) ?></span></div>
    <form method="post" action="<?= route('/checkins') ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label>✅ ماذا أنجزت؟</label>
            <?php if ($tasksActive && $myDoneTasks): ?>
                <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:8px;">
                    <?php foreach ($myDoneTasks as $t): ?>
                        <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin:0;cursor:pointer;">
                            <input type="checkbox" name="done_tasks[]" value="<?= $t['id'] ?>" <?= in_array($t['id'], $selectedTasks['done']) ? 'checked' : '' ?>>
                            <span><?= e($t['title']) ?> <span class="badge badge-success">مكتملة</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <textarea name="done_text" rows="2" placeholder="وأي إنجازات أخرى خارج المهام..."><?= e($entry['done_text'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label>🎯 ماذا ستعمل اليوم/غداً؟</label>
            <?php if ($tasksActive && $myOpenTasks): ?>
                <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:8px;">
                    <?php foreach ($myOpenTasks as $t): ?>
                        <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin:0;cursor:pointer;">
                            <input type="checkbox" name="planned_tasks[]" value="<?= $t['id'] ?>" <?= in_array($t['id'], $selectedTasks['planned']) ? 'checked' : '' ?>>
                            <span><?= e($t['title']) ?> <span class="badge badge-info"><?= e($statusLabels[$t['status']] ?? $t['status']) ?></span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <textarea name="plan_text" rows="2" placeholder="وأي خطط أخرى..."><?= e($entry['plan_text'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label>🚧 هل عندك معوقات؟ (اختياري - يصل تنبيه فوري لمديرك)</label>
            <textarea name="blockers_text" rows="2" placeholder="أي شيء يعطّل شغلك: بانتظار رد، صلاحية ناقصة، مشكلة تقنية..."><?= e($entry['blockers_text'] ?? '') ?></textarea>
        </div>

        <button class="btn" type="submit"><?= $entry ? 'تحديث متابعة اليوم' : 'تسجيل متابعة اليوم' ?></button>
    </form>
</div>
<?php endif; ?>

<div class="card" style="max-width:680px;">
    <div class="card-title"><span>🗓️ سجلي الأخير</span></div>
    <?php if (!$history): ?>
        <div class="empty-state"><div class="ic">📝</div>لا توجد متابعات سابقة بعد</div>
    <?php else: ?>
        <?php foreach ($history as $h): ?>
            <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <strong><?= e($h['entry_date']) ?></strong>
                    <?php if (trim((string) $h['blockers_text']) !== ''): ?>
                        <span class="badge badge-warning">🚧 معوق<?= $h['blocker_task_id'] ? ' - تحت المعالجة' : '' ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($h['done_text']): ?><div class="hint" style="margin-top:4px;">✅ <?= e(mb_substr($h['done_text'], 0, 120)) ?></div><?php endif; ?>
                <?php if ($h['plan_text']): ?><div class="hint">🎯 <?= e(mb_substr($h['plan_text'], 0, 120)) ?></div><?php endif; ?>
                <?php if ($h['blockers_text']): ?><div class="hint">🚧 <?= e(mb_substr($h['blockers_text'], 0, 120)) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
