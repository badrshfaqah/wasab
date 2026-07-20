<?php
use App\Core\Auth;
use App\Core\ModuleManager;

$isSystemAdmin = Auth::isSystemAdmin();
$isCompanyAdmin = Auth::isCompanyAdmin();
$manageCore = $isSystemAdmin || $isCompanyAdmin;

$coreItems = [
    ['label' => 'الرئيسية', 'icon' => '🏠', 'url' => route('/'), 'show' => true],
    ['label' => 'التقويم', 'icon' => '📅', 'url' => route('/calendar'), 'show' => true],
    ['label' => 'التقارير', 'icon' => '📊', 'url' => route('/reports'), 'show' => $isCompanyAdmin],
];

$moduleItems = $user ? ModuleManager::collectNavItems($user) : [];
foreach ($moduleItems as $item) {
    $coreItems[] = $item + ['show' => true];
}

// خط فاصل بين روابط الإضافات وبين قسم الإدارة (المستخدمون/الصلاحيات/الإعدادات...) -
// يُعرض فقط إن كان هناك فعلاً عنصر إداري سيظهر بعده، حتى لا يبقى خطاً معلّقاً بلا شيء تحته.
if ($isSystemAdmin || $manageCore) {
    $coreItems[] = ['divider' => true];
}

$coreItems[] = ['label' => 'الشركات', 'icon' => '🏢', 'url' => route('/companies'), 'show' => $isSystemAdmin];
$coreItems[] = ['label' => 'المستخدمون', 'icon' => '👥', 'url' => route('/users'), 'show' => $manageCore];
$coreItems[] = ['label' => 'الأدوار والصلاحيات', 'icon' => '🛡️', 'url' => route('/roles'), 'show' => $manageCore];
$pendingModuleUpdates = $isSystemAdmin ? ModuleManager::countPendingUpdates() : 0;
$coreItems[] = [
    'label' => 'الإضافات',
    'icon' => '🧩',
    'url' => route('/extensions'),
    'show' => $isSystemAdmin,
    'badge' => $pendingModuleUpdates > 0 ? $pendingModuleUpdates : null,
];
$coreItems[] = ['label' => 'الإعدادات', 'icon' => '⚙️', 'url' => route('/settings'), 'show' => $manageCore];
$coreItems[] = ['label' => 'سجل العمليات', 'icon' => '📜', 'url' => route('/activity-log'), 'show' => $manageCore];
?>
<aside class="sidebar">
    <div class="brand">
        <?php if (!empty($company['logo'])): ?>
            <img src="<?= e(base_url('storage/uploads/core/' . $company['logo'])) ?>" alt="">
        <?php else: ?>
            <span>🗂️</span>
        <?php endif; ?>
        <span><?= e(app_name()) ?></span>
    </div>
    <nav>
        <?php foreach ($coreItems as $item): ?>
            <?php if (!empty($item['divider'])): ?>
                <div class="nav-divider"></div>
                <?php continue; ?>
            <?php endif; ?>
            <?php if (empty($item['show'])) continue; ?>
            <a class="nav-link<?= rtrim($currentPath, '/') === rtrim(parse_url($item['url'], PHP_URL_PATH), '/') ? ' active' : '' ?>" href="<?= $item['url'] ?>">
                <span class="ic"><?= $item['icon'] ?></span><span><?= e($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?><span class="nav-badge"><?= (int) $item['badge'] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <a class="sidebar-version" href="<?= route('/wasab') ?>">
        وصاب · إصدار <?= e(\App\Core\Wasab::currentVersion()) ?>
    </a>
</aside>
