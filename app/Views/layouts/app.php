<?php
/** @var array|null $user */
/** @var string $content */
use App\Core\Auth;
use App\Core\Notification;

$user = current_user();
$company = null;
if ($user && !empty($user['company_id'])) {
    $company = \App\Core\Database::first('SELECT * FROM companies WHERE id = :id', ['id' => $user['company_id']]);
}
$primaryColor = $company['primary_color'] ?? '#2563eb';
$sidebarColor = $company['sidebar_color'] ?? '#111827';
// ثيم الشركة يحدد لون خلفية الصفحة ونمط الأشكال؛ اللونان الأساسي والقائمة
// يبقيان من أعمدة الشركة (يكتبهما اختيار الثيم، ويتيح تخصيصهما فوقه).
$theme = \App\Core\Theme::resolve($company['theme'] ?? null);
$unread = $user ? Notification::unreadCount((int) $user['id']) : 0;
$notifications = $user ? Notification::recent((int) $user['id'], 6) : [];
$currentPath = \App\Core\Request::path();

$impersonating = Auth::isImpersonating();
$impersonatorName = null;
if ($impersonating) {
    $admin = \App\Core\Database::first('SELECT name FROM users WHERE id = :id', ['id' => Auth::impersonatorId()]);
    $impersonatorName = $admin['name'] ?? 'مدير النظام';
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="manifest" href="<?= route('manifest') ?>">
<meta name="theme-color" content="<?= e($primaryColor) ?>">
<link rel="apple-touch-icon" href="<?= app_icon_url(192) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="وصاب">
<style>:root{--primary:<?= e($primaryColor) ?>;--primary-light:<?= e($primaryColor) ?>14;--sidebar-bg:<?= e($sidebarColor) ?>;--bg:<?= e($theme['bg']) ?>;}</style>
</head>
<body class="theme-shape-<?= e($theme['shape']) ?>">
<div class="app-shell">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="sidebar-backdrop" hidden></div>
    <div class="main">
        <?php if ($impersonating): ?>
            <div class="impersonation-bar">
                <span>⚠️ أنت الآن تتصفح النظام بصفة <strong><?= e($user['name']) ?></strong> بدلاً من <?= e($impersonatorName) ?></span>
                <form method="post" action="<?= route('/impersonate/stop') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm">العودة لحسابي</button>
                </form>
            </div>
        <?php endif; ?>
        <?php include __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content">
            <?php if ($user): ?>
                <div class="push-banner" id="push-banner" hidden>
                    <span>🔔 فعّل التنبيهات ليصلك الجديد فور حدوثه: رسائل مركز المراسلات، المهام، والاجتماعات.</span>
                    <span class="push-banner-actions">
                        <button class="btn btn-sm" id="push-banner-enable" type="button">تفعيل الآن</button>
                        <button class="push-banner-close" id="push-banner-dismiss" type="button" aria-label="إغلاق">✕</button>
                    </span>
                </div>
            <?php endif; ?>
            <?php if ($msg = flash_get('success')): ?>
                <div class="alert alert-success" data-autohide><span>✓ <?= e($msg) ?></span></div>
            <?php endif; ?>
            <?php if ($msg = flash_get('error')): ?>
                <div class="alert alert-danger"><span>✗ <?= e($msg) ?></span></div>
            <?php endif; ?>
            <?= $content ?>
        </div>
        <?php if ($user) include __DIR__ . '/../partials/bottombar.php'; ?>
        <footer class="app-footer">
            <a href="<?= route('/wasab') ?>">إصدار <?= e(\App\Core\Wasab::currentVersion()) ?></a>
            <span>·</span>
            <a href="https://almgrat.com" target="_blank" rel="noopener">وصاب</a>
        </footer>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<?php
// إعدادات إشعارات الدفع للجوال - المفتاح العام يولَّد مرة واحدة عند أول طلب.
// أي فشل (استضافة بلا openssl EC مثلاً) يعطّل الميزة بصمت دون كسر الصفحة.
$pushVapidKey = '';
if ($user) {
    try {
        $pushVapidKey = \App\Core\WebPush::publicKey();
    } catch (\Throwable $e) {
        log_exception($e);
    }
}
?>
<?php if ($user && $pushVapidKey !== ''): ?>
<script>
window.WASAB_PUSH = {
    swUrl: <?= json_encode(route('sw.js')) ?>,
    vapidKey: <?= json_encode($pushVapidKey) ?>,
    subscribeUrl: <?= json_encode(route('/push/subscribe')) ?>,
    unsubscribeUrl: <?= json_encode(route('/push/unsubscribe')) ?>,
    csrf: <?= json_encode(\App\Core\Csrf::token()) ?>
};
</script>
<script src="<?= asset('js/push.js') ?>"></script>
<?php endif; ?>
<?php if ($user): ?>
    <?php foreach (\App\Core\ModuleManager::collectGlobalWidgets($user) as $widgetHtml): ?>
        <?= $widgetHtml ?>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
