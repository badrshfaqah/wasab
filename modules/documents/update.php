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
};
