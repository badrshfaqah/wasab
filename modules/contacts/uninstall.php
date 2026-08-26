<?php
/** إزالة دليل جهات الاتصال. */
return function (PDO $pdo): void {
    foreach (['contacts_numbers', 'contacts_person_orgs', 'contacts_persons', 'contacts_organizations'] as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
};
