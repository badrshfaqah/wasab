<?php

namespace Modules\Documents\Models;

use App\Core\Database;

class Document
{
    public static function typeLabels(): array
    {
        return [
            'general' => 'عام',
            'letter' => 'خطاب',
            'decision' => 'قرار',
            'certificate' => 'شهادة',
            'authorization' => 'تفويض',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'draft' => 'مسودة',
            'pending_approval' => 'بانتظار الموافقة',
            'approved' => 'معتمد',
            'signed' => 'موقّع',
            'archived' => 'مؤرشف',
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM documents_documents WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('documents_documents', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('documents_documents', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('documents_documents', 'id = :id', ['id' => $id]);
    }

    /**
     * نطاق الرؤية ثابت: مدير النظام/مدير الشركة يرون كل مستندات الشركة،
     * أي مستخدم آخر لا يرى إلا ما أنشأه هو - وليس عبر صلاحية اختيارية.
     */
    public static function paginate(int $companyId, bool $seeAll, int $userId, int $page, int $perPage, array $filters = []): array
    {
        [$where, $params] = self::buildFilters($companyId, $seeAll, $userId, $filters);

        $total = Database::first("SELECT COUNT(*) AS c FROM documents_documents d WHERE {$where}", $params)['c'] ?? 0;

        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT d.*, u.name AS creator_name
               FROM documents_documents d
               LEFT JOIN users u ON u.id = d.created_by
              WHERE {$where}
              ORDER BY d.created_at DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => (int) $total];
    }

    private static function buildFilters(int $companyId, bool $seeAll, int $userId, array $filters): array
    {
        $where = 'd.company_id = :company_id';
        $params = ['company_id' => $companyId];

        if (!$seeAll) {
            $where .= ' AND d.created_by = :creator';
            $params['creator'] = $userId;
        }

        if (!empty($filters['q'])) {
            $where .= ' AND (d.title LIKE :q OR d.number LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['type'])) {
            $where .= ' AND d.type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND d.status = :status';
            $params['status'] = $filters['status'];
        }

        return [$where, $params];
    }

    public static function countPendingApproval(int $companyId): int
    {
        return Database::count(
            'documents_documents',
            'company_id = :c AND status = "pending_approval"',
            ['c' => $companyId]
        );
    }

    public static function recentFor(int $companyId, bool $seeAll, int $userId, int $limit): array
    {
        $where = 'company_id = :c';
        $params = ['c' => $companyId];
        if (!$seeAll) {
            $where .= ' AND created_by = :u';
            $params['u'] = $userId;
        }
        return Database::select(
            "SELECT * FROM documents_documents WHERE {$where} ORDER BY created_at DESC LIMIT {$limit}",
            $params
        );
    }
}
