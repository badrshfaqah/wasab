<?php
/** إزالة نهائية: حذف جداول الإضافة فقط. */
return function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS `checkins_entry_tasks`');
    $pdo->exec('DROP TABLE IF EXISTS `checkins_entries`');
};
