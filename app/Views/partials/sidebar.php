<?php
use App\Core\Auth;
use App\Core\ModuleManager;

/**
 * الشريط الجانبي: مجموعات لا قائمة مسطّحة.
 *
 * مع نمو الإضافات تجاوزت الروابط الخمسة والعشرين، وقائمة بهذا الطول بلا هرمية
 * تُجبر العين على قراءة كل شيء لتجد شيئاً. فرّقناها إلى مجموعات قصيرة يعرفها
 * مدير تطوير الأعمال ومسؤول العمليات من عناوينها: ما ينتظرني الآن، ثم عملي
 * اليومي، ثم العلاقات والأوراق والموارد، وأخيراً الإدارة (مطوية افتراضياً لأنها
 * تُفتح مرة في الشهر لا كل ساعة). ومربع تصفية أعلى القائمة للوصول بالكتابة.
 */

$isSystemAdmin = Auth::isSystemAdmin();
$isCompanyAdmin = Auth::isCompanyAdmin();
$manageCore = $isSystemAdmin || $isCompanyAdmin;

try {
    $approvalsCount = \App\Controllers\ApprovalsController::pendingCount();
} catch (\Throwable $e) {
    $approvalsCount = 0;
}
$pendingModuleUpdates = $isSystemAdmin ? ModuleManager::countPendingUpdates() : 0;

/** تعريف المجموعات بترتيب ظهورها. */
$groups = [
    'now' => ['title' => null, 'open' => true],                    // بلا عنوان: أول ما تقع عليه العين
    'mine' => ['title' => 'مساحتي', 'open' => true],
    'work' => ['title' => 'العمل اليومي', 'open' => true],
    'relations' => ['title' => 'العلاقات', 'open' => true],
    'papers' => ['title' => 'الأوراق والمستندات', 'open' => true],
    'resources' => ['title' => 'الموارد', 'open' => true],
    'admin' => ['title' => 'الإدارة والإعدادات', 'open' => false],
];

/** المجموعة الافتراضية لكل إضافة - وأي إضافة تستطيع تجاوزها بـ group في nav.php. */
$moduleGroup = [
    'inbox' => 'work', 'tasks' => 'work', 'meetings' => 'work', 'phone' => 'work', 'checkins' => 'work',
    'contacts' => 'relations', 'crm' => 'relations',
    'documents' => 'papers', 'forms' => 'papers', 'archive' => 'papers',
    'employees' => 'resources', 'assets' => 'resources', 'expenses' => 'resources',
];

/** روابط شخصية تُنقل لمساحة المستخدم مهما كانت إضافتها. */
$personalUrls = ['/me', '/employees/leaves', '/crm/today', '/forms/request'];

$items = [];
$add = function (string $group, array $item) use (&$items): void {
    if (($item['show'] ?? true) === false) {
        return;
    }
    $items[$group][] = $item;
};

// ما ينتظرني الآن
$add('now', ['label' => 'الرئيسية', 'icon' => '🏠', 'url' => route('/')]);
$add('now', ['label' => 'بانتظار قرارك', 'icon' => '✅', 'url' => route('/approvals'), 'badge' => $approvalsCount ?: null]);
$add('mine', ['label' => 'ملفي', 'icon' => '👤', 'url' => route('/me')]);
$add('work', ['label' => 'التقويم', 'icon' => '📅', 'url' => route('/calendar')]);

// روابط الإضافات المفعّلة
foreach (($user ? ModuleManager::collectNavItems($user) : []) as $item) {
    $path = parse_url($item['url'] ?? '', PHP_URL_PATH) ?: '';
    $path = '/' . trim(str_replace(rtrim(base_url(''), '/'), '', $path), '/');
    $group = $item['group'] ?? ($moduleGroup[$item['module'] ?? ''] ?? 'work');
    if (in_array($path, $personalUrls, true) || str_contains($item['label'] ?? '', 'ملفي')) {
        $group = 'mine';
    }
    $add($group, $item);
}

