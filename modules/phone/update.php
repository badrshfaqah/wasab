<?php
/**
 * يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات.
 */
return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `phone_contacts` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `type` ENUM('internal','external') NOT NULL,
                `linked_user_id` INT UNSIGNED NULL COMMENT 'للنوع الداخلي فقط: يشير لمستخدم بنفس الشركة، والرقم يُقرأ حياً من phone_users عنده وقت العرض',
                `name` VARCHAR(150) NOT NULL,
                `phone_number` VARCHAR(50) NULL COMMENT 'للنوع الخارجي فقط',
                `notes` VARCHAR(255) NULL,
                `visibility` ENUM('public','private') NOT NULL DEFAULT 'private',
                `created_by` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                KEY `phone_contacts_company_index` (`company_id`),
                KEY `phone_contacts_visibility_index` (`visibility`),
                KEY `phone_contacts_creator_index` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
};
