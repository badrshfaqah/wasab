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

$priorityLabels = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'urgent' => 'عاجلة'];
$statusLabels = ['todo' => 'لم تبدأ', 'in_progress' => 'قيد التنفيذ', 'in_review' => 'قيد المراجعة', 'done' => 'مكتملة', 'cancelled' => 'ملغاة'];

$tasksQuery = function (string $scope, array $filters, array $overrides = []): string {
    $params = array_merge(['scope' => $scope], $filters, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return route('/tasks?' . http_build_query($params));
};
?>
<div class="page-head">
    <div><h1>المهام</h1><p>متابعة المهام المسندة إليك والتي أنشأتها.</p></div>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline" href="<?= route('/tasks/board?' . http_build_query(array_merge(['scope' => $scope], $filters))) ?>">🗂️ عرض كانبان</a>
        <?php if (can('tasks.create')): ?>
            <a class="btn" href="<?= route('/tasks/create') ?>">+ مهمة جديدة</a>
        <?php endif; ?>
    </div>
</div>

<div class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="<?= $scope === $key ? 'active' : '' ?>" href="<?= $tasksQuery($key, $filters) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <form method="get" action="<?= route('/tasks') ?>" class="filters-toolbar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <input type="hidden" name="scope" value="<?= e($scope) ?>">
        <div class="field" style="margin:0;flex:1;min-width:180px;">
            <label>بحث بالعنوان</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اكتب جزءاً من العنوان...">
        </div>
        <div class="field" style="margin:0;min-width:160px;">
            <label>المسؤول</label>
            <select name="assignee_id">
                <option value="">الكل</option>
                <?php foreach ($companyUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int) ($filters['assignee_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:140px;">
            <label>الأولوية</label>
            <select name="priority">
                <option value="">الكل</option>
                <?php foreach ($priorities as $p): ?>
                    <option value="<?= $p ?>" <?= ($filters['priority'] ?? '') === $p ? 'selected' : '' ?>><?= e($priorityLabels[$p]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:140px;">
            <label>الحالة</label>
            <select name="status">
                <option value="">الكل</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if ($filters): ?>
            <a class="btn btn-outline btn-sm" href="<?= $tasksQuery($scope, []) ?>">مسح الفلاتر</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
    <table>
        <thead><tr><th>العنوان</th><th>المسؤول</th><th>الأولوية</th><th>الحالة</th><th>تاريخ الاستحقاق</th></tr></thead>
        <tbody>
        <?php if (!$tasks): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">📋</div>لا توجد مهام مطابقة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($tasks as $t): ?>
            <?php $overdue = $t['due_date'] && $t['due_date'] < date('Y-m-d') && !in_array($t['status'], ['done', 'cancelled'], true); ?>
            <tr>
                <td><a href="<?= route('/tasks/' . $t['id']) ?>"><?= e($t['title']) ?></a></td>
                <td><?= e($t['assignee_name'] ?? '-') ?></td>
                <td><?= status_badge($t['priority']) ?></td>
                <td><?= status_badge($t['status']) ?></td>
                <td<?= $overdue ? ' style="color:var(--danger);font-weight:700;"' : '' ?>><?= format_date($t['due_date']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, $tasksQuery($scope, $filters)) ?>
</div>
