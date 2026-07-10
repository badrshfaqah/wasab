<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

if (is_file(BASE_PATH . '/storage/install.lock')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><body style="font-family:sans-serif;text-align:center;padding:60px">';
    echo '<h2>التثبيت مكتمل مسبقاً</h2><p>تم قفل صفحة التثبيت. يمكنك الانتقال إلى صفحة تسجيل الدخول.</p>';
    echo '<a href="../">الذهاب لتسجيل الدخول</a></body></html>';
    exit;
}

session_start();
require BASE_PATH . '/install/partials.php';

$step = (int) ($_GET['step'] ?? 1);
$step = max(1, min(4, $step));
$errors = [];

function install_requirements(): array
{
    return [
        'PHP بإصدار 8.0 أو أحدث' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'امتداد PDO MySQL' => extension_loaded('pdo_mysql'),
        'امتداد mbstring' => extension_loaded('mbstring'),
        'امتداد JSON' => extension_loaded('json'),
        'امتداد Session' => extension_loaded('session'),
        'إمكانية الكتابة داخل مجلد الجذر' => is_writable(BASE_PATH),
        'إمكانية الكتابة داخل storage/' => is_writable(BASE_PATH . '/storage'),
        'إمكانية الكتابة داخل storage/uploads' => is_writable(BASE_PATH . '/storage/uploads'),
        'إمكانية الكتابة داخل storage/logs' => is_writable(BASE_PATH . '/storage/logs'),
    ];
}

// ---------- الخطوة 1: فحص المتطلبات ----------
if ($step === 1) {
    $requirements = install_requirements();
    $allPass = !in_array(false, $requirements, true);

    install_header('فحص المتطلبات', 1);
    ?>
    <h1>فحص متطلبات السيرفر</h1>
    <p class="desc">تأكد من توافق السيرفر مع متطلبات تشغيل النظام قبل المتابعة.</p>
    <ul class="req-list">
        <?php foreach ($requirements as $label => $pass): ?>
            <li><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="<?= $pass ? 'ok' : 'fail' ?>"><?= $pass ? '✓ جاهز' : '✗ غير متوفر' ?></span></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($allPass): ?>
        <a class="btn btn-block" href="?step=2">متابعة</a>
    <?php else: ?>
        <div class="alert alert-danger">يرجى معالجة النقاط غير المتوفرة أعلاه ثم إعادة تحميل الصفحة.</div>
        <a class="btn btn-block" href="?step=1">إعادة الفحص</a>
    <?php endif; ?>
    <?php
    install_footer();
    exit;
}

