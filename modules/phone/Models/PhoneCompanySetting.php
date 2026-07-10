<?php

namespace Modules\Phone\Models;

use App\Core\Database;

class PhoneCompanySetting
{
    public static function forCompany(int $companyId): ?array
    {
        return Database::first('SELECT * FROM phone_company_settings WHERE company_id = :c', ['c' => $companyId]);
    }

    public static function save(int $companyId, string $apiKey): void
    {
        $existing = self::forCompany($companyId);
        if ($existing) {
            Database::update('phone_company_settings', [
                'api_key' => $apiKey,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $existing['id']]);
            return;
        }

        Database::insert('phone_company_settings', [
            'company_id' => $companyId,
            'api_key' => $apiKey,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
