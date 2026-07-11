<?php

namespace App\Core;

/**
 * يدير اكتشاف الإضافات على القرص، وحالتها في قاعدة البيانات (مثبتة/مفعلة/معطلة)،
 * وتحميل مساراتها وعناصر صفحتها الرئيسية عند الحاجة فقط (الإضافات المعطلة لا تُحمّل إطلاقاً).
 */
class ModuleManager
{
    private static ?array $installedCache = null;

    public static function modulesPath(): string
    {
        return BASE_PATH . '/modules';
    }

    /** الإضافات الموجودة فعلياً في مجلد modules/ مع بيانات module.json */
    public static function discover(): array
    {
        $found = [];
        $base = self::modulesPath();
        if (!is_dir($base)) {
            return $found;
        }

        foreach (scandir($base) as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $manifest = $base . '/' . $dir . '/module.json';
            if (!is_file($manifest)) {
                continue;
            }
            $data = json_decode(file_get_contents($manifest), true);
            if (!is_array($data) || empty($data['key'])) {
                continue;
            }
            $found[$data['key']] = $data;
        }

        return $found;
    }

    /** حالة الإضافات المسجلة في قاعدة البيانات: module_key => row */
    public static function installedRows(): array
    {
        if (self::$installedCache !== null) {
            return self::$installedCache;
        }
        $rows = Database::select('SELECT * FROM modules');
        self::$installedCache = [];
        foreach ($rows as $row) {
            self::$installedCache[$row['module_key']] = $row;
        }
        return self::$installedCache;
    }

    /** قائمة مدمجة: بيانات القرص + حالة قاعدة البيانات، لصفحة إدارة الإضافات */
    public static function list(): array
    {
        $disk = self::discover();
        $db = self::installedRows();

        $list = [];
        foreach ($disk as $key => $manifest) {
            $row = $db[$key] ?? null;
            $list[] = [
                'key' => $key,
                'name' => $manifest['name'] ?? $key,
                'description' => $manifest['description'] ?? '',
                'version' => $manifest['version'] ?? '1.0.0',
                'author' => $manifest['author'] ?? '',
                'installed' => $row !== null,
                'status' => $row['status'] ?? null,
                'installed_version' => $row['version'] ?? null,
                'needs_update' => $row && version_compare($row['version'], $manifest['version'] ?? '1.0.0', '<'),
            ];
        }

        return $list;
    }

    /** عدد الإضافات المثبتة التي رُفعت لها ملفات بإصدار أحدث على القرص وتحتاج الضغط على "تحديث" */
    public static function countPendingUpdates(): int
    {
        return count(array_filter(self::list(), fn ($m) => $m['needs_update']));
    }

    public static function isActive(string $key): bool
    {
        $rows = self::installedRows();
        return isset($rows[$key]) && $rows[$key]['status'] === 'active';
    }

    public static function isInstalled(string $key): bool
    {
        return isset(self::installedRows()[$key]);
    }

    private static function manifest(string $key): ?array
    {
        return self::discover()[$key] ?? null;
    }

    public static function install(string $key): void
    {
        $manifest = self::manifest($key);
        if (!$manifest) {
            throw new \RuntimeException('الإضافة غير موجودة على القرص.');
        }
        if (self::isInstalled($key)) {
            throw new \RuntimeException('الإضافة مثبتة مسبقاً.');
        }

        $installFile = self::modulesPath() . '/' . $key . '/install.php';
        if (is_file($installFile)) {
            $install = require $installFile;
            if (is_callable($install)) {
                $install(Database::pdo());
            }
        }

        self::syncPermissions($key);

        Database::insert('modules', [
            'module_key' => $key,
            'name' => $manifest['name'] ?? $key,
            'version' => $manifest['version'] ?? '1.0.0',
            'status' => 'inactive',
            'installed_at' => date('Y-m-d H:i:s'),
        ]);

        self::$installedCache = null;
        ActivityLog::log('module.install', 'module', $key, "تثبيت إضافة: {$key}");
    }

    public static function activate(string $key): void
    {
        self::assertInstalled($key);
        Database::update('modules', ['status' => 'active'], 'module_key = :k', ['k' => $key]);
        self::$installedCache = null;
        ActivityLog::log('module.activate', 'module', $key, "تفعيل إضافة: {$key}");
    }

    public static function deactivate(string $key): void
    {
        self::assertInstalled($key);
        Database::update('modules', ['status' => 'inactive'], 'module_key = :k', ['k' => $key]);
        self::$installedCache = null;
        ActivityLog::log('module.deactivate', 'module', $key, "تعطيل إضافة: {$key}");
    }

