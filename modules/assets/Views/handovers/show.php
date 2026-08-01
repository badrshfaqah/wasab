<?php $holderTypeLabels = ['employee' => 'من الملف الوظيفي', 'user' => 'مستخدم بالنظام', 'manual' => 'شخص يدوي']; ?>
<div class="page-head">
    <div>
        <h1>محضر تسليم</h1>
        <p>الحامل: <strong><?= e($handover['holder_name']) ?></strong> · <?= e($holderTypeLabels[$handover['holder_type']] ?? '') ?> · بتاريخ <?= format_date($handover['handover_date']) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/custody/handovers') ?>">← المحاضر</a>
        <a class="btn btn-outline" target="_blank" rel="noopener" href="<?= route('/custody/handovers/' . $handover['id'] . '/print') ?>">🖨️ محضر مطبوع</a>
    </div>
</div>

<?php if (!empty($handover['acknowledged_at'])): ?>
<div class="card" style="border-inline-start:4px solid var(--success);">
    <p style="margin:0;">✅ أقرّ الحامل باستلام هذه العهدة إلكترونياً بتاريخ <?= format_date($handover['acknowledged_at'], 'Y-m-d H:i') ?>.</p>
</div>
<?php endif; ?>

<?php if ($handover['holder_contact']): ?>
<div class="card"><p style="margin:0;">📞 تواصل الحامل: <span dir="ltr"><?= e($handover['holder_contact']) ?></span></p></div>
<?php endif; ?>
<?php if ($handover['notes']): ?>
<div class="card"><div class="card-title"><span>ملاحظات المحضر</span></div><div style="white-space:pre-wrap;"><?= e($handover['notes']) ?></div></div>
<?php endif; ?>

<div class="card">
    <div class="card-title"><span>📦 الأصول المُسلَّمة (<?= count($items) ?>)</span></div>
    <div class="table-wrap">
    <table class="table-cards">
        <thead><tr><th>الأصل</th><th>الرقم التسلسلي</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><a href="<?= route('/custody/' . $it['asset_id']) ?>"><strong><?= e($it['asset_name']) ?></strong></a><?= $it['asset_code'] ? ' <span class="hint">(' . e($it['asset_code']) . ')</span>' : '' ?></td>
                <td dir="ltr" style="text-align:end;"><?= e($it['serial_number'] ?: '—') ?></td>
                <td>
                    <?php if ($it['returned_at']): ?>
                        <span class="badge badge-success">مُرجع</span>
                        <div class="hint"><?= format_date($it['returned_at'], 'Y-m-d') ?><?= $it['return_condition'] ? ' · ' . e($it['return_condition']) : '' ?></div>
                    <?php else: ?>
                        <span class="badge badge-info">بالعهدة</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$it['returned_at'] && $canAssign): ?>
                    <form method="post" action="<?= route('/custody/handovers/items/' . $it['id'] . '/return') ?>" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?= csrf_field() ?>
                        <input type="text" name="return_condition" maxlength="60" placeholder="حالة الإرجاع" style="width:130px;padding:6px 8px;">
                        <button class="btn btn-outline btn-sm" type="submit">↩️ إرجاع</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
