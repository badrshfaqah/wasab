<?php
/**
 * يُستدعى عند الضغط على "تحديث" إن كان إصدار القرص أحدث من إصدار قاعدة البيانات.
 */
if (!function_exists('employees_add_column_if_missing')) {
    function employees_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
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
        // وثائق قابلة للانتهاء وحرجة نظاماً (الإقامة/الجواز/الشهادة الصحية/التأمين)
        employees_add_column_if_missing($pdo, 'employees_profiles', 'passport_number', "VARCHAR(50) NULL AFTER `driving_license_expiry`");
        employees_add_column_if_missing($pdo, 'employees_profiles', 'passport_expiry', "DATE NULL AFTER `passport_number`");
        employees_add_column_if_missing($pdo, 'employees_profiles', 'iqama_expiry', "DATE NULL AFTER `passport_expiry`");
        employees_add_column_if_missing($pdo, 'employees_profiles', 'health_cert_expiry', "DATE NULL AFTER `iqama_expiry`");
        employees_add_column_if_missing($pdo, 'employees_profiles', 'insurance_expiry', "DATE NULL AFTER `health_cert_expiry`");
    }
};
