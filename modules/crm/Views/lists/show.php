<?php $wid = (int) $workspace['id']; $lid = (int) $list['id']; ?>
<div class="page-head">
    <div><h1>📋 <?= e($list['name']) ?></h1>
        <p><?= e($list['description'] ?: 'جهات هذه القائمة داخل مساحة ' . $workspace['name']) ?></p>
    </div>
    <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/lists') ?>">↩ القوائم</a>
</div>

<div class="card">
    <div class="card-title"><span>الجهات (<?= count($rows) ?>)</span></div>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الجهة</th><th>القطاع</th><th>المدينة</th><th>المسؤول</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="ic">📋</div>القائمة فارغة — أضف جهات إليها بالأسفل.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><a href="<?= route('/crm/w/' . $wid . '/orgs/' . $r['organization_id']) ?>"><?= e($r['name']) ?></a></td>
                <td><?= e($r['sector'] ?: '—') ?></td>
                <td><?= e($r['city'] ?: '—') ?></td>
                <td><?= e($r['owner_name'] ?? '—') ?></td>
                <?php if ($canManage): ?>
                <td>
                    <form method="post" action="<?= route('/crm/w/' . $wid . '/lists/' . $lid . '/items') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="organization_id" value="<?= $r['organization_id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit">✕</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($canManage): ?>
    <form method="post" action="<?= route('/crm/w/' . $wid . '/lists/' . $lid . '/items') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px;">
        <?= csrf_field() ?>
        <div class="field" style="margin:0;flex:1;min-width:220px;">
            <label>إضافة جهة</label>
            <select name="organization_id" required>
                <option value="">اختر جهة من المساحة...</option>
                <?php foreach ($allOrgs as $o): ?>
                    <option value="<?= $o['organization_id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm" type="submit">إضافة</button>
    </form>
    <?php endif; ?>
</div>
