<?php

namespace Modules\Contacts\Models;

use App\Core\Database;
use App\Core\ModuleManager;

/**
 * دليل جهات الاتصال: الجهات والأفراد وربطهما.
 *
 * قاعدة الدليل: الطرف يُسجَّل مرة واحدة. الجهة لا تُنشأ بلا شخص تواصل واحد على
 * الأقل (فجهة بلا من نكلّمه سجلٌّ ميت)، والفرد يُضاف مستقلاً ويُربط لاحقاً بعدة
 * جهات - ومسمّاه صفة للعلاقة لا للفرد، فقد يكون مديراً هنا ومستشاراً هناك.
 */
class Directory
{
    // ---------------- الجهات ----------------

    public static function findOrg(int $id): ?array
    {
        return Database::first('SELECT * FROM contacts_organizations WHERE id = :id', ['id' => $id]);
    }

    public static function createOrg(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = Database::insert('contacts_organizations', $data);
        self::syncNumbers((int) $data['company_id'], $id, null, [
            ['raw' => $data['phone'] ?? '', 'label' => 'هاتف الجهة'],
        ]);
        return $id;
    }

    public static function updateOrg(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('contacts_organizations', $data, 'id = :id', ['id' => $id]);
        $org = self::findOrg($id);
        if ($org) {
            self::syncNumbers((int) $org['company_id'], $id, null, [
                ['raw' => $org['phone'] ?? '', 'label' => 'هاتف الجهة'],
            ]);
        }
    }

