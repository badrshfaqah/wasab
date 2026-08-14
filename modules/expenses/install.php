<?php
/** تثبيت إضافة المصروفات: جدول طلبات المصروف بمرفق فاتورة اختياري. */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `expenses_claims` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `expense_date` DATE NOT NULL,
            `description` VARCHAR(500) NOT NULL,
            `receipt_image` VARCHAR(255) NULL COMMENT 'صورة الفاتورة/الإيصال',
            `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            `decided_by` INT UNSIGNED NULL,
            `decided_at` DATETIME NULL,
            `decision_note` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            KEY `expenses_claims_company_index` (`company_id`),
            KEY `expenses_claims_user_index` (`user_id`),
            KEY `expenses_claims_status_index` (`status`),
            KEY `expenses_claims_date_index` (`expense_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
