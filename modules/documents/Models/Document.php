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

    public static function confidentialityLabels(): array
    {
        return [
            'normal' => 'عادي',
            'internal' => 'داخلي',
            'confidential' => 'سري',
            'secret' => 'سري للغاية',
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM documents_documents WHERE id = :id', ['id' => $id]);
    }

    /** بحث بمستند عبر رمز التحقق العام (لصفحة التحقق بلا تسجيل دخول). */
    public static function findByToken(string $token): ?array
    {
        return Database::first(
            'SELECT d.*, c.name AS company_name, u.name AS creator_name
               FROM documents_documents d
               LEFT JOIN companies c ON c.id = d.company_id
               LEFT JOIN users u ON u.id = d.created_by
              WHERE d.verify_token = :t',
            ['t' => $token]
        );
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
     * فلسفة الكتابة التعاونية: القائمة بثلاثة نطاقات -
     * mine: ما أنشأته أنا · shared: ما شُورك معي (مشاهدة/تعديل) ·
     * company: العام للشركة كله (والمدير يرى فيه الخاص أيضاً).
     */
    public static function paginate(int $companyId, string $scope, bool $isManager, int $userId, int $page, int $perPage, array $filters = []): array
    {
        [$where, $params] = self::buildFilters($companyId, $scope, $isManager, $userId, $filters);

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

    private static function buildFilters(int $companyId, string $scope, bool $isManager, int $userId, array $filters): array
    {
        $where = 'd.company_id = :company_id';
        $params = ['company_id' => $companyId];

        if ($scope === 'mine') {
            $where .= ' AND d.created_by = :creator';
            $params['creator'] = $userId;
        } elseif ($scope === 'shared') {
            $where .= ' AND EXISTS (SELECT 1 FROM documents_shares s WHERE s.document_id = d.id AND s.user_id = :share_user)';
            $params['share_user'] = $userId;
        } else { // company: العام للجميع، والمدير يرى الخاص أيضاً
            if (!$isManager) {
                $where .= " AND d.visibility = 'public'";
            }
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

    // ---------------- المشاركات (الكتابة التعاونية) ----------------

    /** مشاركو المستند مع أسمائهم وأدوارهم. */
    public static function shares(int $documentId): array
    {
        return Database::select(
            'SELECT s.*, u.name AS user_name FROM documents_shares s
               JOIN users u ON u.id = s.user_id
              WHERE s.document_id = :d ORDER BY u.name',
            ['d' => $documentId]
        );
    }

    /** دور المستخدم في مستند مشارَك (viewer/editor) أو null إن لم يُشارك معه. */
    public static function shareRole(int $documentId, int $userId): ?string
    {
        $row = Database::first(
            'SELECT role FROM documents_shares WHERE document_id = :d AND user_id = :u',
            ['d' => $documentId, 'u' => $userId]
        );
        return $row['role'] ?? null;
    }

    /** إضافة/تحديث مشاركة مستخدم بدور محدد. */
    public static function setShare(int $documentId, int $userId, string $role, int $byUserId): void
    {
        $existing = Database::first(
            'SELECT id FROM documents_shares WHERE document_id = :d AND user_id = :u',
            ['d' => $documentId, 'u' => $userId]
        );
        if ($existing) {
            Database::update('documents_shares', ['role' => $role], 'id = :id', ['id' => $existing['id']]);
            return;
        }
        Database::insert('documents_shares', [
            'document_id' => $documentId,
            'user_id' => $userId,
            'role' => $role,
            'created_by' => $byUserId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function removeShare(int $documentId, int $userId): void
    {
        Database::delete('documents_shares', 'document_id = :d AND user_id = :u', ['d' => $documentId, 'u' => $userId]);
    }

    /** عدد المستندات المشارَكة مع مستخدم (لشارة التبويب والودجت). */
    public static function countSharedWith(int $companyId, int $userId): int
    {
        return (int) (Database::first(
            'SELECT COUNT(*) AS c FROM documents_shares s
               JOIN documents_documents d ON d.id = s.document_id
              WHERE d.company_id = :c AND s.user_id = :u',
            ['c' => $companyId, 'u' => $userId]
        )['c'] ?? 0);
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

    /** لمزوّد التقويم: المستندات التي لها تاريخ متابعة ضمن نطاق معيّن، بنفس نطاق الرؤية المعتاد. */
    public static function forCalendarRange(int $companyId, bool $seeAll, int $userId, string $fromDate, string $toDate): array
    {
        $where = 'company_id = :c AND follow_up_date IS NOT NULL AND follow_up_date BETWEEN :from AND :to';
        $params = ['c' => $companyId, 'from' => $fromDate, 'to' => $toDate];
        if (!$seeAll) {
            $where .= ' AND created_by = :u';
            $params['u'] = $userId;
        }
        return Database::select(
            "SELECT id, title, follow_up_date FROM documents_documents WHERE {$where} ORDER BY follow_up_date",
            $params
        );
    }
}
