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
                <?php if (!empty($task['linked_type']) && !empty($task['linked_label'])): ?>
                    <?php
                    $typeIcons = ['document' => '📄', 'asset' => '📦', 'employee' => '👤'];
                    $linkUrl = \Modules\Tasks\Controllers\TaskController::linkUrl($task['linked_type'], $task['linked_id']);
                    ?>
                    <tr><th>مرتبطة بـ</th><td>
                        <?= $typeIcons[$task['linked_type']] ?? '🔗' ?>
                        <?php if ($linkUrl): ?><a href="<?= $linkUrl ?>"><?= e($task['linked_label']) ?></a><?php else: ?><?= e($task['linked_label']) ?><?php endif; ?>
                    </td></tr>
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
                <?php if (!empty($checklists)): ?>
                    <form method="post" action="<?= route('/tasks/' . $task['id'] . '/apply-checklist') ?>" style="margin-top:8px;display:flex;gap:8px;">
                        <?= csrf_field() ?>
                        <select name="checklist_id" required>
                            <option value="">تطبيق قائمة تحقق جاهزة...</option>
                            <?php foreach ($checklists as $cl): ?><option value="<?= $cl['id'] ?>"><?= e($cl['name']) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline btn-sm" type="submit">تطبيق</button>
                    </form>
                <?php endif; ?>
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
                <div class="field" style="position:relative;">
                    <textarea name="body" id="comment-body" placeholder="أضف ملاحظة... اكتب @ لذكر زميل" required></textarea>
                    <div id="mention-menu" style="display:none;position:absolute;z-index:20;background:var(--card,#fff);border:1px solid var(--border);border-radius:8px;max-height:180px;overflow:auto;box-shadow:0 4px 16px rgba(0,0,0,.12);min-width:180px;"></div>
                </div>
                <p class="hint" style="margin:-4px 0 8px;">اكتب <code>@</code> ثم اسم الزميل لتنبيهه، مثل: <code>@<?= e($companyUsers[0]['name'] ?? 'الاسم') ?></code></p>
                <button class="btn btn-sm" type="submit">إضافة</button>
            </form>
            <script>
            (function () {
                var users = <?= json_encode(array_map(fn ($u) => $u['name'], $companyUsers), JSON_UNESCAPED_UNICODE) ?>;
                var ta = document.getElementById('comment-body');
                var menu = document.getElementById('mention-menu');
                if (!ta || !menu) return;
                function hide() { menu.style.display = 'none'; }
                function tokenBefore() {
                    var v = ta.value.substring(0, ta.selectionStart);
                    var m = v.match(/@([^@\n]*)$/); // آخر @ وما بعده حتى المؤشر
                    return m ? m[1] : null;
                }
                ta.addEventListener('input', function () {
                    var q = tokenBefore();
                    if (q === null) { hide(); return; }
                    var matches = users.filter(function (n) { return q === '' || n.indexOf(q) !== -1; }).slice(0, 6);
                    if (!matches.length) { hide(); return; }
                    menu.innerHTML = '';
                    matches.forEach(function (n) {
                        var item = document.createElement('div');
                        item.textContent = '@' + n;
                        item.style.cssText = 'padding:8px 12px;cursor:pointer;';
                        item.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            var start = ta.selectionStart;
                            var before = ta.value.substring(0, start).replace(/@([^@\n]*)$/, '@' + n + ' ');
                            var after = ta.value.substring(start);
                            ta.value = before + after;
                            ta.focus();
                            ta.selectionStart = ta.selectionEnd = before.length;
                            hide();
                        });
                        menu.appendChild(item);
                    });
                    menu.style.display = 'block';
                });
                ta.addEventListener('blur', function () { setTimeout(hide, 150); });
            })();
            </script>
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
