<?php

namespace App\Core;

class ActivityLog
{
    public static function log(string $action, string $subjectType, $subjectId, string $description): void
    {
        $user = Auth::user();
        Database::insert('activity_log', [
            'user_id' => $user['id'] ?? null,
            'company_id' => $user['company_id'] ?? null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => (string) $subjectId,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * يبني شرط WHERE + المعاملات من فلاتر موثوقة (كلها معاملات مُقيَّدة - لا حقن).
     * $companyId إن مُرِّر يقصر النتائج على شركة واحدة (مدير الشركة)؛ null = كل الشركات
     * (مدير النظام)، مع إتاحة فلتر company_id اختياري له.
     */
    private static function buildWhere(?int $companyId, array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if ($companyId !== null) {
            $where[] = 'al.company_id = :cid';
            $params['cid'] = $companyId;
        } elseif (!empty($filters['company_id'])) {
            $where[] = 'al.company_id = :cid';
            $params['cid'] = (int) $filters['company_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'al.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['subject_type'])) {
            $where[] = 'al.subject_type = :stype';
            $params['stype'] = $filters['subject_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'al.created_at >= :dfrom';
            $params['dfrom'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'al.created_at <= :dto';
            $params['dto'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['q'])) {
            $where[] = '(al.description LIKE :q OR al.action LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    public static function paginate(int $page = 1, int $perPage = 20, ?int $companyId = null, array $filters = []): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        [$where, $params] = self::buildWhere($companyId, $filters);

        $total = (int) (Database::first("SELECT COUNT(*) AS c FROM activity_log al WHERE {$where}", $params)['c'] ?? 0);
        $rows = Database::select(
            "SELECT al.*, u.name AS user_name
               FROM activity_log al
               LEFT JOIN users u ON u.id = al.user_id
              WHERE {$where}
              ORDER BY al.id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /** كل الصفوف المطابقة للفلاتر (بحد أقصى للتصدير) - للتصدير كـ CSV للامتثال. */
    public static function forExport(?int $companyId, array $filters = [], int $limit = 5000): array
    {
        [$where, $params] = self::buildWhere($companyId, $filters);
        $limit = max(1, min(20000, $limit));
        return Database::select(
            "SELECT al.*, u.name AS user_name, c.name AS company_name
               FROM activity_log al
               LEFT JOIN users u ON u.id = al.user_id
               LEFT JOIN companies c ON c.id = al.company_id
              WHERE {$where}
              ORDER BY al.id DESC
              LIMIT {$limit}",
            $params
        );
    }

    /** قيم الفلاتر المتاحة (عمليات، أنواع، مستخدمون) ضمن نطاق الشركة. */
    public static function filterOptions(?int $companyId): array
    {
        $where = '1=1';
        $params = [];
        if ($companyId !== null) {
            $where = 'company_id = :cid';
            $params['cid'] = $companyId;
        }

        $actions = array_column(
            Database::select("SELECT DISTINCT action FROM activity_log WHERE {$where} ORDER BY action", $params),
            'action'
        );
        $subjectTypes = array_column(
            Database::select("SELECT DISTINCT subject_type FROM activity_log WHERE {$where} AND subject_type IS NOT NULL AND subject_type <> '' ORDER BY subject_type", $params),
            'subject_type'
        );

        $users = $companyId !== null
            ? Database::select('SELECT id, name FROM users WHERE company_id = :c ORDER BY name', ['c' => $companyId])
            : [];

        // مدير النظام: قائمة الشركات لفلترة السجل عبرها
        $companies = $companyId === null
            ? Database::select('SELECT id, name FROM companies ORDER BY name')
            : [];

        return ['actions' => $actions, 'subject_types' => $subjectTypes, 'users' => $users, 'companies' => $companies];
    }
}
