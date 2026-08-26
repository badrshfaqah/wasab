<div class="page-head">
    <div><h1>دليل الجهات المركزي</h1><p>كل جهات الشركة (<?= (int) $total ?>) — الجهة مسجّلة مرة واحدة وتظهر في المساحات المرتبطة بها.</p></div>
    <a class="btn btn-outline" href="<?= route('/crm') ?>">↩ المساحات</a>
</div>
<div class="card">
    <form method="get" action="<?= route('/crm/directory') ?>" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="ابحث بالاسم أو البريد أو الهاتف..." style="flex:1;min-width:220px;">
        <button class="btn btn-sm" type="submit">بحث</button>
        <?php if ($q !== ''): ?><a class="btn btn-outline btn-sm" href="<?= route('/crm/directory') ?>">مسح</a><?php endif; ?>
    </form>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الجهة</th><th>القطاع</th><th>المدينة</th><th>المساحات المرتبطة</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="4"><div class="empty-state"><div class="ic">🏛️</div>لا توجد جهات بعد</div></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong><?php if (!empty($r['trade_name'])): ?><div class="hint"><?= e($r['trade_name']) ?></div><?php endif; ?></td>
                <td><?= e($r['sector'] ?: '—') ?></td>
                <td><?= e($r['city'] ?: '—') ?></td>
                <td>
                    <?php if (empty($r['spaces'])): ?><span class="hint">غير مرتبطة بمساحة</span><?php endif; ?>
                    <?php foreach ($r['spaces'] as $s): ?>
                        <a class="badge badge-muted" style="margin-inline-end:4px;" href="<?= route('/crm/w/' . $s['workspace_id'] . '/orgs/' . $r['id']) ?>"><?= e($s['icon']) ?> <?= e($s['workspace_name']) ?></a>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