// الإدارة
$add('admin', ['label' => 'التقارير', 'icon' => '📊', 'url' => route('/reports'), 'show' => $isCompanyAdmin]);
$add('admin', ['label' => 'المستخدمون', 'icon' => '👥', 'url' => route('/users'), 'show' => $manageCore]);
$add('admin', ['label' => 'الأدوار والصلاحيات', 'icon' => '🛡️', 'url' => route('/roles'), 'show' => $manageCore]);
$add('admin', ['label' => 'أختام الشركة', 'icon' => '🔖', 'url' => route('/stamps'), 'show' => $manageCore]);
$add('admin', ['label' => 'الإعدادات', 'icon' => '⚙️', 'url' => route('/settings'), 'show' => $manageCore]);
$add('admin', ['label' => 'سجل العمليات', 'icon' => '📜', 'url' => route('/activity-log'), 'show' => $manageCore]);
$add('admin', ['label' => 'الإضافات', 'icon' => '🧩', 'url' => route('/extensions'), 'show' => $isSystemAdmin, 'badge' => $pendingModuleUpdates ?: null]);
$add('admin', ['label' => 'لوحة النظام', 'icon' => '🛠️', 'url' => route('/admin'), 'show' => $isSystemAdmin]);
$add('admin', ['label' => 'الشركات', 'icon' => '🏢', 'url' => route('/companies'), 'show' => $isSystemAdmin]);
$add('admin', ['label' => 'تثبيت التطبيق', 'icon' => '📱', 'url' => route('/get-app')]);

$isActive = function (string $url) use ($currentPath): bool {
    return rtrim($currentPath, '/') === rtrim(parse_url($url, PHP_URL_PATH) ?: '', '/');
};
?>
<aside class="sidebar">
  <div class="sidebar-head">
    <div class="brand">
        <?php if (!empty($company['logo'])): ?>
            <img src="<?= e(route('/media/companies/' . $company['logo'])) ?>" alt="">
        <?php elseif ($appLogoUrl = app_logo_url()): ?>
            <img src="<?= e($appLogoUrl) ?>" alt="">
        <?php else: ?>
            <span>🗂️</span>
        <?php endif; ?>
        <span class="brand-text"><?= e($company['name'] ?? app_name()) ?></span>
        <button type="button" class="rail-toggle" id="rail-toggle" title="طيّ القائمة" aria-label="طيّ القائمة">
            <?= \App\Core\Icons::svg('chevron', 16) ?>
        </button>
    </div>

    <button type="button" class="nav-filter nav-palette-open" id="open-palette"
            title="ابحث في كل شيء (Ctrl+K)" aria-label="لوحة الأوامر">
        <span aria-hidden="true"><?= \App\Core\Icons::svg('search', 16) ?></span>
        <span class="nav-palette-label">ابحث في كل شيء…</span>
        <kbd>Ctrl K</kbd>
    </button>
  </div>

    <nav id="sidebar-nav">
        <?php foreach ($groups as $key => $group): ?>
            <?php
            $groupItems = $items[$key] ?? [];
            if (!$groupItems) {
                continue;
            }
            // المجموعة التي تحوي الصفحة الحالية تُفتح دائماً
            $hasActive = false;
            foreach ($groupItems as $item) {
                if ($isActive($item['url'])) {
                    $hasActive = true;
                    break;
                }
            }
            $open = $group['open'] || $hasActive;
            // مجموع تنبيهات المجموعة - يظهر على عنوانها حين تكون مطوية، فلا
            // يختفي ما ينتظر إجراءً لمجرد أن المستخدم طوى القسم
            $groupBadge = 0;
            foreach ($groupItems as $item) {
                $groupBadge += (int) ($item['badge'] ?? 0);
            }
            ?>
            <div class="nav-group<?= $open ? '' : ' collapsed' ?>" data-group="<?= e($key) ?>">
                <?php if ($group['title'] !== null): ?>
                    <button type="button" class="nav-group-title" aria-expanded="<?= $open ? 'true' : 'false' ?>">
                        <span><?= e($group['title']) ?></span>
                        <span class="chev" aria-hidden="true"><?= \App\Core\Icons::svg('chevron', 14) ?></span>
                        <?php if ($groupBadge > 0): ?>
                            <span class="nav-group-badge" title="<?= (int) $groupBadge ?> بانتظار إجراء"><?= (int) $groupBadge ?></span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
                <div class="nav-group-items">
                    <?php foreach ($groupItems as $item): ?>
                        <?php
                        $iconName = $item['svg'] ?? \App\Core\Icons::forPath($item['url']);
                        $iconSvg = $iconName ? \App\Core\Icons::svg($iconName) : '';
                        ?>
                        <a class="nav-link<?= $isActive($item['url']) ? ' active' : '' ?>" href="<?= $item['url'] ?>"
                           title="<?= e($item['label']) ?>">
                            <span class="ic"><?= $iconSvg ?: $item['icon'] ?></span><span class="nav-text"><?= e($item['label']) ?></span>
                            <?php if (!empty($item['badge'])): ?><span class="nav-badge"><?= (int) $item['badge'] ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <p class="nav-empty" hidden>لا رابط مطابق</p>
    </nav>

    <a class="sidebar-version" href="<?= route('/wasab') ?>">
        وصاب · إصدار <?= e(\App\Core\Wasab::currentVersion()) ?>
    </a>
</aside>
