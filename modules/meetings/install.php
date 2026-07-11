<?php
/**
 * تثبيت إضافة الاجتماعات: ينشئ جداولها الخاصة فقط، دون المساس بجداول النواة.
 * لا يوجد ربط FK بجداول users/companies عمداً (استقلالية الإضافة عن النواة)،
 * لكن الجداول الفرعية (حضور/ملاحظات) مرتبطة بـ FK لأنها ملك الاجتماع نفسه.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `meetings_meetings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `type` ENUM('in_person','online') NOT NULL DEFAULT 'in_person',
            `location` VARCHAR(255) NULL,
            `starts_at` DATETIME NOT NULL,
            `ends_at` DATETIME NULL,
            `description` TEXT NULL,
            `outcomes` TEXT NULL,
            `status` ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
            `recurrence_rule` ENUM('none','weekly','monthly') NOT NULL DEFAULT 'none',
            `recurrence_end_date` DATE NULL COMMENT 'آخر تاريخ يتوقف عنده توليد مواعيد جديدة لهذه السلسلة',
            `recurrence_group_id` INT UNSIGNED NULL COMMENT 'معرّف أول موعد بالسلسلة - يربط كل مواعيد نفس التكرار ببعضها',
            `reminder_sent_at` DATETIME NULL COMMENT 'وقت إرسال تذكير الاجتماع القريب (مرة واحدة فقط) عبر cron.php',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `meetings_company_index` (`company_id`),
            KEY `meetings_starts_at_index` (`starts_at`),
            KEY `meetings_creator_index` (`created_by`),
            KEY `meetings_recurrence_group_index` (`recurrence_group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `meetings_attendees` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `meeting_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL COMMENT 'حاضر داخلي (مستخدم بالنظام) - فارغ لحاضر خارجي',
            `external_name` VARCHAR(150) NULL COMMENT 'اسم الحاضر الخارجي - فارغ لحاضر داخلي',
            `external_contact` VARCHAR(150) NULL,
            `token` VARCHAR(48) NULL COMMENT 'رابط تأكيد حضور بلا تسجيل دخول - يُولَّد للمدعوين الخارجيين فقط',
            `response` ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
            `responded_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            KEY `meetings_attendees_meeting_index` (`meeting_id`),
            KEY `meetings_attendees_user_index` (`user_id`),
            UNIQUE KEY `meetings_attendees_token_unique` (`token`),
            CONSTRAINT `meetings_attendees_meeting_fk` FOREIGN KEY (`meeting_id`) REFERENCES `meetings_meetings`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `meetings_notes` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `meeting_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `phase` ENUM('before','after') NOT NULL DEFAULT 'before',
            `body` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            KEY `meetings_notes_meeting_index` (`meeting_id`),
            CONSTRAINT `meetings_notes_meeting_fk` FOREIGN KEY (`meeting_id`) REFERENCES `meetings_meetings`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
