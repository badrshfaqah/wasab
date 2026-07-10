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
<style>:root{--primary:<?= e($primaryColor) ?>;--primary-light:<?= e($primaryColor) ?>14;}</style>
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
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
            <?php if ($msg = flash_get('success')): ?>
                <div class="alert alert-success" data-autohide><span>✓ <?= e($msg) ?></span></div>
            <?php endif; ?>
            <?php if ($msg = flash_get('error')): ?>
                <div class="alert alert-danger"><span>✗ <?= e($msg) ?></span></div>
            <?php endif; ?>
            <?= $content ?>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
