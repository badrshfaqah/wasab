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
?>
<div class="page-head">
    <div><h1>المهام</h1><p>متابعة المهام المسندة إليك والتي أنشأتها.</p></div>
    <?php if (can('tasks.create')): ?>
        <a class="btn" href="<?= route('/tasks/create') ?>">+ مهمة جديدة</a>
    <?php endif; ?>
</div>

<div class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="<?= $scope === $key ? 'active' : '' ?>" href="<?= route('/tasks?scope=' . $key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>العنوان</th><th>المسؤول</th><th>الأولوية</th><th>الحالة</th><th>تاريخ الاستحقاق</th></tr></thead>
        <tbody>
        <?php if (!$tasks): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">📋</div>لا توجد مهام هنا حالياً</div></td></tr>
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
    <?= render_pagination($total, $perPage, $page, route('/tasks?scope=' . $scope)) ?>
</div>
