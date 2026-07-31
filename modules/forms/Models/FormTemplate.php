<?php

namespace Modules\Forms\Models;

use App\Core\Database;

class FormTemplate
{
    public static function forCompany(int $companyId, bool $activeOnly = false): array
    {
        $where = 'company_id = :c' . ($activeOnly ? ' AND is_active = 1' : '');
        return Database::select("SELECT * FROM forms_templates WHERE {$where} ORDER BY name", ['c' => $companyId]);
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM forms_templates WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        return Database::insert('forms_templates', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('forms_templates', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('forms_templates', 'id = :id', ['id' => $id]);
    }
}
