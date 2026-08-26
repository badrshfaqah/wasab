<?php
/**
 * تثبيت إضافة CRM. الفكرة المعمارية:
 *
 *   crm_organizations       دليل الجهات المركزي للشركة - الجهة تُسجَّل مرة واحدة.
 *   crm_workspace_orgs      علاقة الجهة بمساحة معيّنة (تصنيف/مسؤول/حالة/ملاحظات).
 *   crm_workspaces          المساحات، وكل مساحة بيئة مستقلة بأعضائها وإعداداتها.
 *
 * فالشركة الواحدة تكون «منظم فعاليات» في مساحة و«عميل محتمل» في أخرى، ببيانات
 * أساسية واحدة لا تتكرر. لا توجد مفاتيح أجنبية نحو users/companies عمداً (كبقية
 * الإضافات) لتبقى الإضافة مستقلة وسهلة الإزالة.
 */
return function (PDO $pdo): void {
    // ---------- المساحات وأعضاؤها ----------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_workspaces` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `description` VARCHAR(500) NULL,
            `icon` VARCHAR(16) NOT NULL DEFAULT '🤝' COMMENT 'رمز تعبيري يميّز المساحة في القوائم',
            `color` VARCHAR(7) NOT NULL DEFAULT '#2563eb',
            `manager_id` INT UNSIGNED NULL COMMENT 'المسؤول عن المساحة',
            `status` ENUM('active','archived') NOT NULL DEFAULT 'active',
            `settings_json` TEXT NULL COMMENT 'إعدادات المساحة وحقول الجهات المخصصة (JSON)',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `crm_workspaces_company_index` (`company_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_workspace_members` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `workspace_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `role` ENUM('manager','member','viewer') NOT NULL DEFAULT 'member',
            `abilities_json` TEXT NULL COMMENT 'صلاحيات تفصيلية تتجاوز الدور الافتراضي (JSON)',
            `created_at` DATETIME NOT NULL,
            UNIQUE KEY `crm_workspace_members_unique` (`workspace_id`, `user_id`),
            KEY `crm_workspace_members_user_index` (`user_id`),
            CONSTRAINT `crm_workspace_members_ws_fk` FOREIGN KEY (`workspace_id`) REFERENCES `crm_workspaces`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ---------- دليل الجهات المركزي ----------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_organizations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `trade_name` VARCHAR(200) NULL COMMENT 'الاسم التجاري إن اختلف',
            `logo` VARCHAR(255) NULL,
            `description` TEXT NULL,
            `sector` VARCHAR(120) NULL,
            `country` VARCHAR(80) NULL,
            `city` VARCHAR(80) NULL,
            `address` VARCHAR(255) NULL,
            `website` VARCHAR(200) NULL,
            `email` VARCHAR(150) NULL,
            `phone` VARCHAR(50) NULL,
            `social_json` TEXT NULL COMMENT 'حسابات التواصل (JSON)',
            `custom_json` TEXT NULL COMMENT 'حقول مخصصة تُعرّفها المساحات (JSON)',
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `crm_organizations_company_index` (`company_id`),
            KEY `crm_organizations_name_index` (`company_id`, `name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // أشخاص الجهة: تابعون للجهة نفسها فيراهم كل من يصل إليها من أي مساحة
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_contacts` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `organization_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `job_title` VARCHAR(150) NULL,
            `department` VARCHAR(150) NULL,
            `mobile` VARCHAR(50) NULL,
            `phone` VARCHAR(50) NULL,
            `email` VARCHAR(150) NULL,
            `linkedin` VARCHAR(255) NULL,
            `notes` TEXT NULL,
            `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `crm_contacts_org_index` (`organization_id`),
            CONSTRAINT `crm_contacts_org_fk` FOREIGN KEY (`organization_id`) REFERENCES `crm_organizations`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ---------- علاقة الجهة بالمساحة ----------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_workspace_orgs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `workspace_id` INT UNSIGNED NOT NULL,
            `organization_id` INT UNSIGNED NOT NULL,
            `owner_id` INT UNSIGNED NULL COMMENT 'المسؤول عن العلاقة داخل هذه المساحة',
            `relation_status` VARCHAR(60) NULL COMMENT 'حالة العلاقة داخل المساحة (نص حر يضبطه الفريق)',
            `notes` TEXT NULL,
            `last_activity_at` DATETIME NULL COMMENT 'آخر تواصل - للفرز والفلترة',
            `next_action_at` DATETIME NULL COMMENT 'موعد المتابعة القادمة',
            `added_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `crm_workspace_orgs_unique` (`workspace_id`, `organization_id`),
            KEY `crm_workspace_orgs_org_index` (`organization_id`),
            KEY `crm_workspace_orgs_next_index` (`workspace_id`, `next_action_at`),
            CONSTRAINT `crm_workspace_orgs_ws_fk` FOREIGN KEY (`workspace_id`) REFERENCES `crm_workspaces`(`id`) ON DELETE CASCADE,
            CONSTRAINT `crm_workspace_orgs_org_fk` FOREIGN KEY (`organization_id`) REFERENCES `crm_organizations`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // تصنيفات المساحة (منظم فعاليات / راعٍ / Lead ...) وربطها بالعلاقة - متعددة
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_categories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `workspace_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `color` VARCHAR(7) NOT NULL DEFAULT '#6b7280',
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            KEY `crm_categories_ws_index` (`workspace_id`),
            CONSTRAINT `crm_categories_ws_fk` FOREIGN KEY (`workspace_id`) REFERENCES `crm_workspaces`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_org_categories` (
            `workspace_org_id` INT UNSIGNED NOT NULL,
            `category_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`workspace_org_id`, `category_id`),
            CONSTRAINT `crm_org_categories_rel_fk` FOREIGN KEY (`workspace_org_id`) REFERENCES `crm_workspace_orgs`(`id`) ON DELETE CASCADE,
            CONSTRAINT `crm_org_categories_cat_fk` FOREIGN KEY (`category_id`) REFERENCES `crm_categories`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // وسوم حرة داخل المساحة
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_tags` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `workspace_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(80) NOT NULL,
            `created_at` DATETIME NOT NULL,
            UNIQUE KEY `crm_tags_unique` (`workspace_id`, `name`),
            CONSTRAINT `crm_tags_ws_fk` FOREIGN KEY (`workspace_id`) REFERENCES `crm_workspaces`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_org_tags` (
            `workspace_org_id` INT UNSIGNED NOT NULL,
            `tag_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`workspace_org_id`, `tag_id`),
            CONSTRAINT `crm_org_tags_rel_fk` FOREIGN KEY (`workspace_org_id`) REFERENCES `crm_workspace_orgs`(`id`) ON DELETE CASCADE,
            CONSTRAINT `crm_org_tags_tag_fk` FOREIGN KEY (`tag_id`) REFERENCES `crm_tags`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ---------- خطوط العمل والفرص ----------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_pipelines` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `workspace_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            KEY `crm_pipelines_ws_index` (`workspace_id`),
            CONSTRAINT `crm_pipelines_ws_fk` FOREIGN KEY (`workspace_id`) REFERENCES `crm_workspaces`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_stages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `pipeline_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `color` VARCHAR(7) NOT NULL DEFAULT '#6b7280',
            `outcome` ENUM('open','won','lost') NOT NULL DEFAULT 'open' COMMENT 'المراحل النهائية تحدد نتيجة الفرصة',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            KEY `crm_stages_pipeline_index` (`pipeline_id`, `sort_order`),
            CONSTRAINT `crm_stages_pipeline_fk` FOREIGN KEY (`pipeline_id`) REFERENCES `crm_pipelines`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_opportunities` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `workspace_id` INT UNSIGNED NOT NULL,
            `organization_id` INT UNSIGNED NOT NULL,
            `contact_id` INT UNSIGNED NULL,
            `pipeline_id` INT UNSIGNED NOT NULL,
            `stage_id` INT UNSIGNED NULL,
            `name` VARCHAR(200) NOT NULL,
            `owner_id` INT UNSIGNED NULL,
            `value` DECIMAL(14,2) NULL COMMENT 'القيمة المتوقعة - اختيارية',
            `probability` TINYINT UNSIGNED NULL COMMENT 'احتمالية النجاح %',
            `expected_close_date` DATE NULL,
            `source` VARCHAR(120) NULL,
            `description` TEXT NULL,
            `status` ENUM('open','won','lost') NOT NULL DEFAULT 'open',
            `closed_at` DATETIME NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            KEY `crm_opportunities_ws_index` (`workspace_id`, `status`),
            KEY `crm_opportunities_org_index` (`organization_id`),
            KEY `crm_opportunities_stage_index` (`stage_id`),
            CONSTRAINT `crm_opportunities_ws_fk` FOREIGN KEY (`workspace_id`) REFERENCES `crm_workspaces`(`id`) ON DELETE CASCADE,
            CONSTRAINT `crm_opportunities_org_fk` FOREIGN KEY (`organization_id`) REFERENCES `crm_organizations`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_opportunity_members` (
            `opportunity_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`opportunity_id`, `user_id`),
            CONSTRAINT `crm_opportunity_members_opp_fk` FOREIGN KEY (`opportunity_id`) REFERENCES `crm_opportunities`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ---------- الأنشطة وسجل العلاقة ----------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_activities` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `workspace_id` INT UNSIGNED NOT NULL,
            `organization_id` INT UNSIGNED NOT NULL,
            `contact_id` INT UNSIGNED NULL,
            `opportunity_id` INT UNSIGNED NULL,
            `type` VARCHAR(30) NOT NULL DEFAULT 'note' COMMENT 'email/call/whatsapp/meeting/note/followup/proposal/file/visit/stage_change',
            `subject` VARCHAR(200) NULL,
            `body` TEXT NULL,
            `outcome` VARCHAR(255) NULL COMMENT 'نتيجة النشاط',
            `occurred_at` DATETIME NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `next_action_at` DATETIME NULL,
            `next_action_note` VARCHAR(255) NULL,
            `next_action_owner_id` INT UNSIGNED NULL COMMENT 'المسؤول عن المتابعة - قد يختلف عن مسجّل النشاط',
            `next_action_status` ENUM('none','pending','done') NOT NULL DEFAULT 'none',
            `task_id` INT UNSIGNED NULL COMMENT 'المهمة المُنشأة للمتابعة في إضافة المهام',
            `created_at` DATETIME NOT NULL,
            KEY `crm_activities_org_index` (`organization_id`, `occurred_at`),
            KEY `crm_activities_ws_index` (`workspace_id`, `occurred_at`),
            KEY `crm_activities_next_index` (`workspace_id`, `next_action_status`, `next_action_at`),
            CONSTRAINT `crm_activities_ws_fk` FOREIGN KEY (`workspace_id`) REFERENCES `crm_workspaces`(`id`) ON DELETE CASCADE,
            CONSTRAINT `crm_activities_org_fk` FOREIGN KEY (`organization_id`) REFERENCES `crm_organizations`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ---------- القوائم ----------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_lists` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `workspace_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `description` VARCHAR(500) NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            KEY `crm_lists_ws_index` (`workspace_id`),
            CONSTRAINT `crm_lists_ws_fk` FOREIGN KEY (`workspace_id`) REFERENCES `crm_workspaces`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_list_items` (
            `list_id` INT UNSIGNED NOT NULL,
            `workspace_org_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`list_id`, `workspace_org_id`),
            CONSTRAINT `crm_list_items_list_fk` FOREIGN KEY (`list_id`) REFERENCES `crm_lists`(`id`) ON DELETE CASCADE,
            CONSTRAINT `crm_list_items_rel_fk` FOREIGN KEY (`workspace_org_id`) REFERENCES `crm_workspace_orgs`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ---------- سجل التغييرات ----------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `crm_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT UNSIGNED NOT NULL,
            `workspace_id` INT UNSIGNED NULL,
            `user_id` INT UNSIGNED NULL,
            `action` VARCHAR(50) NOT NULL,
            `entity_type` VARCHAR(30) NOT NULL,
            `entity_id` INT UNSIGNED NULL,
            `description` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL,
            KEY `crm_logs_ws_index` (`workspace_id`, `created_at`),
            KEY `crm_logs_entity_index` (`entity_type`, `entity_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
