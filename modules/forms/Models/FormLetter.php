<?php

namespace Modules\Forms\Models;

use App\Core\Database;

class FormLetter
{
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT l.*, t.name AS template_name FROM forms_letters l
               LEFT JOIN forms_templates t ON t.id = l.template_id
              WHERE l.id = :id',
            ['id' => $id]
        );
    }

    /** خطاب حسب رمز التحقق العام (لصفحة التحقق بلا مصادقة). */
    public static function findByToken(string $token): ?array
    {
        return Database::first(
            'SELECT l.*, c.name AS company_name FROM forms_letters l
               LEFT JOIN companies c ON c.id = l.company_id
              WHERE l.verify_token = :t',
            ['t' => $token]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('forms_letters', $data);
    }

    public static function delete(int $id): void
    {
        Database::delete('forms_letters', 'id = :id', ['id' => $id]);
    }

    public static function paginate(int $companyId, int $page, int $perPage, array $filters = []): array
    {
        $where = ['l.company_id = :c'];
        $params = ['c' => $companyId];
        if (!empty($filters['q'])) {
            $where[] = '(l.title LIKE :q OR l.recipient_name LIKE :q OR l.number LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::first("SELECT COUNT(*) AS c FROM forms_letters l WHERE {$whereSql}", $params)['c'] ?? 0);
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT l.*, t.name AS template_name, u.name AS creator_name
               FROM forms_letters l
               LEFT JOIN forms_templates t ON t.id = l.template_id
               LEFT JOIN users u ON u.id = l.created_by
              WHERE {$whereSql}
              ORDER BY l.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** رقم تسلسلي فريد للشركة داخل معاملة بقفل صف الإعدادات (كترقيم المستندات). */
    public static function nextNumber(int $companyId): string
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $row = $pdo->prepare('SELECT number_prefix, last_sequence FROM forms_settings WHERE company_id = :c FOR UPDATE');
            $row->execute(['c' => $companyId]);
            $s = $row->fetch();
            if (!$s) {
                $pdo->prepare('INSERT INTO forms_settings (company_id, last_sequence) VALUES (:c, 0)')->execute(['c' => $companyId]);
                $prefix = '';
                $seq = 0;
            } else {
                $prefix = (string) ($s['number_prefix'] ?? '');
                $seq = (int) $s['last_sequence'];
            }
            $seq++;
            $pdo->prepare('UPDATE forms_settings SET last_sequence = :s WHERE company_id = :c')->execute(['s' => $seq, 'c' => $companyId]);
            $pdo->commit();
            return ($prefix !== '' ? $prefix . '-' : '') . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
