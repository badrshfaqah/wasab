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

    if (version_compare($fromVersion, '2.0.0', '<')) {
        /*
         * الجهات انتقلت إلى دليل «جهات الاتصال» المشترك: CRM يحتفظ بطبقة العلاقة
         * وحدها. ننقل ما لديه من جهات وأشخاص إلى الدليل (بمطابقة الاسم فلا نكرر
         * ما هو مسجّل هناك)، ثم نعيد تأشير معرّفات العلاقات والأنشطة والفرص.
         */
        $tableExists = fn (string $t): bool => (bool) $pdo->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($t)
        )->fetchColumn();

        if (!$tableExists('contacts_organizations')) {
            // الدليل غير مثبَّت بعد: نؤجّل الترحيل - يتولاه التثبيت التالي
            return;
        }

        $now = date('Y-m-d H:i:s');
        $orgMap = [];
        $personMap = [];

        if ($tableExists('crm_organizations')) {
            $orgs = $pdo->query('SELECT * FROM crm_organizations')->fetchAll(PDO::FETCH_ASSOC);
            $findOrg = $pdo->prepare('SELECT id FROM contacts_organizations WHERE company_id = ? AND name = ? LIMIT 1');
            $insertOrg = $pdo->prepare(
                'INSERT INTO contacts_organizations
                    (company_id, name, trade_name, logo, sector, country, city, address, website, email, phone, social_json, custom_json, notes, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach ($orgs as $o) {
                $findOrg->execute([$o['company_id'], $o['name']]);
                $existing = $findOrg->fetchColumn();
                if ($existing) {
                    $orgMap[(int) $o['id']] = (int) $existing;
                    continue;
                }
                $insertOrg->execute([
                    $o['company_id'], $o['name'], $o['trade_name'], $o['logo'], $o['sector'], $o['country'], $o['city'],
                    $o['address'], $o['website'], $o['email'], $o['phone'], $o['social_json'], $o['custom_json'] ?? null,
                    $o['notes'], $o['created_by'], $o['created_at'] ?: $now,
                ]);
                $orgMap[(int) $o['id']] = (int) $pdo->lastInsertId();
            }
        }

        if ($tableExists('crm_contacts')) {
            $contacts = $pdo->query('SELECT * FROM crm_contacts')->fetchAll(PDO::FETCH_ASSOC);
            $findPerson = $pdo->prepare('SELECT id FROM contacts_persons WHERE company_id = ? AND full_name = ? LIMIT 1');
            $insertPerson = $pdo->prepare(
                'INSERT INTO contacts_persons (company_id, full_name, job_title, mobile, phone, email, linkedin, notes, status, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $link = $pdo->prepare(
                'INSERT IGNORE INTO contacts_person_orgs (person_id, organization_id, job_title, department, is_primary, created_at)
                 VALUES (?,?,?,?,0,?)'
            );
            foreach ($contacts as $c) {
                $findPerson->execute([$c['company_id'], $c['name']]);
                $existing = $findPerson->fetchColumn();
                if ($existing) {
                    $personId = (int) $existing;
                } else {
                    $insertPerson->execute([
                        $c['company_id'], $c['name'], $c['job_title'], $c['mobile'], $c['phone'], $c['email'],
                        $c['linkedin'], $c['notes'], $c['status'] === 'inactive' ? 'archived' : 'active',
                        $c['created_by'], $c['created_at'] ?: $now,
                    ]);
                    $personId = (int) $pdo->lastInsertId();
                }
                $personMap[(int) $c['id']] = $personId;
                $newOrgId = $orgMap[(int) $c['organization_id']] ?? null;
                if ($newOrgId) {
                    $link->execute([$personId, $newOrgId, $c['job_title'], $c['department'], $now]);
                }
            }
        }

        // إعادة تأشير المعرّفات في طبقة العلاقة
        $remap = function (string $table, string $column, array $map) use ($pdo): void {
            if (!$map) {
                return;
            }
            $stmt = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = ? WHERE `{$column}` = ?");
            foreach ($map as $old => $new) {
                if ($old !== $new) {
                    $stmt->execute([$new, $old]);
                }
            }
        };
        // إسقاط المفاتيح الأجنبية القديمة قبل تغيير القيم
        foreach ([
            ['crm_workspace_orgs', 'crm_workspace_orgs_org_fk'],
            ['crm_activities', 'crm_activities_org_fk'],
            ['crm_opportunities', 'crm_opportunities_org_fk'],
            ['crm_contacts', 'crm_contacts_org_fk'],
        ] as [$table, $constraint]) {
            $exists = $pdo->query(
                "SELECT 1 FROM information_schema.table_constraints
                  WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table) . "
                    AND constraint_name = " . $pdo->quote($constraint)
            )->fetchColumn();
            if ($exists) {
                $pdo->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }
        }

        $remap('crm_workspace_orgs', 'organization_id', $orgMap);
        $remap('crm_activities', 'organization_id', $orgMap);
        $remap('crm_opportunities', 'organization_id', $orgMap);
        $remap('crm_activities', 'contact_id', $personMap);
        $remap('crm_opportunities', 'contact_id', $personMap);

        // روابط المهام والأرشيف تشير الآن إلى نوع الدليل
        if ($tableExists('tasks_tasks')) {
            $stmt = $pdo->prepare("UPDATE tasks_tasks SET linked_type = 'contact_org', linked_id = ? WHERE linked_type = 'crm_org' AND linked_id = ?");
            foreach ($orgMap as $old => $new) {
                $stmt->execute([$new, $old]);
            }
            $pdo->exec("UPDATE tasks_tasks SET linked_type = 'contact_org' WHERE linked_type = 'crm_org'");
        }
        if ($tableExists('archive_file_links')) {
            $stmt = $pdo->prepare("UPDATE archive_file_links SET linked_module = 'contact_org', linked_id = ? WHERE linked_module = 'crm' AND linked_id = ?");
            foreach ($orgMap as $old => $new) {
                $stmt->execute([$new, $old]);
            }
            $pdo->exec("UPDATE archive_file_links SET linked_module = 'contact_org' WHERE linked_module = 'crm'");
        }

        $pdo->exec('DROP TABLE IF EXISTS `crm_contacts`');
        $pdo->exec('DROP TABLE IF EXISTS `crm_organizations`');
    }
};
