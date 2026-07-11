<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول - <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
body{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;gap:14px;}
.login-card{width:100%;max-width:380px;background:#fff;border:1px solid var(--border);border-radius:14px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,.06);}
.login-logo{text-align:center;font-size:20px;font-weight:800;color:var(--primary);margin-bottom:22px;}
</style>
</head>
<body>
<div class="login-card">
    <div class="login-logo">🗂️ <?= e(app_name()) ?></div>
    <?php if ($msg = flash_get('error')): ?>
        <div class="alert alert-danger"><?= e($msg) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= route('/login') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>البريد الإلكتروني</label>
            <input type="email" name="email" value="<?= old('email') ?>" required autofocus>
        </div>
        <div class="field">
            <label>كلمة المرور</label>
            <input type="password" name="password" required>
        </div>
        <button class="btn btn-block" type="submit" style="width:100%;justify-content:center;">تسجيل الدخول</button>
    </form>
</div>
<footer class="app-footer">
    <span>إصدار <?= e(\App\Core\Wasab::currentVersion()) ?></span>
    <span>·</span>
    <a href="https://almgrat.com" target="_blank" rel="noopener">وصاب</a>
</footer>
</body>
</html>
