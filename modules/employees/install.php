<?php
/**
 * تثبيت إضافة الملف الوظيفي: ينشئ جداولها الخاصة فقط، دون المساس بجداول النواة.
 * لا يوجد ربط FK بجدولي users/companies عمداً (استقلالية الإضافة عن النواة، ونفس
 * السبب الجوهري لتصميم هذه الإضافة: linked_user_id مجرد عمود عادي غير مقيّد بمفتاح
 * أجنبي، فلو حُذف حساب المستخدم لاحقاً يبقى الملف الوظيفي كاملاً دون أي أثر).
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees_profiles` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `linked_user_id` INT UNSIGNED NULL COMMENT 'ربط اختياري بحساب دخول موجود - عمود عادي بلا FK عمداً',
            `full_name` VARCHAR(200) NOT NULL,
            `photo` VARCHAR(255) NULL,
            `national_id` VARCHAR(50) NULL,
            `nationality` VARCHAR(100) NULL,
            `birth_date` DATE NULL,
            `gender` ENUM('male','female') NULL,
            `marital_status` ENUM('single','married','divorced','widowed') NULL,
            `phone` VARCHAR(50) NULL,
            `personal_email` VARCHAR(150) NULL,
            `address` VARCHAR(255) NULL,
            `emergency_contact_name` VARCHAR(150) NULL,
            `emergency_contact_phone` VARCHAR(50) NULL,
            `emergency_contact_relation` VARCHAR(100) NULL,
            `job_title` VARCHAR(150) NULL,
            `department` VARCHAR(150) NULL,
            `manager_employee_id` INT UNSIGNED NULL,
            `branch` VARCHAR(150) NULL,
            `hire_date` DATE NULL,
            `employment_type` ENUM('full_time','part_time','contract') NOT NULL DEFAULT 'full_time',
            `annual_leave_balance` INT NOT NULL DEFAULT 30 COMMENT 'رصيد الإجازة السنوية بالأيام - تُخصم منه الإجازات المعتمدة',
            `status` ENUM('active','on_leave','suspended','terminated') NOT NULL DEFAULT 'active',
            `contract_type` VARCHAR(100) NULL,
            `contract_start_date` DATE NULL,
            `contract_end_date` DATE NULL,
            `probation_end_date` DATE NULL,
            `salary_base` DECIMAL(10,2) NULL,
            `salary_allowances` DECIMAL(10,2) NULL,
            `bank_name` VARCHAR(150) NULL,
            `bank_iban` VARCHAR(50) NULL,
            `gosi_number` VARCHAR(50) NULL,
            `medical_insurance_provider` VARCHAR(150) NULL,
            `medical_insurance_policy_number` VARCHAR(100) NULL,
            `driving_license_number` VARCHAR(100) NULL,
            `driving_license_expiry` DATE NULL,
            `passport_number` VARCHAR(50) NULL,
            `passport_expiry` DATE NULL COMMENT 'انتهاء الجواز - يظهر ضمن تنبيهات الوثائق',
            `iqama_expiry` DATE NULL COMMENT 'انتهاء الإقامة - يظهر ضمن تنبيهات الوثائق',
            `health_cert_expiry` DATE NULL COMMENT 'انتهاء الشهادة الصحية - يظهر ضمن تنبيهات الوثائق',
            `insurance_expiry` DATE NULL COMMENT 'انتهاء التأمين الطبي - يظهر ضمن تنبيهات الوثائق',
            `skills` TEXT NULL,
            `languages` TEXT NULL,
            `admin_notes` TEXT NULL COMMENT 'خاصة بمن يدير الإضافة فقط - لا تظهر للموظف نفسه أبداً',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `employees_profiles_linked_user_unique` (`linked_user_id`),
            KEY `employees_profiles_company_index` (`company_id`),
            KEY `employees_profiles_manager_index` (`manager_employee_id`),
            KEY `employees_profiles_status_index` (`status`),
            CONSTRAINT `employees_profiles_manager_fk` FOREIGN KEY (`manager_employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees_dependents` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `relation` ENUM('spouse','child','other') NOT NULL DEFAULT 'child',
            `birth_date` DATE NULL,
            `created_at` DATETIME NOT NULL,
            KEY `employees_dependents_employee_index` (`employee_id`),
            CONSTRAINT `employees_dependents_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees_certifications` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `issuer` VARCHAR(150) NULL,
            `issue_date` DATE NULL,
            `expiry_date` DATE NULL COMMENT 'يظهر كتنبيه بالتقويم الموحّد قبل الانتهاء إن وُجد',
            `created_at` DATETIME NOT NULL,
            KEY `employees_certifications_employee_index` (`employee_id`),
            KEY `employees_certifications_expiry_index` (`expiry_date`),
            CONSTRAINT `employees_certifications_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees_disciplinary` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `employee_id` INT UNSIGNED NOT NULL,
            `action_type` ENUM('verbal','written','deduction','suspension','final_warning','other') NOT NULL DEFAULT 'written',
            `incident_date` DATE NULL,
            `action_date` DATE NULL,
            `description` TEXT NOT NULL,
            `penalty` VARCHAR(255) NULL COMMENT 'الجزاء المطبَّق (خصم/إيقاف...) نصاً',
            `issued_by` INT UNSIGNED NULL COMMENT 'المستخدم الذي سجّل الإجراء',
            `created_at` DATETIME NOT NULL,
            KEY `employees_disciplinary_employee_index` (`employee_id`),
            KEY `employees_disciplinary_company_index` (`company_id`),
            CONSTRAINT `employees_disciplinary_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees_reviews` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `employee_id` INT UNSIGNED NOT NULL,
            `period` VARCHAR(60) NOT NULL COMMENT 'فترة التقييم نصاً، مثل: 2024 أو الربع الأول 2024',
            `review_date` DATE NULL,
            `overall_rating` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'من 1 إلى 5',
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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees_documents` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `stored_name` VARCHAR(255) NOT NULL,
            `extension` VARCHAR(10) NULL,
            `size` INT UNSIGNED NULL,
            `uploaded_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            KEY `employees_documents_employee_index` (`employee_id`),
            CONSTRAINT `employees_documents_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees_timeline` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT UNSIGNED NOT NULL,
            `event_type` VARCHAR(50) NOT NULL DEFAULT 'note',
            `description` TEXT NOT NULL,
            `event_date` DATE NOT NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `employees_timeline_employee_index` (`employee_id`),
            CONSTRAINT `employees_timeline_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employees_profiles`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // طلبات الإجازات والأذونات: يقدّمها الموظف، يعتمدها/يرفضها المدير، وتُخصم
    // الإجازة السنوية المعتمدة من رصيد الموظف تلقائياً.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees_leaves` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `employee_id` INT UNSIGNED NOT NULL,
            `type` ENUM('annual','sick','hours','unpaid','other') NOT NULL DEFAULT 'annual',
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `hours` DECIMAL(4,1) NULL COMMENT 'عدد ساعات الإذن (لنوع hours فقط)',
            `days_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'عدد الأيام شاملاً الطرفين',
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

    // مسير الرواتب المبسّط: مسير شهري واحد لكل شركة/شهر وبنوده لكل موظف
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
};
