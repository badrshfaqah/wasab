<?php
use App\Core\Auth;
use App\Core\ModuleManager;

$isSystemAdmin = Auth::isSystemAdmin();
$isCompanyAdmin = Auth::isCompanyAdmin();
$manageCore = $isSystemAdmin || $isCompanyAdmin;

$coreItems = [
    ['label' => 'الرئيسية', 'icon' => '🏠', 'url' => route('/'), 'show' => true],
];

$moduleItems = $user ? ModuleManager::collectNavItems($user) : [];
foreach ($moduleItems as $item) {
    $coreItems[] = $item + ['show' => true];
}

$coreItems[] = ['label' => 'الشركات', 'icon' => '🏢', 'url' => route('/companies'), 'show' => $isSystemAdmin];
$coreItems[] = ['label' => 'المستخدمون', 'icon' => '👥', 'url' => route('/users'), 'show' => $manageCore];
$coreItems[] = ['label' => 'الأدوار والصلاحيات', 'icon' => '🛡️', 'url' => route('/roles'), 'show' => $manageCore];
$coreItems[] = ['label' => 'الإضافات', 'icon' => '🧩', 'url' => route('/extensions'), 'show' => $isSystemAdmin];
$coreItems[] = ['label' => 'الإعدادات', 'icon' => '⚙️', 'url' => route('/settings'), 'show' => $manageCore];
$coreItems[] = ['label' => 'سجل العمليات', 'icon' => '📜', 'url' => route('/activity-log'), 'show' => $manageCore];
?>
<aside class="sidebar">
    <div class="brand">
        <?php if (!empty($company['logo'])): ?>
            <img src="<?= e(base_url('storage/uploads/' . $company['logo'])) ?>" alt="">
        <?php else: ?>
            <span>🗂️</span>
        <?php endif; ?>
        <span><?= e(app_name()) ?></span>
    </div>
    <nav>
        <?php foreach ($coreItems as $item): ?>
            <?php if (empty($item['show'])) continue; ?>
            <a class="nav-link<?= rtrim($currentPath, '/') === rtrim(parse_url($item['url'], PHP_URL_PATH), '/') ? ' active' : '' ?>" href="<?= $item['url'] ?>">
                <span class="ic"><?= $item['icon'] ?></span><span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
