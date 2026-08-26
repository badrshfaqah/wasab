<?php
/**
 * دليل جهات الاتصال: طرفان خارجيان تتعامل معهما الشركة.
 *
 *   contacts_organizations  الجهة (شركة/مؤسسة/جهة حكومية...) ببياناتها.
 *   contacts_persons        الفرد بذاته - قد يكون مستقلاً بلا جهة إطلاقاً.
 *   contacts_person_orgs    الربط بينهما: الفرد يعمل في أكثر من جهة، وبمسمّى
 *                           مختلف في كل واحدة، فالمسمّى صفة للعلاقة لا للفرد.
 *   contacts_numbers        فهرس أرقام مطبَّع (بلا رموز ولا مسافات) لمطابقة
 *                           الرقم الوارد بصاحبه لاحقاً في التلفون والفواتير.
 *
 * الدليل مستقل عن أي إضافة: تبقى بياناته وإن عُطّلت إدارة العلاقات أو غيرها.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `contacts_organizations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `trade_name` VARCHAR(200) NULL,
            `kind` VARCHAR(60) NULL COMMENT 'طبيعة الجهة: شركة، مؤسسة، جهة حكومية، جمعية...',
            `logo` VARCHAR(255) NULL,
            `sector` VARCHAR(120) NULL,
            `country` VARCHAR(80) NULL,
            `city` VARCHAR(80) NULL,
            `address` VARCHAR(255) NULL,
            `website` VARCHAR(200) NULL,
            `email` VARCHAR(150) NULL,
            `phone` VARCHAR(50) NULL,
            `social_json` TEXT NULL,
            `custom_json` TEXT NULL,
            `notes` TEXT NULL,
            `status` ENUM('active','archived') NOT NULL DEFAULT 'active',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `contacts_orgs_company_index` (`company_id`, `status`),
            KEY `contacts_orgs_name_index` (`company_id`, `name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `contacts_persons` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `full_name` VARCHAR(150) NOT NULL,
            `job_title` VARCHAR(150) NULL COMMENT 'المسمّى العام - وداخل كل جهة مسمّاه الخاص',
            `mobile` VARCHAR(50) NULL,
            `phone` VARCHAR(50) NULL,
            `email` VARCHAR(150) NULL,
            `linkedin` VARCHAR(255) NULL,
            `city` VARCHAR(80) NULL,
            `notes` TEXT NULL,
            `status` ENUM('active','archived') NOT NULL DEFAULT 'active',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `contacts_persons_company_index` (`company_id`, `status`),
            KEY `contacts_persons_name_index` (`company_id`, `full_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `contacts_person_orgs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `person_id` INT UNSIGNED NOT NULL,
            `organization_id` INT UNSIGNED NOT NULL,
            `job_title` VARCHAR(150) NULL COMMENT 'مسمّاه في هذه الجهة تحديداً',
            `department` VARCHAR(150) NULL,
            `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'شخص التواصل الرئيسي للجهة',
            `created_at` DATETIME NOT NULL,
            UNIQUE KEY `contacts_person_orgs_unique` (`person_id`, `organization_id`),
            KEY `contacts_person_orgs_org_index` (`organization_id`),
            CONSTRAINT `contacts_person_orgs_person_fk` FOREIGN KEY (`person_id`) REFERENCES `contacts_persons`(`id`) ON DELETE CASCADE,
            CONSTRAINT `contacts_person_orgs_org_fk` FOREIGN KEY (`organization_id`) REFERENCES `contacts_organizations`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `contacts_numbers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `normalized` VARCHAR(32) NOT NULL COMMENT 'الرقم بلا رموز - للمطابقة السريعة',
            `raw` VARCHAR(50) NOT NULL,
            `label` VARCHAR(60) NULL,
            `organization_id` INT UNSIGNED NULL,
            `person_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `contacts_numbers_lookup` (`company_id`, `normalized`),
            KEY `contacts_numbers_org_index` (`organization_id`),
            KEY `contacts_numbers_person_index` (`person_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
