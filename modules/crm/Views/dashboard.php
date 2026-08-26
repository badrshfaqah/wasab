<?php
$wid = (int) $workspace['id'];
$o = $stats['orgs'];
$op = $stats['opportunities'];
$money = fn ($v) => $v ? number_format((float) $v, 0) : '—';
$tile = function (string $label, $value, string $color = '', string $url = '') {
    ?>
    <div class="card" style="margin-bottom:0;">
        <div class="hint"><?= $label ?></div>
        <div style="font-size:1.7em;font-weight:700;<?= $color ? 'color:' . $color . ';' : '' ?>">
            <?= $url ? '<a href="' . $url . '" style="color:inherit;">' . $value . '</a>' : $value ?>
        </div>
    </div>
    <?php
};
?>
<div class="page-head">
    <div><h1>لوحة <?= e($workspace['icon']) ?> <?= e($workspace['name']) ?></h1>
        <p>أرقام المساحة للفترة <?= e($stats['from']) ?> ← <?= e($stats['to']) ?>.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid . '/opportunities') ?>">💼 الفرص</a>
        <a class="btn btn-outline" href="<?= route('/crm/w/' . $wid) ?>">🏢 الجهات</a>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= route('/crm/w/' . $wid . '/dashboard') ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div class="field" style="margin:0;"><label>من</label><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="field" style="margin:0;"><label>إلى</label><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>العضو</label>
            <select name="owner">
                <option value="">الكل</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= $m['user_id'] ?>" <?= (int) $filters['owner'] === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['user_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:150px;">
            <label>التصنيف</label>
            <select name="category">
                <option value="">الكل</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int) $filters['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($pipelines) > 1): ?>
        <div class="field" style="margin:0;min-width:150px;">
            <label>المسار</label>
            <select name="pipeline">
                <option value="">الكل</option>
                <?php foreach ($pipelines as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int) $filters['pipeline'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button class="btn btn-sm" type="submit">تطبيق</button>
    </form>
</div>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
    <?php
    $tile('إجمالي الجهات', (int) $o['total'], '', route('/crm/w/' . $wid));
    $tile('جهات جديدة بالفترة', (int) $o['new']);
    $tile('تم التواصل معها', (int) $o['contacted']);
    $tile('لم يبدأ التواصل', (int) $o['untouched'], 'var(--warning)', route('/crm/w/' . $wid));
    ?>
</div>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
    <?php
    $tile('متابعات اليوم', (int) $o['due_today'], 'var(--info)', route('/crm/today'));
    $tile('متابعات متأخرة', (int) $o['overdue'], (int) $o['overdue'] ? 'var(--danger)' : '', route('/crm/today'));
    $tile('جهات خاملة (30 يوماً)', (int) $o['stale'], '', route('/crm/w/' . $wid . '?stale=1'));
    $tile('أنشطة بالفترة', (int) $stats['activities']['total']);
    ?>
</div>

<div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
    <?php
    $tile('فرص مفتوحة', (int) $op['open'], '', route('/crm/w/' . $wid . '/opportunities'));
    $tile('قيمة الفرص المفتوحة', $money($op['open_value']));
    $tile('تم الاتفاق', (int) $op['won'], 'var(--success)');
    $tile('قيمة ما تم', $money($op['won_value']), 'var(--success)');
    ?>
</div>

<div class="grid-2" style="align-items:start;">
    <div class="card">
        <div class="card-title"><span>🪜 الفرص المفتوحة على المراحل</span></div>
        <?php if (!$stats['stages']): ?><p class="hint" style="margin-top:0;">لا مراحل بعد.</p><?php endif; ?>
        <?php
        $maxStage = max(array_map(fn ($s) => (int) $s['c'], $stats['stages'] ?: [['c' => 0]])) ?: 1;
        foreach ($stats['stages'] as $s): ?>
            <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;font-size:.9em;">
                    <span><?= e($s['name']) ?></span><strong><?= (int) $s['c'] ?></strong>
                </div>
                <div style="height:8px;background:var(--border);border-radius:4px;overflow:hidden;margin-top:4px;">
                    <div style="height:100%;width:<?= (int) round(((int) $s['c'] / $maxStage) * 100) ?>%;background:<?= e($s['color']) ?>;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-title"><span>📊 الأنشطة حسب النوع</span></div>
        <?php if (!$stats['activities']['by_type']): ?><p class="hint" style="margin-top:0;">لا أنشطة في هذه الفترة.</p><?php endif; ?>
        <?php foreach ($stats['activities']['by_type'] as $t): ?>
            <div class="doc-log" style="display:flex;justify-content:space-between;">
                <span><?= \Modules\Crm\Models\Activity::typeIcon($t['type']) ?> <?= e(\Modules\Crm\Models\Activity::typeLabel($t['type'])) ?></span>
                <strong><?= (int) $t['c'] ?></strong>
            </div>
        <?php endforeach; ?>

        <?php if ($stats['top_users']): ?>
            <div class="card-title divided" style="margin-top:14px;"><span>الأنشط في الفريق</span></div>
            <?php foreach ($stats['top_users'] as $u): ?>
                <div class="doc-log" style="display:flex;justify-content:space-between;">
                    <span><?= e($u['name']) ?></span><strong><?= (int) $u['c'] ?></strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
