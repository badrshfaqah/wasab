<?php
/** ترقية جداول الإضافة عند تحديثها من إصدار أقدم. */
return function (PDO $pdo, string $fromVersion): void {
    // 1.1.0: عمود إقرار استلام الحامل بالمحضر (ميزة الإقرار)
    $col = $pdo->query("SHOW COLUMNS FROM `assets_handovers` LIKE 'acknowledged_at'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE `assets_handovers` ADD COLUMN `acknowledged_at` DATETIME NULL COMMENT 'وقت إقرار الحامل باستلام العهدة' AFTER `notes`");
    }

    if (version_compare($fromVersion, '1.2.0', '<')) {
        // حقول مخصصة حسب التصنيف: أسماء الحقول على التصنيف، والقيم على الأصل
        $c1 = $pdo->query("SHOW COLUMNS FROM `assets_categories` LIKE 'fields_json'")->fetch();
        if (!$c1) {
            $pdo->exec("ALTER TABLE `assets_categories` ADD COLUMN `fields_json` TEXT NULL COMMENT 'أسماء الحقول المخصصة لهذا التصنيف (JSON)' AFTER `name`");
        }
        $c2 = $pdo->query("SHOW COLUMNS FROM `assets_assets` LIKE 'custom_json'")->fetch();
        if (!$c2) {
            $pdo->exec("ALTER TABLE `assets_assets` ADD COLUMN `custom_json` TEXT NULL COMMENT 'قيم الحقول المخصصة (JSON)' AFTER `notes`");
        }
    }
};
