<?php
/**
 * تثبيت إضافة النماذج: قوالب الخطابات (بحقول دمج)، الخطابات المولّدة، إعدادات
 * الترويسة (خلفية/توقيع/ختم/ترقيم)، وسجل. مستقلة عن باقي الإضافات - تعمل حتى لو
 * عُطّل الملف الوظيفي (يصبح الدمج يدوياً بالكامل حينها).
 *
 * عند التثبيت تُزرع قوالب جاهزة افتراضية لكل شركة عبر seedDefaults().
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `forms_templates` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(160) NOT NULL,
            `body` MEDIUMTEXT NOT NULL COMMENT 'نص القالب بحقول دمج مثل {الاسم} {الراتب_الأساسي}',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `stamp_id` INT UNSIGNED NULL COMMENT 'ختم افتراضي لهذا القالب من مكتبة أختام الشركة',
            `qr_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'إظهار رمز QR للتحقق على الخطاب',
            `qr_x` INT NOT NULL DEFAULT 40 COMMENT 'بكسل من يسار الصفحة',
            `qr_y` INT NOT NULL DEFAULT 40 COMMENT 'بكسل من أسفل الصفحة',
            `qr_size` INT NOT NULL DEFAULT 90 COMMENT 'حجم رمز QR بالبكسل',
            `qr_color` VARCHAR(7) NOT NULL DEFAULT '#000000' COMMENT 'لون رمز QR',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `forms_templates_company_index` (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `forms_letters` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `template_id` INT UNSIGNED NULL,
            `title` VARCHAR(200) NOT NULL,
            `number` VARCHAR(60) NULL,
            `employee_id` INT UNSIGNED NULL COMMENT 'الموظف المولَّد له إن اختير من الملف الوظيفي',
            `recipient_name` VARCHAR(180) NULL COMMENT 'اسم المستفيد (لقطة)',
            `body` MEDIUMTEXT NOT NULL COMMENT 'النص النهائي بعد ملء حقول الدمج',
            `signature_file` VARCHAR(255) NULL COMMENT 'صورة توقيع مُصدِر الخطاب المختار (لقطة)',
            `qr_enabled` TINYINT(1) NULL COMMENT 'إظهار رمز التحقق على الخطاب - NULL يعني اتباع إعداد القالب',
            `verify_token` CHAR(32) NULL COMMENT 'رمز تحقق عام غير قابل للتخمين للتأكد من صحة الخطاب',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `forms_letters_company_index` (`company_id`),
            KEY `forms_letters_created_index` (`created_at`),
            UNIQUE KEY `forms_letters_verify_token_unique` (`verify_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `forms_settings` (
            `company_id` INT UNSIGNED NOT NULL PRIMARY KEY,
            `number_prefix` VARCHAR(30) NULL,
            `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
            `background_image` VARCHAR(255) NULL,
            `header_html` TEXT NULL,
            `footer_html` TEXT NULL,
            `signature_image` VARCHAR(255) NULL,
            `stamp_image` VARCHAR(255) NULL,
            `signer_name` VARCHAR(120) NULL,
            `signer_title` VARCHAR(120) NULL,
            `updated_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // زرع القوالب الجاهزة لكل شركة موجودة (لا تُكرَّر إن وُجدت)
    $companies = $pdo->query('SELECT id FROM companies')->fetchAll(PDO::FETCH_COLUMN);
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO forms_templates (company_id, name, body, is_active, created_at) VALUES (:c, :n, :b, 1, :t)');
    foreach ($companies as $cid) {
        $existing = (int) $pdo->query('SELECT COUNT(*) FROM forms_templates WHERE company_id = ' . (int) $cid)->fetchColumn();
        if ($existing > 0) {
            continue;
        }
        foreach (require __DIR__ . '/default_templates.php' as $tpl) {
            $stmt->execute(['c' => $cid, 'n' => $tpl['name'], 'b' => $tpl['body'], 't' => $now]);
        }
    }

    // طلبات الخطابات: الموظف يطلب خطاباً واعتماد المدير يولّده تلقائياً
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `forms_requests` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `employee_id` INT UNSIGNED NOT NULL,
            `template_id` INT UNSIGNED NOT NULL,
            `note` VARCHAR(500) NULL,
            `status` ENUM('pending','done','rejected') NOT NULL DEFAULT 'pending',
            `letter_id` INT UNSIGNED NULL,
            `decided_by` INT UNSIGNED NULL,
            `decided_at` DATETIME NULL,
            `decision_note` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            KEY `forms_requests_company_index` (`company_id`),
            KEY `forms_requests_status_index` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

};