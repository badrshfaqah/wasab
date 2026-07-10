<?php

namespace Modules\Archive\Models;

use App\Core\Database;

class ArchiveFile
{
    public const MAX_BYTES = 25 * 1024 * 1024;

    public const EXTENSION_GROUPS = [
        'pdf' => ['pdf'],
        'word' => ['doc', 'docx'],
        'excel' => ['xls', 'xlsx'],
        'powerpoint' => ['ppt', 'pptx'],
        'image' => ['png', 'jpg', 'jpeg', 'webp', 'gif'],
        'zip' => ['zip'],
    ];

    private const ICONS = [
        'pdf' => '📕', 'doc' => '📘', 'docx' => '📘', 'xls' => '📗', 'xlsx' => '📗',
        'ppt' => '📙', 'pptx' => '📙', 'zip' => '🗜️',
        'png' => '🖼️', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'webp' => '🖼️', 'gif' => '🖼️',
    ];

    public static function allowedExtensions(): array
    {
        return array_merge(...array_values(self::EXTENSION_GROUPS));
    }

    public static function icon(string $extension): string
    {
        return self::ICONS[strtolower($extension)] ?? '📄';
    }

    public static function isImage(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSION_GROUPS['image'], true);
    }

    public static function isPdf(string $extension): bool
    {
        return strtolower($extension) === 'pdf';
    }

    public static function statusLabels(): array
    {
        return [
            'active' => 'نشط',
            'expired' => 'منتهي الصلاحية',
            'archived' => 'مؤرشف',
            'closed' => 'مغلق',
        ];
    }

    public static function visibilityLabels(): array
    {
        return [
            'inherit' => 'حسب التصنيف',
            'all' => 'جميع الموظفين',
            'specific_users' => 'مستخدمون محددون',
            'system_admin_only' => 'مدير النظام فقط',
            'company_admin_only' => 'مدير الشركة فقط',
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM archive_files WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('archive_files', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('archive_files', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('archive_files', 'id = :id', ['id' => $id]);
    }

    public static function incrementView(int $id): void
    {
        Database::pdo()->prepare('UPDATE archive_files SET view_count = view_count + 1 WHERE id = :id')->execute(['id' => $id]);
    }

    public static function incrementDownload(int $id): void
    {
        Database::pdo()->prepare('UPDATE archive_files SET download_count = download_count + 1 WHERE id = :id')->execute(['id' => $id]);
    }

    public static function setAccessUsers(int $fileId, array $userIds): void
    {
        Database::delete('archive_file_users', 'file_id = :id', ['id' => $fileId]);
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                Database::insert('archive_file_users', ['file_id' => $fileId, 'user_id' => $uid]);
            }
        }
    }

    public static function accessUserIds(int $fileId): array
    {
        return array_column(
            Database::select('SELECT user_id FROM archive_file_users WHERE file_id = :id', ['id' => $fileId]),
            'user_id'
        );
    }

    /**
     * فحص إمكانية مشاهدة مستخدم عادي لملف واحد (بعد التحقق من نفس الشركة وصلاحية
     * archive.view الأساسية في الكونترولر). system_admin يتجاوز كل شيء دائماً
     * (نفس قاعدة Permission::check العامة)، أما "مدير النظام فقط" فهي الاستثناء
     * الوحيد الذي لا يتجاوزه حتى مدير الشركة - وإلا لما كان لهذا الخيار أي معنى.
     */
    public static function isVisibleTo(array $file, array $category, int $userId, bool $isSystemAdmin, bool $canManage): bool
    {
        if ($isSystemAdmin) {
            return true;
        }

        $visibility = $file['visibility_type'] === 'inherit' ? $category['visibility_type'] : $file['visibility_type'];

        if ($visibility === 'system_admin_only') {
            return false;
        }
        if ($canManage) {
            return true;
        }
        if ($visibility === 'all') {
            return true;
        }
        if ($visibility === 'company_admin_only') {
            return false;
        }
        if ($visibility === 'specific_users') {
            if (in_array($userId, self::accessUserIds((int) $file['id']), true)) {
                return true;
            }
            if ($file['visibility_type'] === 'inherit') {
                return in_array($userId, ArchiveCategory::accessUserIds((int) $file['category_id']), true);
            }
        }

        return false;
    }

    /**
     * شرط SQL يطبّق نفس منطق isVisibleTo() على مستوى الاستعلام (للقوائم والودجت)،
     * حتى لا تظهر عناوين ملفات مقيّدة الوصول لمن لا يملك صلاحية مشاهدتها - تسريب
     * معلومة ولو لم يستطع فتح الملف فعلياً. مبنية كجزء WHERE يُدمج مع بقية الشروط،
     * ويفترض أن الاستعلام يستخدم alias "f" للملفات و"c" للتصنيفات (JOIN مطلوب).
     */
    private static function visibilitySql(int $userId, bool $isSystemAdmin, bool $canManage): array
    {
        if ($isSystemAdmin) {
            return ['1=1', []];
        }

        $canManageFlag = $canManage ? 1 : 0;
        $sql = "(
            (CASE WHEN f.visibility_type = 'inherit' THEN c.visibility_type ELSE f.visibility_type END) = 'all'
            OR (:vis_can_manage = 1 AND (CASE WHEN f.visibility_type = 'inherit' THEN c.visibility_type ELSE f.visibility_type END) != 'system_admin_only')
            OR (
                (CASE WHEN f.visibility_type = 'inherit' THEN c.visibility_type ELSE f.visibility_type END) = 'specific_users'
                AND (
                    EXISTS (SELECT 1 FROM archive_file_users fu WHERE fu.file_id = f.id AND fu.user_id = :vis_uid1)
                    OR (f.visibility_type = 'inherit' AND EXISTS (SELECT 1 FROM archive_category_users cu WHERE cu.category_id = f.category_id AND cu.user_id = :vis_uid2))
                )
            )
        )";

        // PDO في وضع native prepares (ATTR_EMULATE_PREPARES=false) لا يقبل إعادة استخدام
        // نفس اسم المعامل المسمّى أكثر من مرة بنفس الاستعلام - كل تكرار يحتاج اسماً مختلفاً
        // ولو كانت القيمة نفسها.
        return [$sql, ['vis_uid1' => $userId, 'vis_uid2' => $userId, 'vis_can_manage' => $canManageFlag]];
    }

    public static function paginate(int $companyId, int $userId, bool $isSystemAdmin, bool $canManage, array $filters, int $page, int $perPage): array
    {
        [$visSql, $visParams] = self::visibilitySql($userId, $isSystemAdmin, $canManage);
        [$where, $params] = self::buildFilters($companyId, $filters);
        $where .= ' AND ' . $visSql;
        $params = array_merge($params, $visParams);

        $total = Database::first(
            "SELECT COUNT(*) AS c FROM archive_files f JOIN archive_categories c ON c.id = f.category_id WHERE {$where}",
            $params
        )['c'] ?? 0;

        $sort = self::sortClause($filters['sort'] ?? 'date_desc');
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT f.*, u.name AS uploader_name, c.name AS category_name
               FROM archive_files f
               JOIN archive_categories c ON c.id = f.category_id
               LEFT JOIN users u ON u.id = f.created_by
              WHERE {$where}
              ORDER BY {$sort}
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => (int) $total];
    }

    private static function sortClause(string $sort): string
    {
        return match ($sort) {
            'name_asc' => 'f.original_name ASC',
            'name_desc' => 'f.original_name DESC',
            'date_asc' => 'f.created_at ASC',
            'size_asc' => 'f.size ASC',
            'size_desc' => 'f.size DESC',
            'modified_desc' => 'COALESCE(f.updated_at, f.created_at) DESC',
            default => 'f.created_at DESC',
        };
    }

    private static function buildFilters(int $companyId, array $filters): array
    {
        $where = 'f.company_id = :company_id';
        $params = ['company_id' => $companyId];

        if (!empty($filters['category_id'])) {
            $where .= ' AND f.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (f.original_name LIKE :q1 OR f.title LIKE :q2 OR f.description LIKE :q3 OR f.keywords LIKE :q4)';
            $needle = '%' . $filters['q'] . '%';
            $params['q1'] = $needle;
            $params['q2'] = $needle;
            $params['q3'] = $needle;
            $params['q4'] = $needle;
        }
        if (!empty($filters['status'])) {
            $where .= ' AND f.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['extension'])) {
            $where .= ' AND f.extension = :extension';
            $params['extension'] = $filters['extension'];
        }

        return [$where, $params];
    }

    private static function visibleRecent(int $companyId, int $userId, bool $isSystemAdmin, bool $canManage, string $where, array $extraParams, string $order, int $limit): array
    {
        [$visSql, $visParams] = self::visibilitySql($userId, $isSystemAdmin, $canManage);
        $params = array_merge(['company_id' => $companyId], $extraParams, $visParams);

        return Database::select(
            "SELECT f.*, c.name AS category_name
               FROM archive_files f
               JOIN archive_categories c ON c.id = f.category_id
              WHERE f.company_id = :company_id AND {$where} AND {$visSql}
              ORDER BY {$order}
              LIMIT {$limit}",
            $params
        );
    }

    public static function recent(int $companyId, int $userId, bool $isSystemAdmin, bool $canManage, int $limit = 5): array
    {
        return self::visibleRecent($companyId, $userId, $isSystemAdmin, $canManage, '1=1', [], 'f.created_at DESC', $limit);
    }

    public static function expiringSoon(int $companyId, int $userId, bool $isSystemAdmin, bool $canManage, int $withinDays, int $limit = 5): array
    {
        return self::visibleRecent(
            $companyId, $userId, $isSystemAdmin, $canManage,
            "f.status = 'active' AND f.expires_at IS NOT NULL AND f.expires_at <= DATE_ADD(CURDATE(), INTERVAL {$withinDays} DAY)",
            [],
            'f.expires_at ASC',
            $limit
        );
    }

    public static function mostViewed(int $companyId, int $userId, bool $isSystemAdmin, bool $canManage, int $limit = 5): array
    {
        return self::visibleRecent($companyId, $userId, $isSystemAdmin, $canManage, 'f.view_count > 0', [], 'f.view_count DESC', $limit);
    }

    public static function myUploads(int $companyId, int $userId, int $limit = 5): array
    {
        return Database::select(
            'SELECT f.*, c.name AS category_name
               FROM archive_files f
               JOIN archive_categories c ON c.id = f.category_id
              WHERE f.company_id = :c AND f.created_by = :u
              ORDER BY f.created_at DESC
              LIMIT ' . $limit,
            ['c' => $companyId, 'u' => $userId]
        );
    }

    public static function sharedWithMe(int $companyId, int $userId, int $limit = 5): array
    {
        return Database::select(
            "SELECT f.*, c.name AS category_name
               FROM archive_files f
               JOIN archive_categories c ON c.id = f.category_id
              WHERE f.company_id = :c
                AND f.created_by != :u1
                AND (
                    (f.visibility_type = 'specific_users' AND EXISTS (SELECT 1 FROM archive_file_users fu WHERE fu.file_id = f.id AND fu.user_id = :u2))
                    OR (f.visibility_type = 'inherit' AND c.visibility_type = 'specific_users' AND EXISTS (SELECT 1 FROM archive_category_users cu WHERE cu.category_id = f.category_id AND cu.user_id = :u3))
                )
              ORDER BY f.created_at DESC
              LIMIT {$limit}",
            ['c' => $companyId, 'u1' => $userId, 'u2' => $userId, 'u3' => $userId]
        );
    }

    public static function countExpiringSoon(int $companyId, int $withinDays): int
    {
        return Database::first(
            "SELECT COUNT(*) AS c FROM archive_files
              WHERE company_id = :c AND status = 'active' AND expires_at IS NOT NULL
                AND expires_at <= DATE_ADD(CURDATE(), INTERVAL {$withinDays} DAY)",
            ['c' => $companyId]
        )['c'] ?? 0;
    }

    /** يُستدعى دورياً (أو عند تحميل الصفحة الرئيسية) لتحويل الملفات المنتهية فعلياً لحالة "منتهي الصلاحية" تلقائياً. */
    public static function markExpiredPastDue(int $companyId): void
    {
        Database::pdo()->prepare(
            "UPDATE archive_files SET status = 'expired'
              WHERE company_id = :c AND status = 'active' AND expires_at IS NOT NULL AND expires_at < CURDATE()"
        )->execute(['c' => $companyId]);
    }
}
