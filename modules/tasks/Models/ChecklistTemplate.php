<?php

namespace Modules\Tasks\Models;

use App\Core\Database;

/** قالب قائمة تحقق قابل للتطبيق على أي مهمة (يُنشئ عناصره كمهام فرعية). */
class ChecklistTemplate
{
    public static function forCompany(int $companyId): array
    {
        return Database::select(
            'SELECT * FROM tasks_checklists WHERE company_id = :c ORDER BY name',
            ['c' => $companyId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM tasks_checklists WHERE id = :id', ['id' => $id]);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('tasks_checklists', $data);
    }

    public static function delete(int $id): void
    {
        Database::delete('tasks_checklists', 'id = :id', ['id' => $id]);
    }

    /** عناصر القالب كمصفوفة أسطر منظّفة. */
    public static function items(array $template): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) ($template['items'] ?? '')) ?: [];
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $items[] = mb_substr($line, 0, 255);
            }
        }
        return $items;
    }
}
