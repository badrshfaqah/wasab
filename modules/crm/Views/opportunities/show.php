<?php
$wid = (int) $workspace['id'];
$oid = (int) $opportunity['id'];
$statusLabels = ['open' => 'مفتوحة', 'won' => 'تم الاتفاق', 'lost' => 'لم تكتمل'];
$statusClass = ['open' => 'info', 'won' => 'success', 'lost' => 'danger'];
?>
<div class="page-head">
    <div>
        <h1><?= e($opportunity['name']) ?></h1>
        <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="<?= route('/crm/w/' . $wid . '/orgs/' . $opportunity['organization_id']) ?>">🏢 <?= e($organization['name'] ?? '') ?></a>
            <span class="badge badge-<?= $statusClass[$opportunity['status']] ?>"><?= e($statusLabels[$opportunity['status']]) ?></span>
            <?php if (!empty($opportunity['value'])): ?><span class="badge badge-muted"><?= number_format((float) $opportunity['value'], 0) ?></span><?php endif; ?>
        </p>
    </div>
    <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/opportunities') ?>">↩ اللوحة</a>
</div>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>💼 تفاصيل الفرصة</span></div>
        <table class="table-cards"><tbody>
            <tr><td style="width:140px;color:var(--muted);">المسؤول</td><td><?= e(array_column($members, 'user_name', 'user_id')[$opportunity['owner_id']] ?? '—') ?></td></tr>
            <tr><td style="color:var(--muted);">الإغلاق المتوقع</td><td><?= $opportunity['expected_close_date'] ? format_date($opportunity['expected_close_date'], 'Y-m-d') : '—' ?></td></tr>
            <tr><td style="color:var(--muted);">الاحتمالية</td><td><?= $opportunity['probability'] !== null ? (int) $opportunity['probability'] . '%' : '—' ?></td></tr>
            <tr><td style="color:var(--muted);">المصدر</td><td><?= e($opportunity['source'] ?: '—') ?></td></tr>
            <tr><td style="color:var(--muted);">أُنشئت</td><td><?= format_date($opportunity['created_at'], 'Y-m-d') ?></td></tr>
            <?php if (!empty($opportunity['closed_at'])): ?>
                <tr><td style="color:var(--muted);">أُغلقت</td><td><?= format_date($opportunity['closed_at'], 'Y-m-d') ?></td></tr>
            <?php endif; ?>
        </tbody></table>
        <?php if (!empty($opportunity['description'])): ?>
            <p style="margin-top:10px;white-space:pre-wrap;"><?= e($opportunity['description']) ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>🪜 المرحلة</span></div>
        <?php if ($canEdit): ?>
        <form method="post" action="<?= route('/crm/w/' . $wid . '/opportunities/' . $oid . '/move') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <?= csrf_field() ?>
            <div class="field" style="margin:0;flex:1;min-width:160px;">
                <label>نقل إلى</label>
                <select name="stage_id">
                    <?php foreach ($stages as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int) $opportunity['stage_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-sm" type="submit">نقل</button>
        </form>
        <p class="hint" style="margin-top:8px;">المراحل النهائية تُغلق الفرصة تلقائياً (تم الاتفاق / لم يكتمل)، وكل نقلة تُسجَّل في سجل علاقة الجهة.</p>
        <?php else: ?>
            <p class="hint" style="margin-top:0;">لا تملك صلاحية تعديل الفرص في هذه المساحة.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>🕘 أنشطة الفرصة (<?= count($activities) ?>)</span></div>
        <?php if (!$activities): ?><p class="hint" style="margin-top:0;">لا أنشطة مرتبطة بهذه الفرصة بعد.</p><?php endif; ?>
        <?php foreach ($activities as $a): ?>
            <div class="doc-log">
                <div><?= \Modules\Crm\Models\Activity::typeIcon($a['type']) ?> <?= e($a['subject'] ?: \Modules\Crm\Models\Activity::typeLabel($a['type'])) ?></div>
                <?php if (!empty($a['body'])): ?><div class="hint" style="white-space:pre-wrap;"><?= e($a['body']) ?></div><?php endif; ?>
                <div class="doc-log-meta"><?= e($a['user_name'] ?? '') ?> · <?= format_date($a['occurred_at'], 'Y-m-d H:i') ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>📋 سجل التغييرات</span></div>
        <?php foreach ($logs as $log): ?>
            <div class="doc-log">
                <div><?= e($log['description']) ?></div>
                <div class="doc-log-meta"><?= e($log['user_name'] ?? '') ?> · <?= format_date($log['created_at'], 'Y-m-d H:i') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<div class="card">
    <div class="card-title"><span>✏️ تعديل الفرصة</span></div>
    <?php
    $selectedOrg = (int) $opportunity['organization_id'];
    require __DIR__ . '/_edit_fields.php';
    ?>
</div>
<?php endif; ?>
