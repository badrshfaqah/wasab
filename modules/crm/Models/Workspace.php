<?php

namespace Modules\Crm\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Permission;

/**
 * مساحة CRM: بيئة مستقلة بأعضائها وصلاحياتها وتصنيفاتها ومراحلها.
 *
 * الصلاحية هنا طبقتان: صلاحية الدخول للوحدة (crm.view) من نظام وصاب، ثم عضوية
 * المساحة نفسها - فوجود المستخدم في النظام لا يعني رؤيته لكل المساحات. ولكل عضو
 * دور (مدير/عضو/مشاهد) يعطي مجموعة قدرات افتراضية، ويمكن تجاوزها بقدرات محددة
 * تُحفظ في abilities_json فيبقى النموذج قابلاً للتوسع دون تغيير الجداول.
 */
class Workspace
{
    /** القدرات داخل المساحة - أي إضافة مستقبلية تُضاف هنا وتُعرض تلقائياً. */
    public const ABILITIES = [
        'orgs.create' => 'إضافة جهات',
        'orgs.edit' => 'تعديل الجهات',
        'orgs.delete' => 'إزالة الجهات من المساحة',
        'contacts.manage' => 'إدارة جهات الاتصال',
        'opportunities.create' => 'إنشاء الفرص',
        'opportunities.edit' => 'تعديل الفرص',
        'activities.create' => 'تسجيل الأنشطة والمتابعات',
        'activities.view_others' => 'مشاهدة أنشطة الآخرين',
        'pipeline.manage' => 'إدارة مراحل العمل',
        'settings.manage' => 'إدارة التصنيفات وإعدادات المساحة',
        'reports.view' => 'مشاهدة التقارير',
        'export' => 'تصدير البيانات',
    ];

    /** القدرات الافتراضية لكل دور - مدير المساحة يملك كل شيء دائماً. */
    public const ROLE_DEFAULTS = [
        'manager' => ['*'],
        'member' => [
            'orgs.create', 'orgs.edit', 'contacts.manage',
            'opportunities.create', 'opportunities.edit',
            'activities.create', 'activities.view_others', 'reports.view',
        ],
        'viewer' => ['activities.view_others', 'reports.view'],
    ];

    public static function roleLabels(): array
    {
        return ['manager' => 'مدير المساحة', 'member' => 'عضو', 'viewer' => 'مشاهد'];
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM crm_workspaces WHERE id = :id', ['id' => $id]);
    }

    /** هل المستخدم مدير للنظام/الشركة أو يملك إدارة CRM كاملة؟ */
    public static function isAdmin(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('crm.manage');
    }

    /** المساحات التي يحق للمستخدم دخولها (المدير يرى الكل). */
    public static function forUser(int $companyId, int $userId, bool $includeArchived = false): array
    {
        $statusSql = $includeArchived ? '' : " AND w.status = 'active'";
        if (self::isAdmin()) {
            return Database::select(
                "SELECT w.*, 'manager' AS my_role,
                        (SELECT COUNT(*) FROM crm_workspace_members m WHERE m.workspace_id = w.id) AS members_count,
                        (SELECT COUNT(*) FROM crm_workspace_orgs o WHERE o.workspace_id = w.id) AS orgs_count
                   FROM crm_workspaces w
                  WHERE w.company_id = :c{$statusSql}
                  ORDER BY w.name",
                ['c' => $companyId]
            );
        }
        return Database::select(
            "SELECT w.*, m.role AS my_role,
                    (SELECT COUNT(*) FROM crm_workspace_members m2 WHERE m2.workspace_id = w.id) AS members_count,
                    (SELECT COUNT(*) FROM crm_workspace_orgs o WHERE o.workspace_id = w.id) AS orgs_count
               FROM crm_workspaces w
               JOIN crm_workspace_members m ON m.workspace_id = w.id AND m.user_id = :u
              WHERE w.company_id = :c{$statusSql}
              ORDER BY w.name",
            ['c' => $companyId, 'u' => $userId]
        );
    }

    /** عضوية المستخدم في مساحة (أو null). المدير يُعتبر مدير مساحة ضمنياً. */
    public static function membership(int $workspaceId, int $userId): ?array
    {
        $row = Database::first(
            'SELECT * FROM crm_workspace_members WHERE workspace_id = :w AND user_id = :u',
            ['w' => $workspaceId, 'u' => $userId]
        );
        if ($row) {
            return $row;
        }
        return self::isAdmin() ? ['workspace_id' => $workspaceId, 'user_id' => $userId, 'role' => 'manager', 'abilities_json' => null] : null;
    }

    /** هل يملك العضو قدرة معيّنة داخل المساحة؟ */
    public static function can(?array $membership, string $ability): bool
    {
        if (!$membership) {
            return false;
        }
        if (($membership['role'] ?? '') === 'manager') {
            return true;
        }
        $extra = $membership['abilities_json'] ? (array) json_decode((string) $membership['abilities_json'], true) : null;
        $abilities = $extra !== null && $extra !== []
            ? $extra
            : (self::ROLE_DEFAULTS[$membership['role'] ?? 'viewer'] ?? []);
        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /** قدرات العضو الفعلية (لعرضها في شاشة الأعضاء). */
    public static function abilitiesOf(array $membership): array
    {
        if (($membership['role'] ?? '') === 'manager') {
            return array_keys(self::ABILITIES);
        }
        $extra = $membership['abilities_json'] ? (array) json_decode((string) $membership['abilities_json'], true) : null;
        return $extra !== null && $extra !== [] ? $extra : (self::ROLE_DEFAULTS[$membership['role'] ?? 'viewer'] ?? []);
    }

    public static function members(int $workspaceId): array
    {
        return Database::select(
            'SELECT m.*, u.name AS user_name, u.email
               FROM crm_workspace_members m JOIN users u ON u.id = m.user_id
              WHERE m.workspace_id = :w ORDER BY FIELD(m.role, "manager", "member", "viewer"), u.name',
            ['w' => $workspaceId]
        );
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('crm_workspaces', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('crm_workspaces', $data, 'id = :id', ['id' => $id]);
    }

    public static function setMember(int $workspaceId, int $userId, string $role, ?array $abilities = null): void
    {
        $existing = Database::first(
            'SELECT id FROM crm_workspace_members WHERE workspace_id = :w AND user_id = :u',
            ['w' => $workspaceId, 'u' => $userId]
        );
        $payload = ['role' => $role, 'abilities_json' => $abilities ? json_encode(array_values($abilities), JSON_UNESCAPED_UNICODE) : null];
        if ($existing) {
            Database::update('crm_workspace_members', $payload, 'id = :id', ['id' => $existing['id']]);
            return;
        }
        Database::insert('crm_workspace_members', $payload + [
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function removeMember(int $workspaceId, int $userId): void
    {
        Database::delete('crm_workspace_members', 'workspace_id = :w AND user_id = :u', ['w' => $workspaceId, 'u' => $userId]);
    }
}
