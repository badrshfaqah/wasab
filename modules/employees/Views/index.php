<?php
use Modules\Employees\Models\Employee;

$statusLabels = Employee::statusLabels();

$empQuery = function (array $filters, array $overrides = []): string {
    $params = array_merge($filters, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return route('/employees?' . http_build_query($params));
};
?>
<div class="page-head">
    <div><h1>الملف الوظيفي</h1><p>الملفات الوظيفية لموظفي الشركة.</p></div>
    <?php if ($canCreate): ?>
        <a class="btn" href="<?= route('/employees/create') ?>">+ ملف وظيفي جديد</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= route('/employees') ?>" class="filters-toolbar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <div class="field" style="margin:0;flex:1;min-width:200px;">
            <label>بحث بالاسم أو المسمى أو القسم</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اكتب جزءاً من الاسم...">
        </div>
        <div class="field" style="margin:0;min-width:160px;">
            <label>الحالة</label>
            <select name="status">
                <option value="">الكل</option>
                <?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if ($filters): ?>
            <a class="btn btn-outline btn-sm" href="<?= $empQuery([]) ?>">مسح الفلاتر</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
    <table>
        <thead><tr><th>الاسم</th><th>المسمى الوظيفي</th><th>القسم</th><th>تاريخ الالتحاق</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if (!$employees): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">🪪</div>لا توجد ملفات وظيفية مطابقة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($employees as $emp): ?>
            <tr>
                <td><a href="<?= route('/employees/' . $emp['id']) ?>"><?= e($emp['full_name']) ?></a></td>
                <td><?= e($emp['job_title'] ?: '—') ?></td>
                <td><?= e($emp['department'] ?: '—') ?></td>
                <td><?= format_date($emp['hire_date']) ?></td>
                <td><?= status_badge($emp['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, $empQuery($filters)) ?>
</div>
