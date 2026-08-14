<?php $typeLabels = ['company' => 'شركة', 'person' => 'فرد']; ?>
<div class="page-head">
    <div><h1><?= e($client['name']) ?> <span class="badge badge-muted"><?= e($typeLabels[$client['type']] ?? '') ?></span></h1></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/clients') ?>">← العملاء</a>
        <?php if ($canEdit): ?><a class="btn btn-outline" href="<?= route('/clients/' . $client['id'] . '/edit') ?>">تعديل</a><?php endif; ?>
        <?php if ($canDelete): ?>
        <form method="post" action="<?= route('/clients/' . $client['id'] . '/archive') ?>" onsubmit="return confirm('أرشفة هذا العميل؟ يختفي من القائمة ويبقى محفوظاً.');">
            <?= csrf_field() ?><button class="btn btn-outline" type="submit">🗃️ أرشفة</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>البيانات</span></div>
        <div class="table-wrap"><table><tbody>
            <tr><th style="width:130px;">المسؤول</th><td><?= e($client['contact_name'] ?: '—') ?></td></tr>
            <tr><th>الهاتف</th><td dir="ltr" style="text-align:end;"><?= e($client['phone'] ?: '—') ?></td></tr>
            <tr><th>البريد</th><td dir="ltr" style="text-align:end;"><?= e($client['email'] ?: '—') ?></td></tr>
            <tr><th>العنوان</th><td><?= e($client['address'] ?: '—') ?></td></tr>
            <?php if ($client['notes']): ?><tr><th>ملاحظات</th><td style="white-space:pre-wrap;"><?= e($client['notes']) ?></td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="card">
        <div class="card-title"><span>🔗 المرتبط بهذا العميل</span></div>
        <?php if (!$linkedTasks && !$linkedFiles): ?>
            <p class="hint" style="margin:0;">لا شيء مرتبط بعد — اربط مهمة (من نموذج المهمة) أو ملفاً مؤرشفاً (من نموذج الرفع) بهذا العميل.</p>
        <?php endif; ?>
        <?php foreach ($linkedTasks as $t): ?>
            <div style="padding:6px 0;border-top:1px solid var(--border);font-size:13px;">📋 <a href="<?= route('/tasks/' . $t['id']) ?>"><?= e($t['title']) ?></a></div>
        <?php endforeach; ?>
        <?php foreach ($linkedFiles as $f): ?>
            <div style="padding:6px 0;border-top:1px solid var(--border);font-size:13px;">🗂️ <a href="<?= route('/archive/' . $f['id']) ?>"><?= e($f['title']) ?></a></div>
        <?php endforeach; ?>
    </div>
</div>
