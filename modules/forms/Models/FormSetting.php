<?php

namespace Modules\Forms\Models;

use App\Core\Database;

class FormSetting
{
    public static function getOrCreate(int $companyId): array
    {
        $s = Database::first('SELECT * FROM forms_settings WHERE company_id = :c', ['c' => $companyId]);
        if (!$s) {
            Database::insert('forms_settings', ['company_id' => $companyId]);
            $s = Database::first('SELECT * FROM forms_settings WHERE company_id = :c', ['c' => $companyId]);
        }
        return $s;
    }

    public static function update(int $companyId, array $data): void
    {
        self::getOrCreate($companyId);
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('forms_settings', $data, 'company_id = :c', ['c' => $companyId]);
    }
}
