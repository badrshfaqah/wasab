<?php

namespace App\Core;

/**
 * ترقية خفيفة لبنية جداول النواة على المواقع المُثبَّتة مسبقاً، دون الحاجة لأي
 * وصول لـ phpMyAdmin أو Terminal. تُفحص مرة واحدة فقط لكل إصدار عبر جدول settings
 * (company_id NULL, key="core_schema_version") لتفادي أي فحص إضافي في الطلبات التالية.
 */
class CoreMigrator
{
    private const CURRENT_VERSION = 2;

    public static function run(): void
    {
        $version = (int) Setting::get('core_schema_version', null, '1');
        if ($version >= self::CURRENT_VERSION) {
            return;
        }

        if ($version < 2) {
            self::addColumnIfMissing('companies', 'sidebar_color', "VARCHAR(7) NOT NULL DEFAULT '#111827' AFTER `primary_color`");
        }

        Setting::set('core_schema_version', (string) self::CURRENT_VERSION);
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $exists = Database::first(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c',
            ['t' => $table, 'c' => $column]
        );
        if (!$exists) {
            Database::pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}
