<?php
/**
 * يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات.
 */
return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
        $exists = $pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'documents_documents' AND column_name = 'previous_status'"
        )->fetch();
        if (!$exists) {
            $pdo->exec("
                ALTER TABLE `documents_documents`
                ADD COLUMN `previous_status` ENUM('draft','pending_approval','approved','signed') NULL
                    COMMENT 'الحالة قبل الأرشفة، لاستعادة المستند إليها' AFTER `status`
            ");
        }
    }

    if (version_compare($fromVersion, '1.2.0', '<')) {
        $exists = $pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'documents_documents' AND column_name = 'follow_up_date'"
        )->fetch();
        if (!$exists) {
            $pdo->exec("
                ALTER TABLE `documents_documents`
                ADD COLUMN `follow_up_date` DATE NULL COMMENT 'تاريخ متابعة اختياري - يظهر كحدث بالتقويم الموحّد' AFTER `template_id`,
                ADD KEY `documents_follow_up_index` (`follow_up_date`)
            ");
        }
    }

    $colMissing = function (string $col) use ($pdo): bool {
        return !$pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'documents_documents' AND column_name = " . $pdo->quote($col)
        )->fetchColumn();
    };

    if (version_compare($fromVersion, '1.3.0', '<')) {
        // تصنيف السرية + انتهاء الصلاحية + رمز التحقق العام
        if ($colMissing('confidentiality')) {
            $pdo->exec("ALTER TABLE `documents_documents` ADD COLUMN `confidentiality` ENUM('normal','internal','confidential','secret') NOT NULL DEFAULT 'normal' COMMENT 'تصنيف السرية' AFTER `visibility`");
        }
        if ($colMissing('expiry_date')) {
            $pdo->exec("ALTER TABLE `documents_documents` ADD COLUMN `expiry_date` DATE NULL COMMENT 'تاريخ انتهاء صلاحية المستند' AFTER `follow_up_date`");
        }
        if ($colMissing('verify_token')) {
            $pdo->exec("ALTER TABLE `documents_documents` ADD COLUMN `verify_token` CHAR(32) NULL COMMENT 'رمز تحقق عام' AFTER `expiry_date`");
            $pdo->exec("ALTER TABLE `documents_documents` ADD UNIQUE KEY `documents_verify_token_unique` (`verify_token`)");
            // توليد رموز تحقق للمستندات الحالية التي لا تملك رمزاً
            $rows = $pdo->query("SELECT id FROM documents_documents WHERE verify_token IS NULL")->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare('UPDATE documents_documents SET verify_token = :t WHERE id = :id');
            foreach ($rows as $row) {
                $stmt->execute(['t' => bin2hex(random_bytes(16)), 'id' => $row['id']]);
            }
        }
    }

    if (version_compare($fromVersion, '1.4.0', '<')) {
        // سجل إصدارات محتوى المستند (لقطة قبل كل تعديل + استعادة)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `documents_versions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `document_id` INT UNSIGNED NOT NULL,
                `version_no` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `content` LONGTEXT NULL,
                `saved_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                KEY `documents_versions_document_index` (`document_id`),
                CONSTRAINT `documents_versions_document_fk` FOREIGN KEY (`document_id`) REFERENCES `documents_documents`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    if (version_compare($fromVersion, '1.5.0', '<')) {
        // التوقيع الشخصي للموقّع (لقطة من مكتبة تواقيعه) + ختم القالب من مكتبة الأختام
        if ($colMissing('signature_file')) {
            $pdo->exec("ALTER TABLE `documents_documents` ADD COLUMN `signature_file` VARCHAR(255) NULL COMMENT 'صورة توقيع الموقّع المختار وقت التوقيع (لقطة)' AFTER `signed_at`");
        }
        $stampCol = !$pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'documents_templates' AND column_name = 'stamp_id'"
        )->fetchColumn();
        if ($stampCol) {
            $pdo->exec("ALTER TABLE `documents_templates` ADD COLUMN `stamp_id` INT UNSIGNED NULL COMMENT 'ختم افتراضي لهذا القالب من مكتبة أختام الشركة' AFTER `background_image`");
        }
    }

    if (version_compare($fromVersion, '1.7.0', '<')) {
        // اعتماد متعدد المراحل + تعليقات المستندات
        $sMissing = !$pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'documents_settings' AND column_name = 'approval_steps'"
        )->fetchColumn();
        if ($sMissing) {
            $pdo->exec("ALTER TABLE `documents_settings` ADD COLUMN `approval_steps` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'عدد مراحل الاعتماد قبل التوقيع (1 أو 2)' AFTER `last_sequence`");
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `documents_approvals` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `document_id` INT UNSIGNED NOT NULL,
                `step_no` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `approved_by` INT UNSIGNED NOT NULL,
                `approved_at` DATETIME NOT NULL,
                `note` VARCHAR(255) NULL,
                KEY `documents_approvals_document_index` (`document_id`),
                CONSTRAINT `documents_approvals_document_fk` FOREIGN KEY (`document_id`) REFERENCES `documents_documents`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `documents_comments` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `document_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `body` TEXT NOT NULL,
                `created_at` DATETIME NOT NULL,
                KEY `documents_comments_document_index` (`document_id`),
                CONSTRAINT `documents_comments_document_fk` FOREIGN KEY (`document_id`) REFERENCES `documents_documents`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    if (version_compare($fromVersion, '1.6.0', '<')) {
        // رمز QR للتحقق: موضع (بكسل من الأسفل واليسار) وحجم ولون، لكل قالب
        $tcolMissing = fn (string $c): bool => !$pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'documents_templates' AND column_name = " . $pdo->quote($c)
        )->fetchColumn();
        if ($tcolMissing('qr_enabled')) {
            $pdo->exec("ALTER TABLE `documents_templates`
                ADD COLUMN `qr_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'إظهار رمز QR للتحقق على المستند',
                ADD COLUMN `qr_x` INT NOT NULL DEFAULT 40 COMMENT 'بكسل من يسار الصفحة',
                ADD COLUMN `qr_y` INT NOT NULL DEFAULT 40 COMMENT 'بكسل من أسفل الصفحة',
                ADD COLUMN `qr_size` INT NOT NULL DEFAULT 90 COMMENT 'حجم رمز QR بالبكسل',
                ADD COLUMN `qr_color` VARCHAR(7) NOT NULL DEFAULT '#000000' COMMENT 'لون رمز QR'");
        }
    }
};
