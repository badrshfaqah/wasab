<?php
use Modules\Documents\Models\Document;

$typeLabels = Document::typeLabels();
$statusLabels = Document::statusLabels();

$docsQuery = function (array $filters, array $overrides = []): string {
    $params = array_merge($filters, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return route('/documents?' . http_build_query($params));
};
?>
<div class="page-head">
    <div><h1>المستندات</h1><p><?= $canManage ? 'كل مستندات الشركة الرسمية.' : 'المستندات التي أنشأتها.' ?></p></div>
    <div style="display:flex;gap:8px;">
        <?php if ($canManage): ?>
            <a class="btn btn-outline" href="<?= route('/documents/templates') ?>">🎨 القوالب</a>
            <a class="btn btn-outline" href="<?= route('/documents/settings') ?>">⚙️ الإعدادات</a>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <a class="btn" href="<?= route('/documents/create') ?>">+ مستند جديد</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= route('/documents') ?>" class="filters-toolbar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <div class="field" style="margin:0;flex:1;min-width:180px;">
            <label>بحث بالعنوان أو الرقم</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اكتب جزءاً من العنوان أو الرقم...">
        </div>
        <div class="field" style="margin:0;min-width:140px;">
            <label>النوع</label>
            <select name="type">
                <option value="">الكل</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>><?= e($typeLabels[$t]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:160px;">
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
            <a class="btn btn-outline btn-sm" href="<?= $docsQuery([]) ?>">مسح الفلاتر</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الرقم</th><th>العنوان</th><th>النوع</th><th>الحالة</th><?php if ($canManage): ?><th>المُنشئ</th><?php endif; ?><th>التاريخ</th></tr></thead>
        <tbody>
        <?php if (!$documents): ?>
            <tr><td colspan="<?= $canManage ? 6 : 5 ?>"><div class="empty-state"><div class="ic">📄</div>لا توجد مستندات مطابقة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($documents as $d): ?>
            <tr>
                <td><?= e($d['number'] ?: '—') ?></td>
                <td><a href="<?= route('/documents/' . $d['id']) ?>"><?= e($d['title']) ?></a></td>
                <td><?= e($typeLabels[$d['type']] ?? $d['type']) ?></td>
                <td><?= status_badge($d['status']) ?></td>
                <?php if ($canManage): ?><td><?= e($d['creator_name'] ?? '-') ?></td><?php endif; ?>
                <td><?= format_date($d['created_at'], 'Y-m-d') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, $docsQuery($filters)) ?>
</div>
