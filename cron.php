<?php

declare(strict_types=1);

/**
 * نقطة دخول مهام الجدولة الدورية (تذكير الاجتماعات القريبة، أحداث تقويم الشركة...).
 * أضفها كمهمة Cron من لوحة تحكم الاستضافة (cPanel وغيرها) تعمل كل ٥ إلى ١٥ دقيقة، مثال:
 *   php /full/path/to/system/cron.php
 * مخصصة للتشغيل من سطر الأوامر (CLI) فقط - يرفض أي وصول عبر المتصفح مباشرة.
 *
 * لضمان روابط صحيحة داخل الإشعارات المُرسلة من هنا (لا يوجد طلب HTTP حي لاستنتاج
 * عنوان الموقع منه كما بباقي الصفحات)، عرّف "عنوان الموقع" من صفحة الإعدادات أولاً.
 */

define('BASE_PATH', __DIR__);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('ممنوع الوصول المباشر عبر المتصفح. هذا الملف يُشغَّل عبر Cron Job من سطر الأوامر فقط.');
}

require BASE_PATH . '/app/Core/Autoload.php';
require BASE_PATH . '/app/Core/helpers.php';

if (!is_file(BASE_PATH . '/config.php')) {
    fwrite(STDERR, "النظام غير مثبت بعد.\n");
    exit(1);
}

define('APP_CONFIG', require BASE_PATH . '/config.php');
date_default_timezone_set(config_get('timezone', 'Asia/Riyadh'));

use App\Core\CronRunner;
use App\Core\Database;

try {
    Database::connect(APP_CONFIG['db']);
    // اكتمال التحديث تلقائياً حتى بلا زوّار: لو سحبت الاستضافة ملفات جديدة، يكتشف
    // الكرون تغيّر رقم الإصدار ويطبّق الترقيات خلال دورته التالية.
    \App\Core\ModuleManager::finalizeIfVersionChanged();
    CronRunner::run();
    echo 'تم تشغيل مهام الجدولة بنجاح - ' . date('Y-m-d H:i:s') . "\n";
} catch (\Throwable $e) {
    log_exception($e);
    fwrite(STDERR, 'خطأ أثناء تشغيل مهام الجدولة: ' . $e->getMessage() . "\n");
    exit(1);
}
