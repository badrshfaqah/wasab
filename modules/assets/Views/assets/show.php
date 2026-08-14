<?php
$badgeColor = ['available' => 'success', 'assigned' => 'info', 'maintenance' => 'warning', 'retired' => 'muted', 'lost' => 'danger'];
$actionLabels = ['created' => 'تسجيل', 'updated' => 'تعديل', 'assigned' => 'تسليم عهدة', 'returned' => 'إرجاع', 'transferred' => 'نقل عهدة', 'status_changed' => 'تغيير حالة'];
?>
<div class="page-head">
    <div>
        <h1><?= e($asset['name']) ?> <span class="badge badge-<?= $badgeColor[$asset['status']] ?? 'muted' ?>"><?= e($statusLabels[$asset['status']] ?? $asset['status']) ?></span></h1>
        <p><?= e($asset['category_name'] ?? 'بلا تصنيف') ?><?= $asset['asset_code'] ? ' · ' . e($asset['asset_code']) : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/custody') ?>">← القائمة</a>
        <?php if ($canEdit): ?><a class="btn btn-outline" href="<?= route('/custody/' . $asset['id'] . '/edit') ?>">تعديل</a><?php endif; ?>
        <?php if ($canAssign && $asset['status'] === 'available'): ?>
            <a class="btn" href="<?= route('/custody/handovers/create?asset=' . $asset['id']) ?>">🤝 تسليم عهدة</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($asset['status'] === 'assigned'): ?>
<div class="card" style="border-inline-start:4px solid var(--info);">
    <div class="card-title"><span>🤝 العهدة الحالية</span></div>
    <p style="margin:0;">هذا الأصل بعهدة <strong><?= e($asset['current_holder_name']) ?></strong> منذ <?= format_date($asset['assigned_at'], 'Y-m-d') ?>.</p>
    <?php if ($canAssign): ?>
        <div style="margin-top:12px;">
            <a class="btn btn-outline btn-sm" href="<?= route('/custody/' . $asset['id'] . '/transfer') ?>">🔄 نقل العهدة لحاملٍ آخر</a>
        </div>
    <?php endif; ?>
    <?php if ($openItem && $canAssign): ?>
        <form method="post" action="<?= route('/custody/handovers/items/' . $openItem['id'] . '/return') ?>" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <?= csrf_field() ?>
            <div class="field" style="margin:0;min-width:160px;"><label>حالة الإرجاع</label><input type="text" name="return_condition" maxlength="60" placeholder="سليم / تالف..."></div>
            <div class="field" style="margin:0;flex:1;min-width:180px;"><label>ملاحظة</label><input type="text" name="return_note" maxlength="255"></div>
            <button class="btn" type="submit" onclick="return confirm('تأكيد إرجاع هذا الأصل وإعادته متاحاً؟');">↩️ تسجيل الإرجاع</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-title"><span>بيانات الأصل</span></div>
        <div class="table-wrap"><table><tbody>
            <tr><th style="width:150px;">الرقم التسلسلي</th><td dir="ltr" style="text-align:end;"><?= e($asset['serial_number'] ?: '—') ?></td></tr>
            <tr><th>الحالة المادية</th><td><?= e($asset['condition_note'] ?: '—') ?></td></tr>
            <tr><th>تاريخ الشراء</th><td><?= $asset['purchase_date'] ? format_date($asset['purchase_date']) : '—' ?></td></tr>
            <tr><th>قيمة الشراء</th><td><?= $asset['purchase_cost'] !== null ? number_format((float) $asset['purchase_cost'], 2) : '—' ?></td></tr>
            <tr><th>انتهاء الضمان</th><td><?= $asset['warranty_expiry'] ? format_date($asset['warranty_expiry']) : '—' ?></td></tr>
            <?php
            $customValues = !empty($asset['custom_json']) ? (json_decode((string) $asset['custom_json'], true) ?: []) : [];
            foreach ($customValues as $cfName => $cfVal): ?>
                <tr><th><?= e($cfName) ?></th><td><?= e((string) $cfVal) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($asset['notes']): ?><tr><th>ملاحظات</th><td><?= nl2br(e($asset['notes'])) ?></td></tr><?php endif; ?>
        </tbody></table></div>

        <?php if (($canEdit || $canDelete) && $asset['status'] !== 'assigned'): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
            <?php if ($canEdit): ?>
                <?php foreach (['available' => 'إعادة للإتاحة', 'maintenance' => 'إرسال لصيانة', 'retired' => 'خارج الخدمة', 'lost' => 'مفقود'] as $st => $lbl): ?>
                    <?php if ($asset['status'] !== $st): ?>
                    <form method="post" action="<?= route('/custody/' . $asset['id'] . '/status') ?>">
                        <?= csrf_field() ?><input type="hidden" name="status" value="<?= $st ?>">
                        <button class="btn btn-outline btn-sm" type="submit"><?= $lbl ?></button>
                    </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($canDelete): ?>
            <form method="post" action="<?= route('/custody/' . $asset['id'] . '/delete') ?>" onsubmit="return confirm('حذف هذا الأصل نهائياً؟');">
                <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">حذف الأصل</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>📜 سجل الحركة</span></div>
        <?php if (!$logs): ?>
            <div class="notif-empty">لا حركات مسجّلة.</div>
        <?php else: ?>
            <?php foreach ($logs as $l): ?>
                <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;">
                    <strong><?= e($actionLabels[$l['action']] ?? $l['action']) ?></strong>
                    <?= $l['description'] ? '— ' . e($l['description']) : '' ?>
                    <div class="hint"><?= e($l['user_name'] ?? 'النظام') ?> · <?= format_date($l['created_at'], 'Y-m-d H:i') ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
