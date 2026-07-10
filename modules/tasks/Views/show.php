<?php
$statusLabels = ['todo' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'in_review' => 'قيد المراجعة', 'done' => 'مكتملة', 'cancelled' => 'ملغاة'];
$overdue = $task['due_date'] && $task['due_date'] < date('Y-m-d') && !in_array($task['status'], ['done', 'cancelled'], true);
?>
<div class="page-head">
    <div>
        <h1><?= e($task['title']) ?></h1>
        <p><?= status_badge($task['priority']) ?> <?= status_badge($task['status']) ?>
        <?php if ($overdue): ?><span class="badge badge-danger">متأخرة</span><?php endif; ?>
        <?php if ($task['requires_approval']): ?>
            <span class="badge <?= $task['approved_at'] ? 'badge-success' : 'badge-warning' ?>"><?= $task['approved_at'] ? 'معتمدة' : 'بانتظار الاعتماد' ?></span>
        <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <?php if ($canEdit): ?><a class="btn btn-outline" href="<?= route('/tasks/' . $task['id'] . '/edit') ?>">تعديل</a><?php endif; ?>
        <?php if ($canApprove): ?>
            <form method="post" action="<?= route('/tasks/' . $task['id'] . '/approve') ?>">
                <?= csrf_field() ?>
                <button class="btn" type="submit">اعتماد المهمة</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-title">تفاصيل المهمة</div>
            <p><?= nl2br(e($task['description'] ?: 'لا يوجد وصف.')) ?></p>
            <table style="margin-top:10px;">
                <tr><th>المسؤول</th><td><?= e($task['assignee_name'] ?? '-') ?></td></tr>
                <tr><th>المنشئ</th><td><?= e($task['creator_name'] ?? '-') ?></td></tr>
                <tr><th>تاريخ البداية</th><td><?= format_date($task['start_date']) ?></td></tr>
                <tr><th>تاريخ الاستحقاق</th><td><?= format_date($task['due_date']) ?></td></tr>
                <?php if ($task['requires_approval']): ?>
                <tr><th>المعتمِد</th><td><?= e($task['approver_name'] ?? '-') ?></td></tr>
                <?php endif; ?>
            </table>

            <form method="post" action="<?= route('/tasks/' . $task['id'] . '/status') ?>" style="margin-top:14px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                <?= csrf_field() ?>
                <div class="field" style="margin-bottom:0;flex:1;">
                    <label>تغيير الحالة</label>
                    <select name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $task['status'] === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-sm" type="submit">تحديث</button>
            </form>
        </div>

        <div class="card">
            <?php $subtaskTotal = count($subtasks); $subtaskDone = count(array_filter($subtasks, fn ($s) => $s['is_done'])); ?>
            <div class="card-title">
                <span>القائمة الفرعية</span>
                <?php if ($subtaskTotal > 0): ?><span class="badge badge-muted" id="subtask-progress-badge"><?= $subtaskDone ?>/<?= $subtaskTotal ?></span><?php endif; ?>
            </div>
            <?php if ($subtaskTotal > 0): ?>
                <div class="subtask-progress-bar"><div id="subtask-progress-fill" style="width:<?= round($subtaskDone / $subtaskTotal * 100) ?>%"></div></div>
            <?php endif; ?>
            <div id="subtask-list">
                <?php if (!$subtasks): ?>
                    <p class="hint" id="subtask-empty-hint">لا توجد عناصر بعد.</p>
                <?php endif; ?>
                <?php foreach ($subtasks as $s): ?>
                    <div class="subtask-item" data-subtask-id="<?= $s['id'] ?>">
                        <label style="display:flex;align-items:center;gap:8px;flex:1;font-weight:400;cursor:pointer;">
                            <input type="checkbox" class="subtask-toggle" data-subtask-id="<?= $s['id'] ?>" <?= $s['is_done'] ? 'checked' : '' ?> <?= $canManageSubtasks ? '' : 'disabled' ?> style="width:auto;">
                            <span class="subtask-title<?= $s['is_done'] ? ' done' : '' ?>"><?= e($s['title']) ?></span>
                        </label>
                        <?php if ($canManageSubtasks): ?>
                            <form method="post" action="<?= route('/tasks/' . $task['id'] . '/subtasks/' . $s['id'] . '/delete') ?>" data-confirm="حذف هذا العنصر؟">
                                <?= csrf_field() ?>
                                <button type="submit" class="subtask-delete" title="حذف">×</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($canManageSubtasks): ?>
                <form method="post" action="<?= route('/tasks/' . $task['id'] . '/subtasks') ?>" style="margin-top:10px;display:flex;gap:8px;">
                    <?= csrf_field() ?>
                    <input type="text" name="title" placeholder="أضف عنصراً جديداً..." required>
                    <button class="btn btn-sm" type="submit">إضافة</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title">المرفقات</div>
            <?php if (!$attachments): ?>
                <p class="hint">لا توجد مرفقات بعد.</p>
            <?php else: ?>
                <?php foreach ($attachments as $a): ?>
                    <div class="notif-item" style="display:flex;justify-content:space-between;">
                        <a href="<?= route('/tasks/' . $task['id'] . '/attachments/' . $a['id']) ?>">📎 <?= e($a['original_name']) ?></a>
                        <span style="color:var(--muted);font-size:12px;"><?= e($a['user_name']) ?> - <?= format_date($a['created_at'], 'Y-m-d') ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <form method="post" action="<?= route('/tasks/' . $task['id'] . '/attachments') ?>" enctype="multipart/form-data" style="margin-top:12px;display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="file" name="file" required>
                <button class="btn btn-sm" type="submit">رفع</button>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-title">الملاحظات</div>
            <?php if (!$comments): ?>
                <p class="hint">لا توجد ملاحظات بعد.</p>
            <?php else: ?>
                <?php foreach ($comments as $c): ?>
                    <div class="notif-item">
                        <strong><?= e($c['user_name']) ?></strong>
                        <span style="color:var(--muted);font-size:12px;"> - <?= format_date($c['created_at'], 'Y-m-d H:i') ?></span>
                        <div style="margin-top:4px;"><?= nl2br(e($c['body'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <form method="post" action="<?= route('/tasks/' . $task['id'] . '/comments') ?>" style="margin-top:12px;">
                <?= csrf_field() ?>
                <div class="field"><textarea name="body" placeholder="أضف ملاحظة..." required></textarea></div>
                <button class="btn btn-sm" type="submit">إضافة</button>
            </form>
        </div>

        <div class="card">
            <div class="card-title">سجل التغييرات</div>
            <?php if (!$logs): ?>
                <p class="hint">لا يوجد سجل بعد.</p>
            <?php else: ?>
                <?php foreach ($logs as $l): ?>
                    <div class="notif-item">
                        <span style="color:var(--muted);font-size:12px;"><?= format_date($l['created_at'], 'Y-m-d H:i') ?></span> -
                        <?= e($l['description']) ?> (<?= e($l['user_name'] ?? 'النظام') ?>)
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canManageSubtasks): ?>
<script>
(function () {
    var taskId = <?= (int) $task['id'] ?>;
    var csrfToken = '<?= \App\Core\Csrf::token() ?>';

    document.querySelectorAll('.subtask-toggle').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var subtaskId = checkbox.dataset.subtaskId;
            checkbox.disabled = true;

            fetch('<?= route('/tasks') ?>/' + taskId + '/subtasks/' + subtaskId + '/toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: '_csrf=' + encodeURIComponent(csrfToken)
            }).then(function (res) {
                if (!res.ok) throw new Error('failed');
                return res.json();
            }).then(function (data) {
                var titleSpan = checkbox.closest('.subtask-item').querySelector('.subtask-title');
                titleSpan.classList.toggle('done', data.done);

                var badge = document.getElementById('subtask-progress-badge');
                var fill = document.getElementById('subtask-progress-fill');
                if (badge) badge.textContent = data.progress.done + '/' + data.progress.total;
                if (fill) fill.style.width = Math.round((data.progress.done / data.progress.total) * 100) + '%';
            }).catch(function () {
                checkbox.checked = !checkbox.checked;
                alert('تعذر تحديث العنصر، حاول مرة أخرى.');
            }).finally(function () {
                checkbox.disabled = false;
            });
        });
    });
})();
</script>
<?php endif; ?>
