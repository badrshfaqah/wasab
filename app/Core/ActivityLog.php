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

    public static function paginate(int $page = 1, int $perPage = 20, ?int $companyId = null): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];
        if ($companyId !== null) {
            $where = 'company_id = :cid';
            $params['cid'] = $companyId;
        }

        $total = Database::count('activity_log', $where, $params);
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
}
