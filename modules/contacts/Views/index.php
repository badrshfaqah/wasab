<?php
$q = fn (array $over = []) => route('/contacts?' . http_build_query(array_filter(array_merge(['tab' => $tab], $filters, $over), fn ($v) => $v !== '' && $v !== null && $v !== 0)));
?>
<div class="page-head">
    <div>
        <h1>جهات الاتصال</h1>
        <p>دليل الشركة المركزي: الجهات والأفراد. الطرف يُسجَّل مرة واحدة، والفرد يرتبط بأكثر من جهة بمسمّى مختلف في كل واحدة.</p>
    </div>
    <?php if ($canCreate): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if (module_active('clients')): ?>
            <a class="btn btn-outline" href="<?= route('/contacts/import-clients') ?>">📥 استيراد العملاء</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= route('/contacts/people/create') ?>">+ فرد</a>
        <a class="btn" href="<?= route('/contacts/orgs/create') ?>">+ جهة</a>
    </div>
    <?php endif; ?>
</div>

<div class="tabs" style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
    <a class="btn btn-sm <?= $tab === 'orgs' ? '' : 'btn-outline' ?>" href="<?= route('/contacts?tab=orgs') ?>">
        🏢 الجهات <span class="badge"><?= (int) $orgsTotal ?></span>
    </a>
    <a class="btn btn-sm <?= $tab === 'people' ? '' : 'btn-outline' ?>" href="<?= route('/contacts?tab=people') ?>">
        👤 الأفراد <span class="badge"><?= (int) $peopleTotal ?></span>
    </a>
</div>

<div class="card">
    <form method="get" action="<?= route('/contacts') ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px;">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="field" style="margin:0;flex:1;min-width:200px;">
            <label>بحث</label>
            <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= $tab === 'orgs' ? 'اسم الجهة، بريد، هاتف، مدينة...' : 'اسم الفرد، مسمّى، جوال، بريد...' ?>">
        </div>
        <?php if ($tab === 'orgs'): ?>
            <div class="field" style="margin:0;min-width:150px;">
                <label>نوع الجهة</label>
                <select name="kind">
                    <option value="">الكل</option>
                    <?php foreach ($kinds as $k): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin:0;min-width:130px;">
                <label>المدينة</label>
                <input type="text" name="city" value="<?= e($filters['city'] ?? '') ?>">
            </div>
        <?php else: ?>
            <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-bottom:8px;">
                <input type="checkbox" name="standalone" value="1" style="width:auto;" <?= !empty($filters['standalone']) ? 'checked' : '' ?>>
                أفراد بلا جهة
            </label>
        <?php endif; ?>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-bottom:8px;">
            <input type="checkbox" name="archived" value="1" style="width:auto;" <?= !empty($filters['archived']) ? 'checked' : '' ?>>
            إظهار المؤرشف
        </label>
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if ($filters): ?><a class="btn btn-outline btn-sm" href="<?= route('/contacts?tab=' . $tab) ?>">مسح</a><?php endif; ?>
    </form>

    <div class="table-wrap">
    <?php if ($tab === 'orgs'): ?>
        <table class="table-cards">
            <thead><tr><th>الجهة</th><th>النوع</th><th>شخص التواصل</th><th>المدينة</th><th>الهاتف</th></tr></thead>
            <tbody>
            <?php if (!$orgs): ?>
                <tr><td colspan="5"><div class="empty-state"><div class="ic">🏢</div>لا جهات مطابقة</div></td></tr>
            <?php endif; ?>
            <?php foreach ($orgs as $o): ?>
                <tr>
                    <td>
                        <a href="<?= route('/contacts/orgs/' . $o['id']) ?>"><strong><?= e($o['name']) ?></strong></a>
                        <?php if ($o['status'] === 'archived'): ?><span class="badge badge-muted">مؤرشفة</span><?php endif; ?>
                        <?php if (!empty($o['sector'])): ?><div class="hint"><?= e($o['sector']) ?></div><?php endif; ?>
                    </td>
                    <td><?= e($o['kind'] ?: '—') ?></td>
                    <td>
                        <?= e($o['primary_person'] ?: '—') ?>
                        <?php if ((int) $o['people_count'] > 1): ?><span class="hint">+<?= (int) $o['people_count'] - 1 ?></span><?php endif; ?>
                    </td>
                    <td><?= e($o['city'] ?: '—') ?></td>
                    <td><?= e($o['phone'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table class="table-cards">
            <thead><tr><th>الفرد</th><th>المسمّى</th><th>الجهات</th><th>الجوال</th><th>البريد</th></tr></thead>
            <tbody>
            <?php if (!$people): ?>
                <tr><td colspan="5"><div class="empty-state"><div class="ic">👤</div>لا أفراد مطابقون</div></td></tr>
            <?php endif; ?>
            <?php foreach ($people as $p): ?>
                <tr>
                    <td>
                        <a href="<?= route('/contacts/people/' . $p['id']) ?>"><strong><?= e($p['full_name']) ?></strong></a>
                        <?php if ($p['status'] === 'archived'): ?><span class="badge badge-muted">مؤرشف</span><?php endif; ?>
                    </td>
                    <td><?= e($p['job_title'] ?: '—') ?></td>
                    <td>
                        <?php if ((int) $p['orgs_count'] === 0): ?>
                            <span class="hint">مستقل</span>
                        <?php else: ?>
                            <?= e($p['main_org']) ?><?php if ((int) $p['orgs_count'] > 1): ?>
                                <span class="badge badge-muted">+<?= (int) $p['orgs_count'] - 1 ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e($p['mobile'] ?: '—') ?></td>
                    <td><?= e($p['email'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
    <?= render_pagination($tab === 'orgs' ? (int) $orgsTotal : (int) $peopleTotal, $perPage, $page, $q()) ?>
</div>
