<?php
/**
 * يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات.
 */
if (!function_exists('meetings_add_column_if_missing')) {
    function meetings_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
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
        // إعادة تسمية نوع الاجتماع: داخلي/خارجي -> حضوري/اون لاين. نوسّع الـ ENUM أولاً
        // لتقبل القيم القديمة والجديدة معاً، ننقل البيانات، ثم نضيّقه للقيم الجديدة فقط.
        $pdo->exec("ALTER TABLE `meetings_meetings` MODIFY COLUMN `type` ENUM('internal','external','in_person','online') NOT NULL DEFAULT 'in_person'");
        $pdo->exec("UPDATE `meetings_meetings` SET `type` = 'in_person' WHERE `type` = 'internal'");
        $pdo->exec("UPDATE `meetings_meetings` SET `type` = 'online' WHERE `type` = 'external'");
        $pdo->exec("ALTER TABLE `meetings_meetings` MODIFY COLUMN `type` ENUM('in_person','online') NOT NULL DEFAULT 'in_person'");

        $tokenExists = $pdo->query(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'meetings_attendees' AND column_name = 'token'"
        )->fetchColumn();
        if (!$tokenExists) {
            $pdo->exec("ALTER TABLE `meetings_attendees` ADD COLUMN `token` VARCHAR(48) NULL COMMENT 'رابط تأكيد حضور بلا تسجيل دخول - يُولَّد للمدعوين الخارجيين فقط' AFTER `external_contact`");
            $pdo->exec("ALTER TABLE `meetings_attendees` ADD UNIQUE KEY `meetings_attendees_token_unique` (`token`)");
        }

        // توليد رموز للمدعوين الخارجيين الحاليين الذين لا يملكون رمزاً بعد (كانوا يُنشؤون قبل هذه الميزة)
        $rows = $pdo->query("SELECT id FROM meetings_attendees WHERE user_id IS NULL AND token IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('UPDATE meetings_attendees SET token = :t WHERE id = :id');
        foreach ($rows as $row) {
            $stmt->execute(['t' => bin2hex(random_bytes(24)), 'id' => $row['id']]);
        }
    }

    if (version_compare($fromVersion, '1.2.0', '<')) {
        meetings_add_column_if_missing($pdo, 'meetings_meetings', 'recurrence_rule', "ENUM('none','weekly','monthly') NOT NULL DEFAULT 'none' AFTER `status`");
        meetings_add_column_if_missing($pdo, 'meetings_meetings', 'recurrence_end_date', "DATE NULL AFTER `recurrence_rule`");
        meetings_add_column_if_missing($pdo, 'meetings_meetings', 'recurrence_group_id', "INT UNSIGNED NULL AFTER `recurrence_end_date`");
        meetings_add_column_if_missing($pdo, 'meetings_meetings', 'reminder_sent_at', "DATETIME NULL AFTER `recurrence_group_id`");

        $indexExists = $pdo->query(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'meetings_meetings' AND index_name = 'meetings_recurrence_group_index'"
        )->fetchColumn();
        if (!$indexExists) {
            $pdo->exec("ALTER TABLE `meetings_meetings` ADD KEY `meetings_recurrence_group_index` (`recurrence_group_id`)");
        }
    }
};
