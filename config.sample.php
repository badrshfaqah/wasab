<?php
/**
 * انسخ هذا الملف باسم config.php وعدّل القيم يدوياً إذا احتجت،
 * أو دع معالج التثبيت (install/) ينشئه تلقائياً.
 */
return [
    'app_name'   => 'نظام الإدارة',
    'app_url'    => '', // يُملأ تلقائياً عند التثبيت، اتركه فارغاً ليُكتشف تلقائياً
    'app_key'    => '', // يُنشأ تلقائياً عند التثبيت
    'timezone'   => 'Asia/Riyadh',

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'database' => '',
        'username' => '',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    /**
     * بطاقة Apple Wallet لبطاقة الموظف (اختياري).
     *
     * اتركها فارغة ويختفي الزر من التطبيق. لتفعيلها تحتاج من حساب أبل
     * للمطورين: مُعرّف Pass Type ID وشهادته (p12)، ومُعرّف الفريق، وشهادة
     * أبل الوسيطة WWDR بصيغة pem. ضع الملفين في storage/wallet/.
     */
    'wallet' => [
        'pass_type_identifier' => '',   // مثال: pass.sa.devco.wasab.employee
        'team_identifier' => '',        // مُعرّف الفريق في حساب أبل
        'certificate' => __DIR__ . '/storage/wallet/pass.p12',
        'certificate_password' => '',
        // حين يرفض OpenSSL ملف p12 (تشفير RC2 من Keychain) حوّله لـ PEM:
        //   openssl pkcs12 -in pass.p12 -out pass.pem -nodes -legacy
        // واجعل certificate يشير إلى pass.pem، واترك private_key فارغاً.
        'private_key' => '',
        'wwdr' => __DIR__ . '/storage/wallet/wwdr.pem',
    ],
];
