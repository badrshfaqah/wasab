<?php

namespace App\Core;

/**
 * إصدار النظام العام ("وصاب") وسجل تحديثاته - منفصل تماماً عن رقم إصدار كل إضافة
 * على حدة (تلك تُدار عبر module.json + ModuleManager). المصدر: app/wasab/changelog.php
 * (أحدث إصدار أولاً)، يُحدَّث يدوياً أو عبر أداة النشر مع كل رفعة.
 *
 * تنبيه: هذا الملف يُقصد بجذر app/ عمداً وليس بجذر المشروع - مجلد باسم "wasab" في
 * جذر المشروع يتعارض مع مسار الصفحة نفسها /wasab (قاعدة .htaccess تتجاهل إعادة
 * التوجيه لأي مجلد موجود فعلياً على القرص بنفس اسم المسار، فتُرجع 404 دائماً).
 */
class Wasab
{
    private static ?array $changelog = null;

    public static function changelog(): array
    {
        if (self::$changelog === null) {
            $file = BASE_PATH . '/app/wasab/changelog.php';
            self::$changelog = is_file($file) ? (require $file) : [];
        }
        return self::$changelog;
    }

    public static function currentVersion(): string
    {
        $log = self::changelog();
        return $log[0]['version'] ?? '1.0.0';
    }
}
