<?php
$tabs = [
    'mine' => 'مهامي',
    'created' => 'التي أنشأتها',
    'overdue' => 'المتأخرة',
    'approval' => 'تحتاج اعتمادي',
];
if ($canManage) {
    $tabs['all'] = 'جميع المهام';
}

$statusLabels = ['todo' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'in_review' => 'قيد المراجعة', 'done' => 'مكتملة', 'cancelled' => 'ملغاة'];

$boardQuery = function (string $scope, array $filters, array $overrides = []): string {
    $params = array_merge(['scope' => $scope], $filters, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return route('/tasks/board?' . http_build_query($params));
};
?>
<div class="page-head">
    <div><h1>لوحة المهام</h1><p>اسحب البطاقة إلى العمود المناسب لتغيير حالتها مباشرة.</p></div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline" href="<?= route('/tasks?' . http_build_query(array_merge(['scope' => $scope], $filters))) ?>">📋 عرض القائمة</a>
        <?php if (can('tasks.create')): ?>
            <a class="btn" href="<?= route('/tasks/create') ?>">+ مهمة جديدة</a>
        <?php endif; ?>
    </div>
</div>

<div class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="<?= $scope === $key ? 'active' : '' ?>" href="<?= $boardQuery($key, $filters) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="board-wrap">
    <?php foreach ($statuses as $status): ?>
        <?php $items = $columns[$status] ?? []; ?>
        <div class="board-column" data-status="<?= $status ?>">
            <div class="board-column-head">
                <span><?= e($statusLabels[$status]) ?></span>
                <span class="badge badge-muted"><?= count($items) ?></span>
            </div>
            <div class="board-column-body" data-status="<?= $status ?>">
                <?php if (!$items): ?>
                    <div class="board-empty">لا توجد مهام</div>
                <?php endif; ?>
                <?php foreach ($items as $t): ?>
                    <?php $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d') && !in_array($t['status'], ['done', 'cancelled'], true); ?>
                    <a class="board-card" href="<?= route('/tasks/' . $t['id']) ?>" draggable="true" data-id="<?= $t['id'] ?>">
                        <div class="board-card-title"><?= e($t['title']) ?></div>
                        <div class="board-card-meta">
                            <span><?= e($t['assignee_name'] ?? '-') ?></span>
                            <?= status_badge($t['priority']) ?>
                        </div>
                        <?php if ($t['due_date']): ?>
                            <div class="board-card-due<?= $overdue ? ' overdue' : '' ?>">📅 <?= format_date($t['due_date']) ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    var csrfToken = '<?= \App\Core\Csrf::token() ?>';
    var dragged = null;

    document.querySelectorAll('.board-card').forEach(function (card) {
        card.addEventListener('dragstart', function (e) {
            dragged = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('dragging');
            dragged = null;
        });
    });

    document.querySelectorAll('.board-column-body').forEach(function (col) {
        col.addEventListener('dragover', function (e) {
            e.preventDefault();
            col.classList.add('drag-over');
        });
        col.addEventListener('dragleave', function () {
            col.classList.remove('drag-over');
        });
        col.addEventListener('drop', function (e) {
            e.preventDefault();
            col.classList.remove('drag-over');
            if (!dragged) return;

            var newStatus = col.dataset.status;
            var oldStatus = dragged.closest('.board-column-body').dataset.status;
            if (newStatus === oldStatus) return;

            var taskId = dragged.dataset.id;
            var emptyEl = col.querySelector('.board-empty');
            if (emptyEl) emptyEl.remove();
            col.appendChild(dragged);
            updateCounts();

            fetch('<?= route('/tasks') ?>/' + taskId + '/status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'status=' + encodeURIComponent(newStatus) + '&_csrf=' + encodeURIComponent(csrfToken)
            }).then(function (res) {
                if (!res.ok) throw new Error('failed');
                return res.json();
            }).catch(function () {
                alert('تعذر تحديث حالة المهمة، يرجى إعادة تحميل الصفحة.');
                window.location.reload();
            });
        });
    });

    function updateCounts() {
        document.querySelectorAll('.board-column').forEach(function (colWrap) {
            var body = colWrap.querySelector('.board-column-body');
            var count = body.querySelectorAll('.board-card').length;
            colWrap.querySelector('.badge').textContent = count;
            if (count === 0 && !body.querySelector('.board-empty')) {
                var div = document.createElement('div');
                div.className = 'board-empty';
                div.textContent = 'لا توجد مهام';
                body.appendChild(div);
            }
        });
    }
})();
</script>
