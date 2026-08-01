<?php
return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
        // مؤشر المعنويات: عمود اختياري لكل سجل متابعة يومية
        $exists = $pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'checkins_entries' AND column_name = 'mood'"
        )->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `checkins_entries` ADD COLUMN `mood` TINYINT UNSIGNED NULL COMMENT 'معنويات اليوم: 1 (مرهق) .. 5 (ممتاز) - اختياري' AFTER `user_id`");
        }
    }
};
