<?php
/**
 * شريط التنقل السفلي للجوال (يظهر فقط ≤900px عبر CSS): الوجهات اليومية الأربع
 * + زر "القائمة" يفتح القائمة الجانبية الكاملة. يحل محل أزرار الشريط العلوي
 * المزدحمة على الجوال (البحث والجرس والبرغر) بنمط تطبيقات الجوال المألوف.
 */
$bnCurrent = rtrim($currentPath, '/') ?: '/';
$bnItems = [
    ['label' => 'الرئيسية', 'icon' => '🏠', 'url' => route('/'), 'path' => '/'],
    ['label' => 'التقويم', 'icon' => '📅', 'url' => route('/calendar'), 'path' => '/calendar'],
    ['label' => 'بحث', 'icon' => '🔍', 'url' => route('/search'), 'path' => '/search'],
    ['label' => 'الإشعارات', 'icon' => '🔔', 'url' => route('/notifications'), 'path' => '/notifications', 'badge' => $unread ?? 0],
];
?>
<nav class="bottom-nav" aria-label="التنقل السريع">
    <?php foreach ($bnItems as $item): ?>
        <?php $active = $item['path'] === '/' ? $bnCurrent === '/' : str_starts_with($bnCurrent, $item['path']); ?>
        <a class="bottom-nav-item<?= $active ? ' active' : '' ?>" href="<?= $item['url'] ?>">
            <span class="bn-ic"><?= $item['icon'] ?></span>
            <span><?= e($item['label']) ?></span>
            <?php if (!empty($item['badge'])): ?><span class="bn-badge"><?= $item['badge'] > 9 ? '9+' : (int) $item['badge'] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
    <button type="button" class="bottom-nav-item" data-sidebar-toggle>
        <span class="bn-ic">☰</span>
        <span>القائمة</span>
    </button>
</nav>
