<?php
$fmtSize = function (int $bytes): string {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' ج.ب';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' م.ب';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' ك.ب';
    }
    return $bytes . ' بايت';
};
$stat = function (string $label, $value, string $icon, string $sub = '') {
    echo '<div class="card" style="text-align:center;padding:18px;">'
        . '<div style="font-size:26px;">' . $icon . '</div>'
        . '<div style="font-size:28px;font-weight:800;">' . e((string) $value) . '</div>'
        . '<div class="hint">' . e($label) . ($sub ? ' · ' . e($sub) : '') . '</div>'
        . '</div>';
};
?>
<div class="page-head">
    <div><h1>لوحة النظام</h1><p>نظرة شاملة على كل الشركات والمستخدمين والموارد.</p></div>
    <a class="btn btn-outline" href="<?= route('/companies') ?>">🏢 إدارة الشركات</a>
</div>

<div class="grid-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;">
    <?php
    $stat('الشركات', (int) $companyStats['total'], '🏢', (int) $companyStats['active'] . ' نشطة');
    $stat('المستخدمون النشطون', (int) $userStats['active'], '👥', 'من ' . (int) $userStats['total']);
    $stat('مدراء الشركات', (int) $userStats['company_admins'], '🛡️');
    $stat('الإضافات المفعّلة', (int) $moduleStats['active'], '🧩', 'من ' . (int) $moduleStats['total']);
    $stat('إجمالي التخزين', $fmtSize((int) $totalStorage), '💾');
    ?>
</div>

<div class="card">
    <div class="card-title"><span>الشركات</span></div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>الشركة</th><th>الحالة</th><th>المستخدمون</th><th>التخزين</th><th>آخر نشاط</th><th>الإنشاء</th></tr></thead>
        <tbody>
        <?php if (!$companies): ?><tr><td colspan="6"><div class="empty-state"><div class="ic">🏢</div>لا توجد شركات</div></td></tr><?php endif; ?>
        <?php foreach ($companies as $c): ?>
            <tr>
                <td><a href="<?= route('/companies/' . $c['id'] . '/edit') ?>"><?= e($c['name']) ?></a></td>
                <td><?= status_badge($c['status']) ?></td>
                <td><?= (int) $c['users_count'] ?></td>
                <td><?= $fmtSize((int) $c['storage_bytes']) ?></td>
                <td><?= $c['last_activity'] ? format_date($c['last_activity'], 'Y-m-d H:i') : '<span class="hint">—</span>' ?></td>
                <td><?= format_date($c['created_at'], 'Y-m-d') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;">
        <span>النسخ الاحتياطية</span>
        <form method="post" action="<?= route('/admin/backup/run') ?>" onsubmit="return confirm('إنشاء نسخة احتياطية كاملة الآن؟');">
            <?= csrf_field() ?>
            <button class="btn btn-sm" type="submit">💾 إنشاء نسخة الآن</button>
        </form>
    </div>
    <p class="hint" style="margin-top:0;">نسخة كاملة من قاعدة البيانات تُنشأ تلقائياً يومياً (يُحتفظ بآخر 7 نسخ). الملفات محميّة ولا تُنزَّل إلا من هنا.</p>
    <?php if (!$backups): ?>
        <p class="hint">لا توجد نسخ بعد — ستُنشأ أول نسخة تلقائياً عند تشغيل المجدول، أو أنشئها الآن.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>الملف</th><th>الحجم</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($backups as $b): ?>
                <tr>
                    <td dir="ltr" style="text-align:right;"><?= e($b['name']) ?></td>
                    <td><?= $fmtSize((int) $b['size']) ?></td>
                    <td><?= e(date('Y-m-d H:i', $b['mtime'])) ?></td>
                    <td><a class="btn btn-outline btn-sm" href="<?= route('/admin/backup/' . rawurlencode($b['name']) . '/download') ?>">تنزيل</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title"><span>آخر النشاط عبر النظام</span></div>
    <?php if (!$recentActivity): ?>
        <p class="hint">لا يوجد نشاط مسجّل.</p>
    <?php endif; ?>
    <?php foreach ($recentActivity as $a): ?>
        <div class="doc-log">
            <div><?= e($a['description']) ?></div>
            <div class="doc-log-meta"><?= e($a['user_name'] ?? 'النظام') ?><?= $a['company_name'] ? ' · ' . e($a['company_name']) : '' ?> · <?= format_date($a['created_at'], 'Y-m-d H:i') ?></div>
        </div>
    <?php endforeach; ?>
</div>
