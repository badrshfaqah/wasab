<?php

declare(strict_types=1);

require BASE_PATH . '/app/Core/Autoload.php';
require BASE_PATH . '/app/Core/helpers.php';

use App\Core\CoreMigrator;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;

// لا نعرض أخطاء PHP الخام (مسارات الملفات، الاستعلامات...) للمستخدم النهائي؛ تُسجَّل بدلاً من ذلك.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

set_exception_handler(function (\Throwable $e): void {
    log_exception($e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8">'
        . '<body style="font-family:sans-serif;text-align:center;padding:60px">'
        . '<h2>حدث خطأ غير متوقع</h2><p>تم تسجيل المشكلة، يرجى المحاولة لاحقاً.</p></body></html>';
});

if (!is_file(BASE_PATH . '/config.php')) {
    $installUrl = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/install/';
    header('Location: ' . $installUrl);
    exit;
}

define('APP_CONFIG', require BASE_PATH . '/config.php');
// نسخة الأصول مربوطة برقم إصدار النظام: كل تحديث يجدد روابط CSS/JS تلقائياً
// فلا يعلق أي جهاز (خصوصاً الجوال) على ملفات قديمة من ذاكرة التخزين المؤقت.
define('APP_ASSET_VERSION', \App\Core\Wasab::currentVersion());

date_default_timezone_set(config_get('timezone', 'Asia/Riyadh'));

Session::start();
Database::connect(APP_CONFIG['db']);
View::addPath(BASE_PATH . '/app/Views');
CoreMigrator::run();
// ترقيات الإضافات تلقائياً بعد تحديث الملفات (ذاتياً أو يدوياً) حتى لا يسبق كود
// الإضافة مخططها في قاعدة البيانات فتنهار الصفحات المعتمدة على أعمدة جديدة.
\App\Core\ModuleManager::autoMigratePending();
