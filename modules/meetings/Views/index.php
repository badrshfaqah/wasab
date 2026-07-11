<?php
use Modules\Meetings\Models\Meeting;

$statusLabels = Meeting::statusLabels();
$typeLabels = Meeting::typeLabels();

$meetingsQuery = function (array $filters, array $overrides = []) {
    $params = array_merge($filters, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return route('/meetings?' . http_build_query($params));
};
?>
<div class="page-head">
    <div><h1>الاجتماعات</h1><p><?= $canManage ? 'كل اجتماعات الشركة.' : 'اجتماعاتك: التي أنشأتها أو أنت مدعو لها.' ?></p></div>
    <?php if ($canCreate): ?>
        <a class="btn" href="<?= route('/meetings/create') ?>">+ اجتماع جديد</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= route('/meetings') ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <div class="field" style="margin:0;flex:1;min-width:200px;">
            <label>بحث بعنوان الاجتماع</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اكتب جزءاً من العنوان...">
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
            <a class="btn btn-outline btn-sm" href="<?= $meetingsQuery([]) ?>">مسح الفلاتر</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
    <table>
        <thead><tr><th>العنوان</th><th>النوع</th><th>الموعد</th><th>المكان</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if (!$meetings): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">🗓️</div>لا توجد اجتماعات مطابقة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($meetings as $m): ?>
            <tr>
                <td><a href="<?= route('/meetings/' . $m['id']) ?>"><?= e($m['title']) ?></a></td>
                <td><?= e($typeLabels[$m['type']] ?? $m['type']) ?></td>
                <td><?= format_date($m['starts_at'], 'Y-m-d H:i') ?></td>
                <td><?= e($m['location'] ?: '—') ?></td>
                <td><?= status_badge($m['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, $meetingsQuery($filters)) ?>
</div>
