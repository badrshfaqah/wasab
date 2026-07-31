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
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `forms_letters_company_index` (`company_id`),
            KEY `forms_letters_created_index` (`created_at`)
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
};
