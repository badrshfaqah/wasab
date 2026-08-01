<?php
/** يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات. */
return function (PDO $pdo, string $fromVersion): void {
    if (version_compare($fromVersion, '1.1.0', '<')) {
        // زرع القوالب الجديدة (إنذار/ترقية/نقل/تعميم) للشركات التي لديها بالفعل
        // قوالب (أي فعّلت النماذج)، دون تكرار ما هو موجود بالاسم، ودون لمس ما حُذف عمداً.
        $newNames = ['إنذار', 'قرار ترقية', 'قرار نقل', 'تعميم'];
        $all = require __DIR__ . '/default_templates.php';
        $newTemplates = array_values(array_filter($all, fn ($t) => in_array($t['name'], $newNames, true)));

        $companies = $pdo->query('SELECT DISTINCT company_id FROM forms_templates')->fetchAll(PDO::FETCH_COLUMN);
        $now = date('Y-m-d H:i:s');
        $check = $pdo->prepare('SELECT COUNT(*) FROM forms_templates WHERE company_id = :c AND name = :n');
        $ins = $pdo->prepare('INSERT INTO forms_templates (company_id, name, body, is_active, created_at) VALUES (:c, :n, :b, 1, :t)');
        foreach ($companies as $cid) {
            foreach ($newTemplates as $tpl) {
                $check->execute(['c' => $cid, 'n' => $tpl['name']]);
                if ((int) $check->fetchColumn() === 0) {
                    $ins->execute(['c' => $cid, 'n' => $tpl['name'], 'b' => $tpl['body'], 't' => $now]);
                }
            }
        }
    }

    if (version_compare($fromVersion, '1.2.0', '<')) {
        // رمز تحقق عام للخطابات (صفحة تحقق كالمستندات)
        $exists = $pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'forms_letters' AND column_name = 'verify_token'"
        )->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `forms_letters` ADD COLUMN `verify_token` CHAR(32) NULL COMMENT 'رمز تحقق عام' AFTER `body`");
            $pdo->exec("ALTER TABLE `forms_letters` ADD UNIQUE KEY `forms_letters_verify_token_unique` (`verify_token`)");
            // توليد رموز للخطابات الحالية
            $rows = $pdo->query("SELECT id FROM forms_letters WHERE verify_token IS NULL")->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare('UPDATE forms_letters SET verify_token = :t WHERE id = :id');
            foreach ($rows as $row) {
                $stmt->execute(['t' => bin2hex(random_bytes(16)), 'id' => $row['id']]);
            }
        }
    }
};
