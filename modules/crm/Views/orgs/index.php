<?php
$wid = (int) $workspace['id'];
$q = fn (array $over = []) => route('/crm/w/' . $wid . '?' . http_build_query(array_filter(array_merge($filters, $over), fn ($v) => $v !== '' && $v !== null && $v !== 0)));
?>
<div class="page-head">
    <div>
        <h1><span style="font-size:.9em;"><?= e($workspace['icon']) ?></span> <?= e($workspace['name']) ?></h1>
        <p><?= e($workspace['description'] ?: 'جهات هذه المساحة وعلاقاتها.') ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($canSettings): ?>
            <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/members') ?>">👥 الأعضاء</a>
            <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/edit') ?>">⚙️ إعدادات</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/opportunities') ?>">💼 الفرص</a>
        <a class="btn btn-outline" href="<?= route('/crm') ?>">↩ المساحات</a>
        <?php if ($canCreate): ?>
            <a class="btn" href="<?= route('/crm/w/' . $wid . '/orgs/create') ?>">+ إضافة جهة</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= route('/crm/w/' . $wid) ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <div class="field" style="margin:0;flex:1;min-width:180px;">
            <label>بحث</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="اسم الجهة، بريد، هاتف، مدينة...">
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>التصنيف</label>
            <select name="category">
                <option value="">الكل</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int) ($filters['category'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>المسؤول</label>
            <select name="owner">
                <option value="">الكل</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= $m['user_id'] ?>" <?= (int) ($filters['owner'] ?? 0) === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['user_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-bottom:8px;">
            <input type="checkbox" name="due" value="1" style="width:auto;" <?= !empty($filters['due']) ? 'checked' : '' ?>>
            متابعات مستحقة
        </label>
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if ($filters): ?><a class="btn btn-outline btn-sm" href="<?= route('/crm/w/' . $wid) ?>">مسح</a><?php endif; ?>
    </form>

    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الجهة</th><th>التصنيف</th><th>المسؤول</th><th>المدينة</th><th>آخر تواصل</th><th>المتابعة القادمة</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="ic">🏢</div>لا توجد جهات مطابقة في هذه المساحة</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <?php $overdue = !empty($r['next_action_at']) && $r['next_action_at'] < date('Y-m-d H:i:s'); ?>
            <tr>
                <td>
                    <a href="<?= route('/crm/w/' . $wid . '/orgs/' . $r['organization_id']) ?>"><strong><?= e($r['name']) ?></strong></a>
                    <?php if (!empty($r['sector'])): ?><div class="hint"><?= e($r['sector']) ?></div><?php endif; ?>
                </td>
                <td><?= $r['categories'] ? e($r['categories']) : '<span class="hint">—</span>' ?></td>
                <td><?= e($r['owner_name'] ?? '—') ?></td>
                <td><?= e($r['city'] ?: '—') ?></td>
                <td><?= $r['last_activity_at'] ? format_date($r['last_activity_at'], 'Y-m-d') : '<span class="hint">لم يبدأ</span>' ?></td>
                <td>
                    <?php if (!empty($r['next_action_at'])): ?>
                        <span class="badge badge-<?= $overdue ? 'danger' : 'info' ?>"><?= format_date($r['next_action_at'], 'Y-m-d') ?></span>
                    <?php else: ?><span class="hint">—</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= render_pagination($total, $perPage, $page, $q()) ?>
</div>
