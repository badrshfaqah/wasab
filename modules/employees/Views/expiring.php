<?php
// تقسيم الوثائق: منتهية فعلاً / تنتهي خلال 30 يوماً / أبعد من ذلك
$expired = [];
$soon = [];
$later = [];
foreach ($documents as $d) {
    if ($d['days_left'] < 0) {
        $expired[] = $d;
    } elseif ($d['days_left'] <= 30) {
        $soon[] = $d;
    } else {
        $later[] = $d;
    }
}

$daysLabel = function (int $days): string {
    if ($days < 0) {
        return 'انتهت منذ ' . abs($days) . ' يوماً';
    }
    if ($days === 0) {
        return 'تنتهي اليوم';
    }
    return 'تبقّى ' . $days . ' يوماً';
};

$row = function (array $d) use ($daysLabel) {
    $color = $d['days_left'] < 0 ? 'danger' : ($d['days_left'] <= 30 ? 'warning' : 'muted');
    ob_start(); ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:10px 0;border-bottom:1px solid var(--border);">
        <div>
            <span style="font-size:18px;"><?= $d['icon'] ?></span>
            <a href="<?= route('/employees/' . $d['employee_id']) ?>"><strong><?= e($d['full_name']) ?></strong></a>
            <span class="hint"> · <?= e($d['kind']) ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="hint"><?= e($d['expiry_date']) ?></span>
            <span class="badge badge-<?= $color ?>"><?= $daysLabel((int) $d['days_left']) ?></span>
        </div>
    </div>
    <?php return ob_get_clean();
};
?>
<div class="page-head">
    <div>
        <h1>🔔 تنبيهات الوثائق</h1>
        <p>الإقامات والجوازات والرخص والشهادات والعقود المنتهية أو التي قاربت على الانتهاء.</p>
    </div>
    <form method="get" action="<?= route('/employees/expiring') ?>" style="display:flex;gap:8px;align-items:center;">
        <label class="hint">النطاق:</label>
        <select name="within" onchange="this.form.submit()" style="width:auto;">
            <?php foreach ([30 => '30 يوماً', 60 => '60 يوماً', 90 => '90 يوماً', 180 => '180 يوماً'] as $v => $lbl): ?>
                <option value="<?= $v ?>" <?= $within === $v ? 'selected' : '' ?>>خلال <?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!$documents): ?>
    <div class="card">
        <div class="empty-state"><div class="ic">✅</div><h3>لا توجد وثائق منتهية أو قاربت على الانتهاء</h3><p>كل الوثائق ضمن النطاق المحدد سارية.</p></div>
    </div>
<?php else: ?>

    <?php if ($expired): ?>
    <div class="card" style="border-inline-start:4px solid var(--danger);">
        <div class="card-title"><span>⛔ منتهية فعلاً (<?= count($expired) ?>)</span></div>
        <?php foreach ($expired as $d) {
            echo $row($d);
        } ?>
    </div>
    <?php endif; ?>

    <?php if ($soon): ?>
    <div class="card" style="border-inline-start:4px solid var(--warning);">
        <div class="card-title"><span>⚠️ تنتهي خلال 30 يوماً (<?= count($soon) ?>)</span></div>
        <?php foreach ($soon as $d) {
            echo $row($d);
        } ?>
    </div>
    <?php endif; ?>

    <?php if ($later): ?>
    <div class="card">
        <div class="card-title"><span>🗓️ تنتهي لاحقاً ضمن النطاق (<?= count($later) ?>)</span></div>
        <?php foreach ($later as $d) {
            echo $row($d);
        } ?>
    </div>
    <?php endif; ?>

<?php endif; ?>
