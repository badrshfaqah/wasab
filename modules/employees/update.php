<?php
/**
 * يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات.
 */
if (!function_exists('employees_add_column_if_missing')) {
    function employees_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        if (!$stmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
        // وثائق قابلة للانتهاء وحرجة نظاماً (الإقامة/الجواز/الشهادة الصحية/التأمين)
        employees_add_column_if_missing($pdo, 'employees_profiles', 'passport_number', "VARCHAR(50) NULL AFTER `driving_license_expiry`");
        employees_add_column_if_missing($pdo, 'employees_profiles', 'passport_expiry', "DATE NULL AFTER `passport_number`");
        employees_add_column_if_missing($pdo, 'employees_profiles', 'iqama_expiry', "DATE NULL AFTER `passport_expiry`");
        employees_add_column_if_missing($pdo, 'employees_profiles', 'health_cert_expiry', "DATE NULL AFTER `iqama_expiry`");
        employees_add_column_if_missing($pdo, 'employees_profiles', 'insurance_expiry', "DATE NULL AFTER `health_cert_expiry`");
    }

    if (version_compare($fromVersion, '1.4.0', '<')) {
        // مسير الرواتب المبسّط: مسير شهري + بنوده لكل موظف
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `employees_payroll_runs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `month` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
                `status` ENUM('draft','approved') NOT NULL DEFAULT 'draft',
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                `approved_by` INT UNSIGNED NULL,
                `approved_at` DATETIME NULL,
                UNIQUE KEY `employees_payroll_runs_unique` (`company_id`, `month`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `employees_payroll_items` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `run_id` INT UNSIGNED NOT NULL,
                `employee_id` INT UNSIGNED NOT NULL,
                `base_salary` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `allowances` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `deductions` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `deduction_note` VARCHAR(255) NULL,
                `net` DECIMAL(10,2) NOT NULL DEFAULT 0,
                KEY `employees_payroll_items_run_index` (`run_id`),
                KEY `employees_payroll_items_employee_index` (`employee_id`),
                CONSTRAINT `employees_payroll_items_run_fk` FOREIGN KEY (`run_id`) REFERENCES `employees_payroll_runs`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    if (version_compare($fromVersion, '1.3.0', '<')) {
        // الإجازات والأذونات + رصيد الإجازة السنوية
        employees_add_column_if_missing($pdo, 'employees_profiles', 'annual_leave_balance', "INT NOT NULL DEFAULT 30 COMMENT 'رصيد الإجازة السنوية بالأيام' AFTER `employment_type`");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `employees_leaves` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `employee_id` INT UNSIGNED NOT NULL,
                `type` ENUM('annual','sick','hours','unpaid','other') NOT NULL DEFAULT 'annual',
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `hours` DECIMAL(4,1) NULL COMMENT 'عدد ساعات الإذن (لنوع hours فقط)',
                `days_count` INT UNSIGNED NOT NULL DEFAULT 1,
                `reason` VARCHAR(500) NULL,
                `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `decided_by` INT UNSIGNED NULL,
                `decided_at` DATETIME NULL,
                `decision_note` VARCHAR(255) NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                KEY `employees_leaves_company_index` (`company_id`),
                KEY `employees_leaves_employee_index` (`employee_id`),
                KEY `employees_leaves_status_index` (`status`),
                KEY `employees_leaves_range_index` (`start_date`, `end_date`),
                CONSTRAINT `employees_leaves_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    if (version_compare($fromVersion, '1.2.0', '<')) {
        // سجل المخالفات والجزاءات التأديبية (حوكمة الموارد البشرية)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `employees_disciplinary` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `employee_id` INT UNSIGNED NOT NULL,
                `action_type` ENUM('verbal','written','deduction','suspension','final_warning','other') NOT NULL DEFAULT 'written',
                `incident_date` DATE NULL,
                `action_date` DATE NULL,
                `description` TEXT NOT NULL,
                `penalty` VARCHAR(255) NULL,
                `issued_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                KEY `employees_disciplinary_employee_index` (`employee_id`),
                KEY `employees_disciplinary_company_index` (`company_id`),
                CONSTRAINT `employees_disciplinary_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // تقييمات الأداء الدورية
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `employees_reviews` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `employee_id` INT UNSIGNED NOT NULL,
                `period` VARCHAR(60) NOT NULL,
                `review_date` DATE NULL,
                `overall_rating` TINYINT UNSIGNED NOT NULL DEFAULT 3,
                `strengths` TEXT NULL,
                `improvements` TEXT NULL,
                `goals` TEXT NULL,
                `reviewer_id` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                KEY `employees_reviews_employee_index` (`employee_id`),
                KEY `employees_reviews_company_index` (`company_id`),
                CONSTRAINT `employees_reviews_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
};
