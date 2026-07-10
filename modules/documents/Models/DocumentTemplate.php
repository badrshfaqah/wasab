<?php

namespace Modules\Documents\Models;

use App\Core\Database;

class DocumentTemplate
{
    public static function forCompany(int $companyId): array
    {
        return Database::select(
            'SELECT * FROM documents_templates WHERE company_id = :c ORDER BY name',
            ['c' => $companyId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM documents_templates WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('documents_templates', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('documents_templates', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('documents_templates', 'id = :id', ['id' => $id]);
    }
}
