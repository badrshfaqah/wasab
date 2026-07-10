<?php
/**
 * يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات.
 * لا توجد ترقيات بعد في الإصدار 1.0.0.
 */
return function (PDO $pdo, string $fromVersion): void {
    // مثال مستقبلي:
    // if (version_compare($fromVersion, '1.1.0', '<')) { $pdo->exec('ALTER TABLE tasks_tasks ADD COLUMN ...'); }
};
