<?php
$submitted = array_filter($rows, fn ($r) => $r['entry_id']);
$pending = array_filter($rows, fn ($r) => !$r['entry_id']);
$withBlockers = array_filter($submitted, fn ($r) => trim((string) $r['blockers_text']) !== '');
?>
<div class="page-head">
    <div>
        <h1>متابعات الفريق</h1>
        <p>يوم <?= e($date) ?> · سجّل <strong><?= count($submitted) ?></strong> من <?= count($rows) ?><?= $withBlockers ? ' · <strong style="color:var(--warning);">' . count($withBlockers) . ' معوقات</strong>' : '' ?></p>
    </div>
    <form method="get" action="<?= route('/checkins/team') ?>" style="display:flex;gap:8px;align-items:center;">
        <input type="date" name="date" value="<?= e($date) ?>" style="width:auto;">
        <button class="btn btn-sm" type="submit">عرض</button>
    </form>
</div>

<?php if ($withBlockers): ?>
<div class="card" style="border-right:4px solid var(--warning);">
    <div class="card-title"><span>🚧 المعوقات أولاً</span></div>
    <?php foreach ($withBlockers as $r): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
                <div>
                    <strong><?= e($r['user_name']) ?></strong>
                    <div style="margin-top:4px;"><?= nl2br(e($r['blockers_text'])) ?></div>
                </div>
                <?php if ($r['blocker_task_id']): ?>
                    <a class="badge badge-success" href="<?= route('/tasks/' . $r['blocker_task_id']) ?>">✓ تحت المعالجة - عرض المهمة</a>
                <?php elseif ($tasksActive): ?>
                    <form method="post" action="<?= route('/checkins/blocker-to-task') ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entry_id" value="<?= $r['entry_id'] ?>">
                        <select name="assignee_id" style="width:auto;min-width:140px;">
                            <?php foreach ($companyUsers as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm" type="submit">تحويل لمهمة</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($pending): ?>
<div class="card">
    <div class="card-title"><span>⏳ لم يسجلوا بعد (<?= count($pending) ?>)</span></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php foreach ($pending as $r): ?>
            <span class="badge badge-muted"><?= e($r['user_name']) ?></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title"><span>✅ المتابعات المسجلة</span></div>
    <?php if (!$submitted): ?>
        <div class="empty-state"><div class="ic">📝</div>لا متابعات مسجلة لهذا اليوم بعد</div>
    <?php endif; ?>
    <?php foreach ($submitted as $r): ?>
        <?php $tasks = $entryTasks[(int) $r['entry_id']] ?? []; ?>
        <details style="padding:8px 0;border-bottom:1px solid var(--border);">
            <summary style="cursor:pointer;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;list-style:none;">
                <strong><?= e($r['user_name']) ?></strong>
                <span class="hint">سجّل <?= time_ago($r['submitted_at']) ?><?= trim((string) $r['blockers_text']) !== '' ? ' · 🚧' : '' ?></span>
            </summary>
            <div style="padding:8px 4px 4px;">
                <div style="margin-bottom:6px;"><strong>✅ أنجز:</strong>
                    <?php if (!empty($tasks['done'])): ?>
                        <?php foreach ($tasks['done'] as $t): ?><span class="badge badge-success"><?= e($t['title'] ?? ('مهمة #' . $t['task_id'])) ?></span> <?php endforeach; ?>
                    <?php endif; ?>
                    <?= $r['done_text'] ? '<div class="hint">' . nl2br(e($r['done_text'])) . '</div>' : (empty($tasks['done']) ? '<span class="hint">-</span>' : '') ?>
                </div>
                <div style="margin-bottom:6px;"><strong>🎯 خطته:</strong>
                    <?php if (!empty($tasks['planned'])): ?>
                        <?php foreach ($tasks['planned'] as $t): ?><span class="badge badge-info"><?= e($t['title'] ?? ('مهمة #' . $t['task_id'])) ?></span> <?php endforeach; ?>
                    <?php endif; ?>
                    <?= $r['plan_text'] ? '<div class="hint">' . nl2br(e($r['plan_text'])) . '</div>' : (empty($tasks['planned']) ? '<span class="hint">-</span>' : '') ?>
                </div>
            </div>
        </details>
    <?php endforeach; ?>
</div>
