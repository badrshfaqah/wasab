<?php

namespace Modules\Archive\Models;

use App\Core\Database;

class ArchiveSetting
{
    public static function getOrCreate(int $companyId): array
    {
        $row = Database::first('SELECT * FROM archive_settings WHERE company_id = :c', ['c' => $companyId]);
        if ($row) {
            return $row;
        }

        Database::insert('archive_settings', [
            'company_id' => $companyId,
            'expiry_warning_days' => 7,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Database::first('SELECT * FROM archive_settings WHERE company_id = :c', ['c' => $companyId]);
    }

    public static function update(int $companyId, array $data): void
    {
        self::getOrCreate($companyId);
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('archive_settings', $data, 'company_id = :c', ['c' => $companyId]);
    }
}
