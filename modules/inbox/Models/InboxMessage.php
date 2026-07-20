<?php

namespace Modules\Inbox\Models;

use App\Core\Database;

class InboxMessage
{
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT m.*, s.name AS site_name, s.url AS site_url, r.name AS reader_name
               FROM inbox_messages m
               LEFT JOIN inbox_sites s ON s.id = m.site_id
               LEFT JOIN users r ON r.id = m.read_by
              WHERE m.id = :id',
            ['id' => $id]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('inbox_messages', $data);
    }

    public static function delete(int $id): void
    {
        Database::delete('inbox_messages', 'id = :id', ['id' => $id]);
    }

    /** فلاتر القائمة المشتركة: نطاق القراءة + موقع مصدر + بحث نصي. */
    private static function buildFilters(int $companyId, string $scope, array $filters): array
    {
        $where = ['m.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if ($scope === 'unread') {
            $where[] = 'm.is_read = 0';
        } elseif ($scope === 'read') {
            $where[] = 'm.is_read = 1';
        }

        if (!empty($filters['site_id'])) {
            $where[] = 'm.site_id = :f_site';
            $params['f_site'] = (int) $filters['site_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(m.sender_name LIKE :q1 OR m.sender_email LIKE :q2 OR m.sender_phone LIKE :q3 OR m.subject LIKE :q4 OR m.body LIKE :q5)';
            $like = '%' . $filters['q'] . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }

        return [implode(' AND ', $where), $params];
    }

    public static function paginate(int $companyId, string $scope, int $page, int $perPage = 20, array $filters = []): array
    {
        [$whereSql, $params] = self::buildFilters($companyId, $scope, $filters);

        $total = (int) (Database::first("SELECT COUNT(*) AS c FROM inbox_messages m WHERE {$whereSql}", $params)['c'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT m.*, s.name AS site_name
               FROM inbox_messages m
               LEFT JOIN inbox_sites s ON s.id = m.site_id
              WHERE {$whereSql}
              ORDER BY m.id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::update(
            'inbox_messages',
            ['is_read' => 1, 'read_by' => $userId, 'read_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $id]
        );
    }

    public static function markUnread(int $id): void
    {
        Database::update(
            'inbox_messages',
            ['is_read' => 0, 'read_by' => null, 'read_at' => null],
            'id = :id',
            ['id' => $id]
        );
    }

    public static function unreadCount(int $companyId): int
    {
        return Database::count('inbox_messages', 'company_id = :c AND is_read = 0', ['c' => $companyId]);
    }

    public static function recent(int $companyId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        return Database::select(
            "SELECT m.*, s.name AS site_name
               FROM inbox_messages m
               LEFT JOIN inbox_sites s ON s.id = m.site_id
              WHERE m.company_id = :c
              ORDER BY m.id DESC
              LIMIT {$limit}",
            ['c' => $companyId]
        );
    }
}
