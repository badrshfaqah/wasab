<?php

namespace Modules\Crm\Models;

use App\Core\Database;

/**
 * الجهة: كيان مركزي على مستوى الشركة يُسجَّل مرة واحدة (crm_organizations)،
 * وعلاقتها بكل مساحة صف مستقل (crm_workspace_orgs) يحمل تصنيفها ومسؤولها
 * وحالتها هناك. فشركة واحدة تكون «منظم فعاليات» في مساحة و«عميل محتمل» في
 * أخرى، دون تكرار بياناتها، ودون أن يرى أحد علاقتها بمساحة لا يصل إليها.
 */
class Organization
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM crm_organizations WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('crm_organizations', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('crm_organizations', $data, 'id = :id', ['id' => $id]);
    }

    /** بحث في الدليل المركزي (لاختيار جهة قائمة بدل تكرارها). */
    public static function search(int $companyId, string $q, int $limit = 20): array
    {
        return Database::select(
            "SELECT id, name, trade_name, city, sector FROM crm_organizations
              WHERE company_id = :c AND (name LIKE :q OR trade_name LIKE :q2 OR email LIKE :q3 OR phone LIKE :q4)
              ORDER BY name LIMIT {$limit}",
            ['c' => $companyId, 'q' => "%{$q}%", 'q2' => "%{$q}%", 'q3' => "%{$q}%", 'q4' => "%{$q}%"]
        );
    }

    /** جهات مشابهة بالاسم - لتنبيه المستخدم قبل إنشاء نسخة مكررة. */
    public static function possibleDuplicates(int $companyId, string $name, ?int $excludeId = null): array
    {
        $needle = mb_substr(trim($name), 0, 40);
        if ($needle === '') {
            return [];
        }
        $sql = "SELECT id, name, city FROM crm_organizations WHERE company_id = :c AND name LIKE :q";
        $params = ['c' => $companyId, 'q' => "%{$needle}%"];
        if ($excludeId) {
            $sql .= ' AND id != :ex';
            $params['ex'] = $excludeId;
        }
        return Database::select($sql . ' LIMIT 5', $params);
    }

    // ---------------- العلاقة بالمساحة ----------------

    public static function relation(int $workspaceId, int $organizationId): ?array
    {
        return Database::first(
            'SELECT * FROM crm_workspace_orgs WHERE workspace_id = :w AND organization_id = :o',
            ['w' => $workspaceId, 'o' => $organizationId]
        );
    }

    public static function findRelation(int $relationId): ?array
    {
        return Database::first('SELECT * FROM crm_workspace_orgs WHERE id = :id', ['id' => $relationId]);
    }

    /** ربط جهة قائمة بمساحة (أو إرجاع الربط الموجود). */
    public static function link(int $workspaceId, int $organizationId, array $data = []): int
    {
        $existing = self::relation($workspaceId, $organizationId);
        if ($existing) {
            if ($data) {
                Database::update('crm_workspace_orgs', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $existing['id']]);
            }
            return (int) $existing['id'];
        }
        return Database::insert('crm_workspace_orgs', $data + [
            'workspace_id' => $workspaceId,
            'organization_id' => $organizationId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function unlink(int $workspaceId, int $organizationId): void
    {
        Database::delete('crm_workspace_orgs', 'workspace_id = :w AND organization_id = :o', ['w' => $workspaceId, 'o' => $organizationId]);
    }

    /** جهات المساحة مع تصنيفاتها ومسؤولها - مع فلاتر البحث. */
    public static function inWorkspace(int $workspaceId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = self::buildFilters($workspaceId, $filters);
        return Database::select(
            "SELECT r.*, o.name, o.trade_name, o.sector, o.city, o.country, o.email, o.phone, o.logo,
                    u.name AS owner_name,
                    (SELECT GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ' · ')
                       FROM crm_org_categories oc JOIN crm_categories c ON c.id = oc.category_id
                      WHERE oc.workspace_org_id = r.id) AS categories
               FROM crm_workspace_orgs r
               JOIN crm_organizations o ON o.id = r.organization_id
               LEFT JOIN users u ON u.id = r.owner_id
              WHERE {$where}
              ORDER BY o.name
              LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countInWorkspace(int $workspaceId, array $filters = []): int
    {
        [$where, $params] = self::buildFilters($workspaceId, $filters);
        return (int) (Database::first(
            "SELECT COUNT(*) AS c FROM crm_workspace_orgs r JOIN crm_organizations o ON o.id = r.organization_id WHERE {$where}",
            $params
        )['c'] ?? 0);
    }

    private static function buildFilters(int $workspaceId, array $filters): array
    {
        $where = 'r.workspace_id = :w';
        $params = ['w' => $workspaceId];

        if (!empty($filters['q'])) {
            $where .= ' AND (o.name LIKE :q OR o.trade_name LIKE :q2 OR o.email LIKE :q3 OR o.phone LIKE :q4 OR o.city LIKE :q5)';
            foreach (['q', 'q2', 'q3', 'q4', 'q5'] as $k) {
                $params[$k] = '%' . $filters['q'] . '%';
            }
        }
        if (!empty($filters['category'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM crm_org_categories oc WHERE oc.workspace_org_id = r.id AND oc.category_id = :cat)';
            $params['cat'] = (int) $filters['category'];
        }
        if (!empty($filters['tag'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM crm_org_tags ot WHERE ot.workspace_org_id = r.id AND ot.tag_id = :tag)';
            $params['tag'] = (int) $filters['tag'];
        }
        if (!empty($filters['owner'])) {
            $where .= ' AND r.owner_id = :owner';
            $params['owner'] = (int) $filters['owner'];
        }
        if (!empty($filters['city'])) {
            $where .= ' AND o.city LIKE :city';
            $params['city'] = '%' . $filters['city'] . '%';
        }
        if (!empty($filters['sector'])) {
            $where .= ' AND o.sector LIKE :sector';
            $params['sector'] = '%' . $filters['sector'] . '%';
        }
        if (!empty($filters['list'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM crm_list_items li WHERE li.workspace_org_id = r.id AND li.list_id = :list)';
            $params['list'] = (int) $filters['list'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND r.relation_status LIKE :rstatus';
            $params['rstatus'] = '%' . $filters['status'] . '%';
        }
        if (!empty($filters['stale'])) {
            // لم يحدث تواصل منذ 30 يوماً أو أكثر (أو لم يبدأ أصلاً)
            $where .= ' AND (r.last_activity_at IS NULL OR r.last_activity_at < :stale)';
            $params['stale'] = date('Y-m-d H:i:s', strtotime('-30 days'));
        }
        if (!empty($filters['due'])) {
            // متابعات مستحقة اليوم أو متأخرة
            $where .= ' AND r.next_action_at IS NOT NULL AND r.next_action_at <= :due';
            $params['due'] = date('Y-m-d 23:59:59');
        }

        return [$where, $params];
    }

    /** المساحات التي ترتبط بها الجهة - تُفلتر لاحقاً بما يصل إليه المستخدم. */
    public static function workspacesOf(int $organizationId): array
    {
        return Database::select(
            'SELECT r.*, w.name AS workspace_name, w.icon, w.status
               FROM crm_workspace_orgs r JOIN crm_workspaces w ON w.id = r.workspace_id
              WHERE r.organization_id = :o ORDER BY w.name',
            ['o' => $organizationId]
        );
    }

    public static function categoriesOf(int $relationId): array
    {
        return Database::select(
            'SELECT c.* FROM crm_org_categories oc JOIN crm_categories c ON c.id = oc.category_id
              WHERE oc.workspace_org_id = :r ORDER BY c.sort_order, c.name',
            ['r' => $relationId]
        );
    }

    // ---------------- الوسوم ----------------

    public static function tagsOf(int $relationId): array
    {
        return Database::select(
            'SELECT t.* FROM crm_org_tags ot JOIN crm_tags t ON t.id = ot.tag_id
              WHERE ot.workspace_org_id = :r ORDER BY t.name',
            ['r' => $relationId]
        );
    }

    /** يضيف وسماً للعلاقة، ويُنشئه في المساحة إن كان جديداً. */
    public static function addTag(int $workspaceId, int $relationId, string $name): void
    {
        $name = mb_substr(ltrim(trim($name), '#'), 0, 80);
        if ($name === '') {
            return;
        }
        $tag = Database::first(
            'SELECT id FROM crm_tags WHERE workspace_id = :w AND name = :n',
            ['w' => $workspaceId, 'n' => $name]
        );
        $tagId = $tag['id'] ?? Database::insert('crm_tags', [
            'workspace_id' => $workspaceId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $linked = Database::first(
            'SELECT tag_id FROM crm_org_tags WHERE workspace_org_id = :r AND tag_id = :t',
            ['r' => $relationId, 't' => $tagId]
        );
        if (!$linked) {
            Database::insert('crm_org_tags', ['workspace_org_id' => $relationId, 'tag_id' => $tagId]);
        }
    }

    public static function removeTag(int $relationId, int $tagId): void
    {
        Database::delete('crm_org_tags', 'workspace_org_id = :r AND tag_id = :t', ['r' => $relationId, 't' => $tagId]);
    }

    /** كل وسوم المساحة مع عدد استخدامها (للفلاتر والإعدادات). */
    public static function workspaceTags(int $workspaceId): array
    {
        return Database::select(
            'SELECT t.*, (SELECT COUNT(*) FROM crm_org_tags ot WHERE ot.tag_id = t.id) AS uses
               FROM crm_tags t WHERE t.workspace_id = :w ORDER BY t.name',
            ['w' => $workspaceId]
        );
    }

    public static function setCategories(int $relationId, array $categoryIds): void
    {
        Database::delete('crm_org_categories', 'workspace_org_id = :r', ['r' => $relationId]);
        foreach (array_unique(array_map('intval', $categoryIds)) as $cid) {
            if ($cid > 0) {
                Database::insert('crm_org_categories', ['workspace_org_id' => $relationId, 'category_id' => $cid]);
            }
        }
    }
}
