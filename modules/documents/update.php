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
};
