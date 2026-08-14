<?php
/**
 * كل الأرقام في بطاقة واحدة موحّدة: صف لكل قسم (النواة ثم كل إضافة) وأرقامه
 * كحبوب مضغوطة بجانبه - بدل صناديق متفرقة لكل إضافة كانت تشتت النظر.
 */
$grouped = [];
foreach ($moduleStats as $s) {
    $grouped[$s['module_name']][] = $s;
}

$renderStat = function (array $s): string {
    $classColor = 'c-' . e($s['color'] ?? 'primary');
    $inner = '<b>' . e((string) $s['value']) . '</b> ' . e($s['label']);
    if (!empty($s['url'])) {
        return '<a class="report-stat ' . $classColor . '" href="' . e($s['url']) . '">' . e($s['icon'] ?? '') . ' ' . $inner . '</a>';
    }
    return '<span class="report-stat ' . $classColor . '">' . e($s['icon'] ?? '') . ' ' . $inner . '</span>';
};
?>
<div class="page-head">
    <div><h1>التقارير</h1><p>أرقام الشركة من كل الإضافات المفعّلة في مكان واحد.</p></div>
    <a class="btn" href="<?= route('/reports/monthly') ?>" target="_blank" rel="noopener">🖨️ التقرير الشهري (PDF)</a>
</div>

<?php if (!$grouped && !$coreStats): ?>
    <div class="empty-state">
        <div class="ic">📊</div>
        <h3>لا توجد أرقام لعرضها بعد</h3>
        <p>فعّل إضافات، أو ارجع لاحقاً بعد إضافة بيانات.</p>
    </div>
<?php else: ?>
<div class="card report-card">
    <?php if ($coreStats): ?>
        <div class="report-row">
            <div class="report-module">🏢 النظام</div>
            <div class="report-stats">
                <?php foreach ($coreStats as $s) echo $renderStat($s); ?>
            </div>
        </div>
    <?php endif; ?>
    <?php foreach ($grouped as $moduleName => $items): ?>
        <div class="report-row">
            <div class="report-module">🧩 <?= e($moduleName) ?></div>
            <div class="report-stats">
                <?php foreach ($items as $s) echo $renderStat($s); ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
