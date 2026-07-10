<?php

namespace App\Core;

class Setting
{
    private static array $cache = [];

    public static function get(string $key, ?int $companyId = null, $default = null)
    {
        $scopeKey = ($companyId ?? 'global') . ':' . $key;
        if (array_key_exists($scopeKey, self::$cache)) {
            return self::$cache[$scopeKey];
        }

        $row = $companyId === null
            ? Database::first('SELECT setting_value FROM settings WHERE company_id IS NULL AND setting_key = :k', ['k' => $key])
            : Database::first('SELECT setting_value FROM settings WHERE company_id = :c AND setting_key = :k', ['c' => $companyId, 'k' => $key]);

        return self::$cache[$scopeKey] = ($row ? $row['setting_value'] : $default);
    }

    public static function set(string $key, string $value, ?int $companyId = null): void
    {
        $existing = $companyId === null
            ? Database::first('SELECT id FROM settings WHERE company_id IS NULL AND setting_key = :k', ['k' => $key])
            : Database::first('SELECT id FROM settings WHERE company_id = :c AND setting_key = :k', ['c' => $companyId, 'k' => $key]);

        if ($existing) {
            Database::update('settings', ['setting_value' => $value], 'id = :id', ['id' => $existing['id']]);
        } else {
            Database::insert('settings', ['company_id' => $companyId, 'setting_key' => $key, 'setting_value' => $value]);
        }

        self::$cache[($companyId ?? 'global') . ':' . $key] = $value;
    }
}
