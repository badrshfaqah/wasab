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

    /** حالة الإضافات المسجلة في قاعدة البيانات: module_key => row، بترتيب القائمة الجانبية المخصص. */
    public static function installedRows(): array
    {
        if (self::$installedCache !== null) {
            return self::$installedCache;
        }
        $rows = Database::select('SELECT * FROM modules ORDER BY sort_order ASC, id ASC');
        self::$installedCache = [];
        foreach ($rows as $row) {
            self::$installedCache[$row['module_key']] = $row;
        }
        return self::$installedCache;
    }

    /**
     * قائمة مدمجة: بيانات القرص + حالة قاعدة البيانات، لصفحة إدارة الإضافات - المثبّتة
     * أولاً بترتيبها المخصص (نفس ترتيب ظهورها بالقائمة الجانبية)، ثم غير المثبّتة بعدها
     * أبجدياً (ترتيبها لا يهم فعلياً لأنها لا تظهر بالقائمة الجانبية أصلاً).
     */
    public static function list(): array
    {
        $disk = self::discover();
        $db = self::installedRows();

        $build = function (string $key, array $manifest, ?array $row): array {
            return [
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
        };

        $list = [];
        foreach ($db as $key => $row) {
            if (!isset($disk[$key])) {
                continue;
            }
            $list[] = $build($key, $disk[$key], $row);
        }
        foreach ($disk as $key => $manifest) {
            if (isset($db[$key])) {
                continue;
            }
            $list[] = $build($key, $manifest, null);
        }

        return $list;
    }

    /** يحرّك رابط الإضافة خطوة للأعلى بالقائمة الجانبية (يتبادل الترتيب مع الإضافة السابقة مباشرة). */
    public static function moveUp(string $key): void
    {
        self::swapOrder($key, -1);
    }

    /** يحرّك رابط الإضافة خطوة للأسفل بالقائمة الجانبية (يتبادل الترتيب مع الإضافة التالية مباشرة). */
    public static function moveDown(string $key): void
    {
        self::swapOrder($key, 1);
    }

    private static function swapOrder(string $key, int $direction): void
    {
        $keys = array_keys(self::installedRows());
        $index = array_search($key, $keys, true);
        if ($index === false) {
            return;
        }

        $targetIndex = $index + $direction;
        if ($targetIndex < 0 || $targetIndex >= count($keys)) {
            return;
        }

        $rows = self::installedRows();
        $current = $rows[$key];
        $target = $rows[$keys[$targetIndex]];

        Database::update('modules', ['sort_order' => $target['sort_order']], 'id = :id', ['id' => $current['id']]);
        Database::update('modules', ['sort_order' => $current['sort_order']], 'id = :id', ['id' => $target['id']]);

        self::$installedCache = null;
    }

    /** عدد الإضافات المثبتة التي رُفعت لها ملفات بإصدار أحدث على القرص وتحتاج الضغط على "تحديث" */
    public static function countPendingUpdates(): int
    {
        return count(array_filter(self::list(), fn ($m) => $m['needs_update']));
    }

    /**
     * الإكمال التلقائي بعد كل سحب من الاستضافة: يقارن رقم إصدار الملفات (changelog)
     * بآخر رقم مسجّل بقاعدة البيانات، وعند اختلافهما (وصلت ملفات جديدة عبر Git
     * الاستضافة أو أي رفع) يطبّق كل شيء فوراً: ترقيات النواة (متجاوزاً تهدئة إعادة
     * المحاولة) + ترقيات الإضافات + مزامنة صلاحيات كل الإضافات، ثم يسجّل الرقم
     * الجديد ويوثّق العملية ويُشعر مدراء النظام. رخيص جداً عند عدم التغيّر (مقارنة
     * نصية واحدة) فيُستدعى بكل طلب ومن cron.
     */
    public static function finalizeIfVersionChanged(): void
    {
        try {
            $fileVersion = Wasab::currentVersion();
            $dbVersion = (string) (Setting::get('system_version', null, '') ?? '');
            if ($fileVersion === '' || $dbVersion === $fileVersion) {
                return;
            }

            // ترقيات النواة كاملة (تتجاهل التهدئة - وصول نسخة جديدة سبب كافٍ للمحاولة فوراً)
            try {
                CoreMigrator::applyAll();
            } catch (\Throwable $e) {
                log_exception($e);
            }

            // ترقيات الإضافات التي ملفاتها أحدث
            self::autoMigratePending();

            // مزامنة صلاحيات كل الإضافات المثبتة (احتياطاً حتى لغير المرقّاة)
            foreach (array_keys(self::installedRows()) as $key) {
                try {
                    self::syncPermissions($key);
                } catch (\Throwable $e) {
                    log_exception($e);
                }
            }

            Setting::set('system_version', $fileVersion);
            Setting::set('system_version_updated_at', date('Y-m-d H:i:s'));

            $label = $dbVersion !== '' ? "{$dbVersion} ← {$fileVersion}" : $fileVersion;
            ActivityLog::log('system.update_finalized', 'system', null, "اكتمال تحديث تلقائي بعد وصول ملفات جديدة: {$label}");

            // إشعار مدراء النظام بأن التحديث اكتمل وقاعدة البيانات مواكبة
            foreach (Database::select("SELECT id FROM users WHERE membership_type = 'system_admin' AND status = 'active'") as $admin) {
                Notification::send(
                    (int) $admin['id'],
                    '⬆️ اكتمل تحديث النظام تلقائياً',
                    "وصلت ملفات جديدة وطُبِّقت الترقيات — النظام الآن على {$fileVersion}.",
                    route('/extensions')
                );
            }
        } catch (\Throwable $e) {
            // لا يُسمح لأي فشل هنا بإسقاط الطلب - يُسجَّل ويُعاد في الطلب التالي
            log_exception($e);
        }
    }

    /**
     * يطبّق ترقيات قاعدة البيانات لأي إضافة رُفعت ملفاتها بإصدار أحدث من المسجّل بقاعدة
     * البيانات (كما يحدث بعد التحديث الذاتي من GitHub الذي ينسخ الملفات دون تشغيل
     * ترقيات الإضافات). يُستدعى مبكراً في الإقلاع فلا يبقى كود الإضافة أحدث من مخططها
     * (وهو ما يُسقط الصفحات التي تعتمد على أعمدة جديدة). آمن ورخيص عند عدم وجود ما ينتظر،
     * وأي فشل بترقية إضافة يُسجَّل ولا يُوقِف بقية النظام.
     */
    public static function autoMigratePending(): void
    {
        $disk = self::discover();
        foreach (self::installedRows() as $key => $row) {
            if (!isset($disk[$key])) {
                continue;
            }
            $diskVersion = $disk[$key]['version'] ?? '1.0.0';
            if (!version_compare($row['version'], $diskVersion, '<')) {
                continue;
            }
            try {
                self::update($key);
            } catch (\Throwable $e) {
                log_exception($e);
            }
        }
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

        $maxOrder = Database::first('SELECT MAX(sort_order) AS m FROM modules')['m'] ?? 0;

        Database::insert('modules', [
            'module_key' => $key,
            'name' => $manifest['name'] ?? $key,
            'version' => $manifest['version'] ?? '1.0.0',
            'status' => 'inactive',
            'sort_order' => (int) $maxOrder + 1,
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
                // عزل كل إضافة: فشل عناصر إضافة واحدة (خطأ استعلام، عمود ناقص...) يجب
                // ألا يُسقط الصفحة الرئيسية بالكامل لبقية الإضافات - يُسجَّل ويُتخطّى.
                try {
                    $result = $provider($user);
                    if (is_array($result)) {
                        $widgets = array_merge($widgets, $result);
                    }
                } catch (\Throwable $e) {
                    log_exception($e);
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

    /**
     * تُستدعى من CronRunner (نقطة الدخول: cron.php بجذر المشروع) لكل الإضافات المفعّلة
     * التي تملك modules/{key}/cron.php - مخصصة لمهام دورية كتذكير الاجتماعات القريبة.
     * كل إضافة مسؤولة عن منع تكرار نفس التذكير (عمود "أُرسل بتاريخ" في جدولها الخاص).
     */
    public static function runCron(): void
    {
        foreach (self::installedRows() as $key => $row) {
            if ($row['status'] !== 'active') {
                continue;
            }
            $file = self::modulesPath() . '/' . $key . '/cron.php';
            if (!is_file($file)) {
                continue;
            }
            $provider = require $file;
            if (is_callable($provider)) {
                $provider();
            }
        }
    }

    /**
     * أحداث التقويم الموحّد من كل إضافة مفعّلة تملك modules/{key}/calendar.php - ميزة
     * أساسية بالنواة (لا علاقة لها بأي إضافة معيّنة)، لكن الإضافات هي مصدر الأحداث.
     * كل إضافة مسؤولة عن فلترة ما يراه المستخدم الحالي داخل مزوّدها الخاص، وعن كفاءة
     * استعلامها ضمن نطاق [$fromDate, $toDate] المُمرَّر (YYYY-MM-DD) بدل إعادة كل شيء.
     * كل حدث: ['date'=>'Y-m-d', 'time'=>?'H:i', 'title'=>string, 'url'=>string, 'color'=>?string].
     */
    public static function collectCalendarEvents(array $user, string $fromDate, string $toDate): array
    {
        $events = [];
        foreach (self::installedRows() as $key => $row) {
            if ($row['status'] !== 'active') {
                continue;
            }
            $file = self::modulesPath() . '/' . $key . '/calendar.php';
            if (!is_file($file)) {
                continue;
            }
            $provider = require $file;
            if (is_callable($provider)) {
                $result = $provider($user, $fromDate, $toDate);
                if (is_array($result)) {
                    foreach ($result as $event) {
                        $event['module'] = $key;
                        $events[] = $event;
                    }
                }
            }
        }
        return $events;
    }

    /**
     * نتائج البحث الموحّد من كل إضافة مفعّلة تملك modules/{key}/search.php - ميزة نواة
     * (بحث بشريط واحد بأعلى الصفحة يغطي كل الإضافات دفعة وحدة)، لكن كل إضافة مسؤولة عن
     * فلترة النتائج حسب صلاحية المستخدم الحالي ونطاق شركته داخل مزوّدها الخاص.
     * كل نتيجة: ['title'=>string, 'subtitle'=>?string, 'url'=>string, 'icon'=>?string].
     */
    public static function collectSearchResults(array $user, string $query): array
    {
        $results = [];
        foreach (self::installedRows() as $key => $row) {
            if ($row['status'] !== 'active') {
                continue;
            }
            $file = self::modulesPath() . '/' . $key . '/search.php';
            if (!is_file($file)) {
                continue;
            }
            $provider = require $file;
            if (is_callable($provider)) {
                $result = $provider($user, $query);
                if (is_array($result)) {
                    foreach ($result as $item) {
                        $item['module'] = $key;
                        $item['module_name'] = self::discover()[$key]['name'] ?? $key;
                        $results[] = $item;
                    }
                }
            }
        }
        return $results;
    }

    /**
     * أرقام مجمّعة لصفحة "التقارير" (خاصة بمدير الشركة) من كل إضافة مفعّلة تملك
     * modules/{key}/report.php. بخلاف widgets/dashboard.php (مفلترة حسب صلاحية المستخدم
     * الحالي)، هذا المزوّد يُستدعى فقط لمن يدير الشركة فعلياً فيعيد أرقاماً على مستوى
     * الشركة كاملة بلا حاجة لفلترة صلاحيات إضافية.
     * كل عنصر: ['label'=>string, 'value'=>int|string, 'icon'=>?string, 'color'=>?string, 'url'=>?string].
     */
    public static function collectReportStats(array $user): array
    {
        $stats = [];
        foreach (self::installedRows() as $key => $row) {
            if ($row['status'] !== 'active') {
                continue;
            }
            $file = self::modulesPath() . '/' . $key . '/report.php';
            if (!is_file($file)) {
                continue;
            }
            $provider = require $file;
            if (is_callable($provider)) {
                $result = $provider($user);
                if (is_array($result)) {
                    foreach ($result as $item) {
                        $item['module'] = $key;
                        $item['module_name'] = self::discover()[$key]['name'] ?? $key;
                        $stats[] = $item;
                    }
                }
            }
        }
        return $stats;
    }
}
