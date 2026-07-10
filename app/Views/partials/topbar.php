<?php
use App\Core\Csrf;
?>
<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-toggle" type="button" aria-label="القائمة">☰</button>
        <div class="page-title"><?= e($pageTitle ?? '') ?></div>
    </div>
    <div class="topbar-actions">
        <div class="user-menu">
            <button class="icon-btn" type="button" data-dropdown-toggle="notif-dropdown">
                🔔<?php if ($unread > 0): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
            </button>
            <div class="dropdown" id="notif-dropdown" style="width:300px;">
                <?php if (!$notifications): ?>
                    <div class="notif-empty">لا توجد إشعارات</div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <a class="notif-item<?= $n['is_read'] ? '' : ' unread' ?>" href="<?= e($n['url'] ?: route('/notifications')) ?>">
                            <strong><?= e($n['title']) ?></strong>
                            <?php if ($n['message']): ?><div style="color:var(--muted);margin-top:2px;"><?= e($n['message']) ?></div><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="<?= route('/notifications') ?>" style="text-align:center;border-top:1px solid var(--border);font-size:12.5px;">عرض جميع الإشعارات</a>
            </div>
        </div>
        <div class="user-menu">
            <button class="user-btn" type="button" data-dropdown-toggle="user-dropdown">
                <span class="avatar"><?= e(mb_substr($user['name'] ?? '؟', 0, 1)) ?></span>
                <span><?= e($user['name'] ?? '') ?></span>
            </button>
            <div class="dropdown" id="user-dropdown">
                <a href="<?= route('/profile') ?>">الملف الشخصي</a>
                <div class="divider"></div>
                <form method="post" action="<?= route('/logout') ?>">
                    <?= csrf_field() ?>
                    <button type="submit">تسجيل الخروج</button>
                </form>
            </div>
        </div>
    </div>
</header>
