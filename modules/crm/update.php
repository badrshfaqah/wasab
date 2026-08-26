<?php
/** ترقيات إضافة CRM. */
return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
        // المتابعة تخص من أُسندت إليه لا من سجّل النشاط
        $has = $pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'crm_activities' AND column_name = 'next_action_owner_id'"
        )->fetchColumn();
        if (!$has) {
            $pdo->exec("ALTER TABLE `crm_activities` ADD COLUMN `next_action_owner_id` INT UNSIGNED NULL COMMENT 'المسؤول عن المتابعة - قد يختلف عن مسجّل النشاط' AFTER `next_action_note`");
            $pdo->exec("UPDATE `crm_activities` SET `next_action_owner_id` = `user_id` WHERE `next_action_owner_id` IS NULL");
        }
    }
};
