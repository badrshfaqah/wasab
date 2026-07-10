<?php

namespace App\Core;

/**
 * فحص الصلاحيات من جهة الخادم دائماً - لا يُعتمد على إخفاء عناصر الواجهة فقط.
 *
 * صلاحيات محصورة بمدير النظام فقط، حتى مدير الشركة لا يملكها:
 */
class Permission
{
    public const SYSTEM_ONLY = [
        'core.manage_companies',
        'core.manage_system_admins',
        'core.manage_modules',
        'core.system_settings',
        'core.view_all_companies',
        'core.promote_system_admin',
    ];

    private static array $cache = [];

    /**
     * يتحقق من صلاحية المستخدم الحالي لمفتاح صلاحية معين مثل "tasks.create".
     */
    public static function check(string $key): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($user['membership_type'] === 'system_admin') {
            return true;
        }

        if (in_array($key, self::SYSTEM_ONLY, true)) {
            return false;
        }

        if ($user['membership_type'] === 'company_admin') {
            return true;
        }

        // مستخدم عادي: يعتمد على الأدوار المعينة له صراحة داخل شركته
        return self::userHasPermission((int) $user['id'], $key);
    }

    public static function checkAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if (self::check($key)) {
                return true;
            }
        }
        return false;
    }

    private static function userHasPermission(int $userId, string $key): bool
    {
        $cacheKey = $userId . ':' . $key;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $moduleKey = explode('.', $key, 2)[0];
        if ($moduleKey !== 'core' && !ModuleManager::isActive($moduleKey)) {
            return self::$cache[$cacheKey] = false;
        }

        $row = Database::first(
            'SELECT 1
               FROM user_roles ur
               JOIN role_permissions rp ON rp.role_id = ur.role_id
               JOIN permissions p ON p.id = rp.permission_id
              WHERE ur.user_id = :uid AND p.permission_key = :pkey
              LIMIT 1',
            ['uid' => $userId, 'pkey' => $key]
        );

        return self::$cache[$cacheKey] = (bool) $row;
    }

    /**
     * كل مفاتيح الصلاحيات التي يملكها المستخدم الحالي (للاستخدام في الواجهة فقط، وليس كبديل عن check).
     */
    public static function userPermissionKeys(int $userId): array
    {
        $rows = Database::select(
            'SELECT DISTINCT p.permission_key
               FROM user_roles ur
               JOIN role_permissions rp ON rp.role_id = ur.role_id
               JOIN permissions p ON p.id = rp.permission_id
              WHERE ur.user_id = :uid',
            ['uid' => $userId]
        );
        return array_column($rows, 'permission_key');
    }
}
