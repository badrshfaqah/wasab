<?php
/** ترقية جداول الإضافة عند تحديثها من إصدار أقدم. */
return function (PDO $pdo, string $fromVersion): void {
    // 1.1.0: عمود إقرار استلام الحامل بالمحضر (ميزة الإقرار)
    $col = $pdo->query("SHOW COLUMNS FROM `assets_handovers` LIKE 'acknowledged_at'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE `assets_handovers` ADD COLUMN `acknowledged_at` DATETIME NULL COMMENT 'وقت إقرار الحامل باستلام العهدة' AFTER `notes`");
    }
};