    public static function organizations(int $companyId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = self::orgFilters($companyId, $filters);
        return Database::select(
            "SELECT o.*,
                    (SELECT COUNT(*) FROM contacts_person_orgs po WHERE po.organization_id = o.id) AS people_count,
                    (SELECT p.full_name FROM contacts_person_orgs po JOIN contacts_persons p ON p.id = po.person_id
                      WHERE po.organization_id = o.id ORDER BY po.is_primary DESC, p.full_name LIMIT 1) AS primary_person
               FROM contacts_organizations o
              WHERE {$where}
              ORDER BY o.name LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countOrganizations(int $companyId, array $filters = []): int
    {
        [$where, $params] = self::orgFilters($companyId, $filters);
        return (int) (Database::first("SELECT COUNT(*) AS c FROM contacts_organizations o WHERE {$where}", $params)['c'] ?? 0);
    }

    private static function orgFilters(int $companyId, array $filters): array
    {
        $where = 'o.company_id = :c';
        $params = ['c' => $companyId];
        if (empty($filters['archived'])) {
            $where .= " AND o.status = 'active'";
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (o.name LIKE :q OR o.trade_name LIKE :q2 OR o.email LIKE :q3 OR o.phone LIKE :q4 OR o.city LIKE :q5)';
            foreach (['q', 'q2', 'q3', 'q4', 'q5'] as $k) {
                $params[$k] = '%' . $filters['q'] . '%';
            }
        }
        if (!empty($filters['kind'])) {
            $where .= ' AND o.kind = :kind';
            $params['kind'] = $filters['kind'];
        }
        if (!empty($filters['city'])) {
            $where .= ' AND o.city LIKE :city';
            $params['city'] = '%' . $filters['city'] . '%';
        }
        return [$where, $params];
    }

    // ---------------- الأفراد ----------------

    public static function findPerson(int $id): ?array
    {
        return Database::first('SELECT * FROM contacts_persons WHERE id = :id', ['id' => $id]);
    }

    public static function createPerson(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = Database::insert('contacts_persons', $data);
        self::syncNumbers((int) $data['company_id'], null, $id, [
            ['raw' => $data['mobile'] ?? '', 'label' => 'جوال'],
            ['raw' => $data['phone'] ?? '', 'label' => 'هاتف'],
        ]);
        return $id;
    }

    public static function updatePerson(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('contacts_persons', $data, 'id = :id', ['id' => $id]);
        $person = self::findPerson($id);
        if ($person) {
            self::syncNumbers((int) $person['company_id'], null, $id, [
                ['raw' => $person['mobile'] ?? '', 'label' => 'جوال'],
                ['raw' => $person['phone'] ?? '', 'label' => 'هاتف'],
            ]);
        }
    }

    public static function persons(int $companyId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = self::personFilters($companyId, $filters);
        return Database::select(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM contacts_person_orgs po WHERE po.person_id = p.id) AS orgs_count,
                    (SELECT o.name FROM contacts_person_orgs po JOIN contacts_organizations o ON o.id = po.organization_id
                      WHERE po.person_id = p.id ORDER BY po.is_primary DESC, o.name LIMIT 1) AS main_org
               FROM contacts_persons p
              WHERE {$where}
              ORDER BY p.full_name LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countPersons(int $companyId, array $filters = []): int
    {
        [$where, $params] = self::personFilters($companyId, $filters);
        return (int) (Database::first("SELECT COUNT(*) AS c FROM contacts_persons p WHERE {$where}", $params)['c'] ?? 0);
    }

    private static function personFilters(int $companyId, array $filters): array
    {
        $where = 'p.company_id = :c';
        $params = ['c' => $companyId];
        if (empty($filters['archived'])) {
            $where .= " AND p.status = 'active'";
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (p.full_name LIKE :q OR p.email LIKE :q2 OR p.mobile LIKE :q3 OR p.job_title LIKE :q4)';
            foreach (['q', 'q2', 'q3', 'q4'] as $k) {
                $params[$k] = '%' . $filters['q'] . '%';
            }
        }
        if (!empty($filters['org'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM contacts_person_orgs po WHERE po.person_id = p.id AND po.organization_id = :org)';
            $params['org'] = (int) $filters['org'];
        }
        if (!empty($filters['standalone'])) {
            $where .= ' AND NOT EXISTS (SELECT 1 FROM contacts_person_orgs po WHERE po.person_id = p.id)';
        }
        return [$where, $params];
    }

    // ---------------- الربط بين الفرد والجهة ----------------

    /** أشخاص الجهة بمسمّياتهم فيها. */
    public static function peopleOf(int $organizationId): array
    {
        return Database::select(
            'SELECT p.*, po.job_title AS role_title, po.department AS role_department, po.is_primary, po.id AS link_id
               FROM contacts_person_orgs po JOIN contacts_persons p ON p.id = po.person_id
              WHERE po.organization_id = :o
              ORDER BY po.is_primary DESC, p.full_name',
            ['o' => $organizationId]
        );
    }

    /** جهات الفرد بمسمّاه في كل واحدة. */
    public static function orgsOf(int $personId): array
    {
        return Database::select(
            'SELECT o.*, po.job_title AS role_title, po.department AS role_department, po.is_primary, po.id AS link_id
               FROM contacts_person_orgs po JOIN contacts_organizations o ON o.id = po.organization_id
              WHERE po.person_id = :p
              ORDER BY po.is_primary DESC, o.name',
            ['p' => $personId]
        );
    }

    public static function link(int $personId, int $organizationId, ?string $jobTitle = null, ?string $department = null, bool $isPrimary = false): void
    {
        $existing = Database::first(
            'SELECT id FROM contacts_person_orgs WHERE person_id = :p AND organization_id = :o',
            ['p' => $personId, 'o' => $organizationId]
        );
        $payload = ['job_title' => $jobTitle ?: null, 'department' => $department ?: null, 'is_primary' => $isPrimary ? 1 : 0];
        if ($existing) {
            Database::update('contacts_person_orgs', $payload, 'id = :id', ['id' => $existing['id']]);
        } else {
            Database::insert('contacts_person_orgs', $payload + [
                'person_id' => $personId,
                'organization_id' => $organizationId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        if ($isPrimary) {
            Database::update(
                'contacts_person_orgs',
                ['is_primary' => 0],
                'organization_id = :o AND person_id != :p',
                ['o' => $organizationId, 'p' => $personId]
            );
        }
    }

    public static function unlink(int $personId, int $organizationId): void
    {
        Database::delete('contacts_person_orgs', 'person_id = :p AND organization_id = :o', ['p' => $personId, 'o' => $organizationId]);
    }

    // ---------------- الأرقام ----------------

    public static function normalizeNumber(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        // نحتفظ بآخر 9 أرقام للمطابقة (تتجاوز اختلاف مفتاح الدولة وصفر البداية)
        return $digits === '' ? '' : substr($digits, -9);
    }

    /** يعيد بناء فهرس أرقام طرف معيّن. */
    public static function syncNumbers(int $companyId, ?int $organizationId, ?int $personId, array $numbers): void
    {
        if ($organizationId) {
            Database::delete('contacts_numbers', 'organization_id = :o', ['o' => $organizationId]);
        }
        if ($personId) {
            Database::delete('contacts_numbers', 'person_id = :p', ['p' => $personId]);
        }
        foreach ($numbers as $n) {
            $raw = trim((string) ($n['raw'] ?? ''));
            $normalized = self::normalizeNumber($raw);
            if ($normalized === '') {
                continue;
            }
            Database::insert('contacts_numbers', [
                'company_id' => $companyId,
                'normalized' => $normalized,
                'raw' => mb_substr($raw, 0, 50),
                'label' => $n['label'] ?? null,
                'organization_id' => $organizationId,
                'person_id' => $personId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * من صاحب هذا الرقم؟ - الواجهة التي تستفيد منها إضافة التلفون لاحقاً
     * لتعريف المتصل، وأي إضافة أخرى (فواتير، رسائل...) لربط رقم بطرف معروف.
     */
    public static function resolveByNumber(int $companyId, string $raw): ?array
    {
        $normalized = self::normalizeNumber($raw);
        if ($normalized === '') {
            return null;
        }
        $row = Database::first(
            "SELECT n.*, o.name AS org_name, p.full_name AS person_name
               FROM contacts_numbers n
               LEFT JOIN contacts_organizations o ON o.id = n.organization_id
               LEFT JOIN contacts_persons p ON p.id = n.person_id
              WHERE n.company_id = :c AND n.normalized = :n
              ORDER BY n.person_id IS NULL LIMIT 1",
            ['c' => $companyId, 'n' => $normalized]
        );
        if (!$row) {
            return null;
        }
        return [
            'type' => $row['person_id'] ? 'person' : 'organization',
            'id' => (int) ($row['person_id'] ?: $row['organization_id']),
            'name' => $row['person_name'] ?: $row['org_name'],
            'url' => $row['person_id']
                ? route('/contacts/people/' . (int) $row['person_id'])
                : route('/contacts/orgs/' . (int) $row['organization_id']),
        ];
    }

    // ---------------- ما يرتبط بالطرف من بقية الإضافات ----------------

    /**
     * كل ما يخص هذا الطرف عبر وصاب: مهام، ملفات أرشيف، وعلاقاته في CRM -
     * تُجمع عند الطلب من الإضافات المفعّلة فقط، فلا يتعطّل شيء إن عُطّلت إحداها.
     */
    public static function linkedItems(int $companyId, string $type, int $id): array
    {
        $out = ['tasks' => [], 'files' => [], 'crm' => []];
        $linkType = $type === 'organization' ? 'contact_org' : 'contact_person';

        if (ModuleManager::isActive('tasks')) {
            $out['tasks'] = Database::select(
                "SELECT id, title, status, due_date FROM tasks_tasks
                  WHERE company_id = :c AND linked_type = :t AND linked_id = :i
                  ORDER BY status = 'done', due_date IS NULL, due_date LIMIT 20",
                ['c' => $companyId, 't' => $linkType, 'i' => $id]
            );
        }
        if (ModuleManager::isActive('archive')) {
            $out['files'] = Database::select(
                "SELECT f.id, f.title, f.created_at FROM archive_file_links l
                   JOIN archive_files f ON f.id = l.file_id
                  WHERE l.linked_module = :m AND l.linked_id = :i
                  ORDER BY f.id DESC LIMIT 20",
                ['m' => $linkType, 'i' => $id]
            );
        }
        if ($type === 'organization' && ModuleManager::isActive('crm')) {
            $out['crm'] = Database::select(
                "SELECT r.id, r.workspace_id, w.name AS workspace_name, w.icon, r.last_activity_at, r.next_action_at
                   FROM crm_workspace_orgs r JOIN crm_workspaces w ON w.id = r.workspace_id
                  WHERE r.organization_id = :o ORDER BY w.name",
                ['o' => $id]
            );
        }
        return $out;
    }
}