    public static function update(string $key): void
    {
        self::assertInstalled($key);
        $manifest = self::manifest($key);
        $row = self::installedRows()[$key];

        $updateFile = self::modulesPath() . '/' . $key . '/update.php';
        if (is_file($updateFile)) {
            $update = require $updateFile;
            if (is_callable($update)) {
                $update(Database::pdo(), $row['version']);
            }
        }

        self::syncPermissions($key);

        Database::update('modules', [
            'version' => $manifest['version'] ?? $row['version'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'module_key = :k', ['k' => $key]);

        self::$installedCache = null;
        ActivityLog::log('module.update', 'module', $key, "تحديث إضافة: {$key}");
    }

    public static function remove(string $key): void
    {
        self::assertInstalled($key);
        $row = self::installedRows()[$key];
        if ($row['status'] === 'active') {
            throw new \RuntimeException('يجب تعطيل الإضافة أولاً قبل إزالتها.');
        }

        $uninstallFile = self::modulesPath() . '/' . $key . '/uninstall.php';
        if (is_file($uninstallFile)) {
            $uninstall = require $uninstallFile;
            if (is_callable($uninstall)) {
                $uninstall(Database::pdo());
            }
        }

        $permIds = array_column(Database::select('SELECT id FROM permissions WHERE module_key = :k', ['k' => $key]), 'id');
        if ($permIds) {
            $in = implode(',', array_fill(0, count($permIds), '?'));
            Database::pdo()->prepare("DELETE FROM role_permissions WHERE permission_id IN ({$in})")->execute($permIds);
            Database::pdo()->prepare("DELETE FROM permissions WHERE id IN ({$in})")->execute($permIds);
        }

        Database::delete('modules', 'module_key = :k', ['k' => $key]);
        self::$installedCache = null;
        ActivityLog::log('module.remove', 'module', $key, "إزالة إضافة: {$key}");
    }

    private static function assertInstalled(string $key): void
    {
        if (!self::isInstalled($key)) {
            throw new \RuntimeException('الإضافة غير مثبتة.');
        }
    }

    /** يقرأ modules/{key}/permissions.php ويزرع الصلاحيات في جدول permissions إن لم تكن موجودة */
    public static function syncPermissions(string $key): void
    {
        $file = self::modulesPath() . '/' . $key . '/permissions.php';
        if (!is_file($file)) {
            return;
        }
        $definitions = require $file;
        if (!is_array($definitions)) {
            return;
        }

        foreach ($definitions as $def) {
            $exists = Database::first(
                'SELECT id FROM permissions WHERE permission_key = :pk LIMIT 1',
                ['pk' => $def['key']]
            );
            if (!$exists) {
                Database::insert('permissions', [
                    'module_key' => $key,
                    'permission_key' => $def['key'],
                    'label' => $def['label'],
                    'default_level' => $def['default_level'] ?? 'employee',
                ]);
            }
        }
    }

    /** تسجيل مسارات كل الإضافات المفعلة فقط داخل الموجّه */
    public static function registerActiveRoutes(Router $router): void
    {
        foreach (self::installedRows() as $key => $row) {
            if ($row['status'] !== 'active') {
                continue;
            }
            $routesFile = self::modulesPath() . '/' . $key . '/routes.php';
            if (is_file($routesFile)) {
                $register = require $routesFile;
                if (is_callable($register)) {
                    $register($router);
                }
            }
        }
    }

    /** عناصر الصفحة الرئيسية من كل إضافة مفعلة يملك المستخدم صلاحية مشاهدتها على الأقل */
    public static function collectDashboardWidgets(array $user): array
    {
        $widgets = [];
        foreach (self::installedRows() as $key => $row) {
            if ($row['status'] !== 'active') {
                continue;
            }
            $widgetFile = self::modulesPath() . '/' . $key . '/Widgets/dashboard.php';
            if (!is_file($widgetFile)) {
                continue;
            }
            $provider = require $widgetFile;
            if (is_callable($provider)) {
                $result = $provider($user);
                if (is_array($result)) {
                    $widgets = array_merge($widgets, $result);
                }
            }
        }
        return $widgets;
    }

    /** روابط القائمة الجانبية المسجّلة من كل إضافة مفعلة، مفلترة حسب صلاحية المستخدم */
    public static function collectNavItems(array $user): array
    {
        $items = [];
        foreach (self::installedRows() as $key => $row) {
            if ($row['status'] !== 'active') {
                continue;
            }
            $navFile = self::modulesPath() . '/' . $key . '/nav.php';
            if (!is_file($navFile)) {
                continue;
            }
            $provider = require $navFile;
            if (is_callable($provider)) {
                $result = $provider($user);
                if (is_array($result)) {
                    $items = array_merge($items, $result);
                }
            }
        }
        return $items;
    }

    /**
     * عناصر عامة (HTML/JS) تُحقن في كل صفحة بعد تسجيل الدخول - مثل ودجت هاتف عائم أو شريط إشعار مستمر.
     * تُجمع فقط من الإضافات المفعّلة، وكل إضافة مسؤولة عن فحص صلاحية المستخدم داخل global.php الخاص بها.
     */
    public static function collectGlobalWidgets(array $user): array
    {
        $widgets = [];
        foreach (self::installedRows() as $key => $row) {
            if ($row['status'] !== 'active') {
                continue;
            }
            $file = self::modulesPath() . '/' . $key . '/global.php';
            if (!is_file($file)) {
                continue;
            }
            $provider = require $file;
            if (is_callable($provider)) {
                $result = $provider($user);
                if (is_array($result)) {
                    $widgets = array_merge($widgets, $result);
                } elseif (is_string($result) && $result !== '') {
                    $widgets[] = $result;
                }
            }
        }
        return $widgets;
    }
}
