<?php

namespace Modules\Tasks\Models;

use App\Core\Database;

class Task
{
    public static function find(int $id): ?array
    {
        return Database::first(
            "SELECT t.*, a.name AS assignee_name, c.name AS creator_name, ap.name AS approver_name
               FROM tasks_tasks t
               LEFT JOIN users a ON a.id = t.assignee_id
               LEFT JOIN users c ON c.id = t.creator_id
               LEFT JOIN users ap ON ap.id = t.approver_id
              WHERE t.id = :id",
            ['id' => $id]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('tasks_tasks', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('tasks_tasks', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('tasks_tasks', 'id = :id', ['id' => $id]);
    }

    /**
     * يبني شروط WHERE + المعاملات المشتركة بين القائمة ولوحة كانبان:
     * النطاق (scope) + فلاتر اختيارية (بحث بالعنوان، مسؤول، أولوية، حالة).
     */
    private static function buildFilters(int $companyId, string $scope, int $userId, array $filters): array
    {
        $where = ['t.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        switch ($scope) {
            case 'mine':
                $where[] = 't.assignee_id = :uid';
                $params['uid'] = $userId;
                break;
            case 'created':
                $where[] = 't.creator_id = :uid';
                $params['uid'] = $userId;
                break;
            case 'overdue':
                $where[] = 't.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status NOT IN ("done","cancelled")';
                $where[] = '(t.assignee_id = :uid OR t.creator_id = :uid2)';
                $params['uid'] = $userId;
                $params['uid2'] = $userId;
                break;
            case 'approval':
                $where[] = 't.requires_approval = 1 AND t.approved_at IS NULL AND t.approver_id = :uid';
                $params['uid'] = $userId;
                break;
            case 'all':
            default:
                break;
        }

        if (!empty($filters['q'])) {
            $where[] = 't.title LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['assignee_id'])) {
            $where[] = 't.assignee_id = :f_assignee';
            $params['f_assignee'] = (int) $filters['assignee_id'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 't.priority = :f_priority';
            $params['f_priority'] = $filters['priority'];
        }
        if (!empty($filters['status'])) {
            $where[] = 't.status = :f_status';
            $params['f_status'] = $filters['status'];
        }

        return [implode(' AND ', $where), $params];
    }

    /** مفاتيح الفرز المسموحة (قائمة بيضاء تمنع حقن SQL). القيمة تعبير SQL أساسي. */
    public static function sortColumns(): array
    {
        return [
            'title' => 't.title',
            'assignee' => 'a.name',
            'priority' => "FIELD(t.priority,'low','medium','high','urgent')",
            'status' => "FIELD(t.status,'todo','in_progress','in_review','done','cancelled')",
            'due' => 't.due_date',
            'created' => 't.id',
        ];
    }

    /** يبني عبارة ORDER BY آمنة من مفتاح فرز موثوق واتجاه. */
    private static function orderBy(string $sort, string $dir): string
    {
        $cols = self::sortColumns();
        $expr = $cols[$sort] ?? $cols['due'];
        $d = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        // تاريخ الاستحقاق: القيم الفارغة دائماً في النهاية بصرف النظر عن الاتجاه
        if ($sort === 'due' || (!isset($cols[$sort]))) {
            return "t.due_date IS NULL, t.due_date {$d}, t.id DESC";
        }
        return "{$expr} {$d}, t.id DESC";
    }

    /**
     * قوائم المهام حسب النطاق: mine, created, overdue, approval, all، مع فلاتر اختيارية.
     */
    public static function paginate(int $companyId, string $scope, int $userId, int $page, int $perPage = 15, array $filters = [], string $sort = 'due', string $dir = 'asc'): array
    {
        [$whereSql, $params] = self::buildFilters($companyId, $scope, $userId, $filters);

        $total = (int) (Database::first("SELECT COUNT(*) AS c FROM tasks_tasks t WHERE {$whereSql}", $params)['c'] ?? 0);

        $orderBy = self::orderBy($sort, $dir);
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT t.*, a.name AS assignee_name, c.name AS creator_name
               FROM tasks_tasks t
               LEFT JOIN users a ON a.id = t.assignee_id
               LEFT JOIN users c ON c.id = t.creator_id
              WHERE {$whereSql}
              ORDER BY {$orderBy}
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * كل المهام المطابقة (بحد أقصى للأداء) مجمّعة حسب الحالة، لعرض لوحة كانبان.
     */
    public static function forBoard(int $companyId, string $scope, int $userId, array $filters = [], int $limit = 300): array
    {
        [$whereSql, $params] = self::buildFilters($companyId, $scope, $userId, $filters);
        $limit = max(1, min(500, $limit));

        $rows = Database::select(
            "SELECT t.*, a.name AS assignee_name, c.name AS creator_name
               FROM tasks_tasks t
               LEFT JOIN users a ON a.id = t.assignee_id
               LEFT JOIN users c ON c.id = t.creator_id
              WHERE {$whereSql}
              ORDER BY t.due_date IS NULL, t.due_date ASC, t.id DESC
              LIMIT {$limit}",
            $params
        );

        $columns = ['todo' => [], 'in_progress' => [], 'in_review' => [], 'done' => [], 'cancelled' => []];
        foreach ($rows as $row) {
            $columns[$row['status']][] = $row;
        }

        return $columns;
    }

    public static function countOverdue(int $companyId, int $userId): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS c FROM tasks_tasks
              WHERE company_id = :c AND due_date IS NOT NULL AND due_date < CURDATE()
                AND status NOT IN ("done","cancelled") AND (assignee_id = :u OR creator_id = :u2)',
            ['c' => $companyId, 'u' => $userId, 'u2' => $userId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function countPendingApproval(int $companyId, int $userId): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS c FROM tasks_tasks
              WHERE company_id = :c AND requires_approval = 1 AND approved_at IS NULL AND approver_id = :u',
            ['c' => $companyId, 'u' => $userId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function recentForUser(int $companyId, int $userId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        return Database::select(
            "SELECT * FROM tasks_tasks
              WHERE company_id = :c AND assignee_id = :u AND status NOT IN ('done','cancelled')
              ORDER BY due_date IS NULL, due_date ASC LIMIT {$limit}",
            ['c' => $companyId, 'u' => $userId]
        );
    }
}