// ---------- الخطوة 2: بيانات قاعدة البيانات ----------
if ($step === 2) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = trim($_POST['db_host'] ?? '');
        $port = trim($_POST['db_port'] ?? '3306');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = (string) ($_POST['db_pass'] ?? '');

        if ($host === '' || $name === '' || $user === '') {
            $errors[] = 'يرجى تعبئة جميع الحقول المطلوبة.';
        } else {
            require_once BASE_PATH . '/app/Core/Database.php';
            try {
                $pdo = \App\Core\Database::connectRaw($host, $port, $user, $pass);
                try {
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (\Throwable $ignored) {
                    // قد لا يملك المستخدم صلاحية إنشاء قاعدة بيانات؛ سنحاول الاتصال بها مباشرة أدناه
                }
                $pdo = \App\Core\Database::connectRaw($host, $port, $user, $pass, $name);

                $_SESSION['install_db'] = compact('host', 'port', 'name', 'user', 'pass');
                header('Location: ?step=3');
                exit;
            } catch (\Throwable $e) {
                $errors[] = 'تعذّر الاتصال بقاعدة البيانات. تأكد من صحة البيانات ومن وجود قاعدة البيانات وصلاحية الوصول إليها للمستخدم المُدخل.';
            }
        }
    }

    $old = $_SESSION['install_db'] ?? ['host' => 'localhost', 'port' => '3306', 'name' => '', 'user' => '', 'pass' => ''];

    install_header('بيانات قاعدة البيانات', 2);
    ?>
    <h1>الاتصال بقاعدة بيانات MySQL</h1>
    <p class="desc">أدخل بيانات قاعدة البيانات التي أنشأتها من لوحة تحكم الاستضافة (مثل cPanel).</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
    <form method="post">
        <div class="grid-2">
            <div class="field"><label>عنوان السيرفر (Host)</label>
                <input type="text" name="db_host" value="<?= htmlspecialchars($old['host'], ENT_QUOTES, 'UTF-8') ?>" required></div>
            <div class="field"><label>المنفذ (Port)</label>
                <input type="text" name="db_port" value="<?= htmlspecialchars($old['port'], ENT_QUOTES, 'UTF-8') ?>"></div>
        </div>
        <div class="field"><label>اسم قاعدة البيانات</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($old['name'], ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="field"><label>اسم مستخدم قاعدة البيانات</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($old['user'], ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="field"><label>كلمة مرور قاعدة البيانات</label>
            <input type="password" name="db_pass" value="<?= htmlspecialchars($old['pass'], ENT_QUOTES, 'UTF-8') ?>"></div>
        <button class="btn btn-block" type="submit">اختبار الاتصال ومتابعة</button>
    </form>
    <?php
    install_footer();
    exit;
}

// ---------- الخطوة 3: حساب المدير والشركة ----------
if ($step === 3) {
    if (!isset($_SESSION['install_db'])) {
        header('Location: ?step=2');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $companyName = trim($_POST['company_name'] ?? '');
        $appName = trim($_POST['app_name'] ?? 'نظام الإدارة');
        $adminName = trim($_POST['admin_name'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass = (string) ($_POST['admin_password'] ?? '');
        $adminPassConfirm = (string) ($_POST['admin_password_confirm'] ?? '');

        if ($companyName === '' || $adminName === '' || $adminEmail === '' || $adminPass === '') {
            $errors[] = 'يرجى تعبئة جميع الحقول المطلوبة.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'صيغة البريد الإلكتروني غير صحيحة.';
        } elseif (strlen($adminPass) < 8) {
            $errors[] = 'يجب ألا تقل كلمة المرور عن 8 أحرف.';
        } elseif ($adminPass !== $adminPassConfirm) {
            $errors[] = 'كلمتا المرور غير متطابقتين.';
        }

        if (!$errors) {
            try {
                $db = $_SESSION['install_db'];
                require_once BASE_PATH . '/app/Core/Database.php';
                $pdo = \App\Core\Database::connectRaw($db['host'], $db['port'], $db['user'], $db['pass'], $db['name']);

                $schema = require BASE_PATH . '/install/schema.php';
                foreach ($schema as $sql) {
                    $pdo->exec($sql);
                }

                $now = date('Y-m-d H:i:s');

                $stmt = $pdo->prepare('INSERT INTO companies (name, status, created_at) VALUES (:name, "active", :now)');
                $stmt->execute(['name' => $companyName, 'now' => $now]);

                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (company_id, name, email, password, membership_type, status, created_at) VALUES (NULL, :name, :email, :password, "system_admin", "active", :now)');
                $stmt->execute(['name' => $adminName, 'email' => $adminEmail, 'password' => $hash, 'now' => $now]);

                $appKey = bin2hex(random_bytes(32));
                $configArray = [
                    'app_name' => $appName ?: 'نظام الإدارة',
                    'app_url' => '',
                    'app_key' => $appKey,
                    'timezone' => 'Asia/Riyadh',
                    'db' => [
                        'host' => $db['host'],
                        'port' => $db['port'],
                        'database' => $db['name'],
                        'username' => $db['user'],
                        'password' => $db['pass'],
                        'charset' => 'utf8mb4',
                    ],
                ];

                $configContent = "<?php\n\nreturn " . var_export($configArray, true) . ";\n";
                file_put_contents(BASE_PATH . '/config.php', $configContent);

                file_put_contents(BASE_PATH . '/storage/install.lock', "installed_at={$now}\n");

                unset($_SESSION['install_db']);

                header('Location: ?step=4');
                exit;
            } catch (\Throwable $e) {
                $errors[] = 'حدث خطأ أثناء إنشاء الجداول أو الحساب: ' . $e->getMessage();
            }
        }
    }

    install_header('الحساب والشركة', 3);
    ?>
    <h1>إنشاء أول شركة وحساب مدير النظام</h1>
    <p class="desc">هذه البيانات ستُستخدم لإنشاء أول شركة وأول حساب مدير نظام يملك صلاحيات كاملة.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
    <form method="post">
        <div class="field"><label>اسم النظام (يظهر في الواجهة)</label>
            <input type="text" name="app_name" value="<?= htmlspecialchars($_POST['app_name'] ?? 'نظام الإدارة', ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="field"><label>اسم الشركة الأولى</label>
            <input type="text" name="company_name" value="<?= htmlspecialchars($_POST['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
        <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">
        <div class="field"><label>اسم مدير النظام</label>
            <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="field"><label>البريد الإلكتروني لمدير النظام</label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="grid-2">
            <div class="field"><label>كلمة المرور</label>
                <input type="password" name="admin_password" required></div>
            <div class="field"><label>تأكيد كلمة المرور</label>
                <input type="password" name="admin_password_confirm" required></div>
        </div>
        <p class="hint">8 أحرف على الأقل.</p>
        <button class="btn btn-block" type="submit">إنشاء النظام</button>
    </form>
    <?php
    install_footer();
    exit;
}

// ---------- الخطوة 4: تم ----------
if ($step === 4) {
    if (!is_file(BASE_PATH . '/storage/install.lock')) {
        header('Location: ?step=1');
        exit;
    }
    install_header('اكتمل التثبيت', 4);
    ?>
    <div class="center">
        <div class="alert alert-success">تم تثبيت النظام بنجاح، وتم قفل صفحة التثبيت تلقائياً.</div>
        <h1>النظام جاهز للاستخدام</h1>
        <p class="desc">يمكنك الآن تسجيل الدخول باستخدام حساب مدير النظام الذي أنشأته.</p>
        <a class="btn btn-block" href="../login">الذهاب لتسجيل الدخول</a>
    </div>
    <?php
    install_footer();
    exit;
}
