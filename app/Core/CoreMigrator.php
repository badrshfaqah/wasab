<?php

namespace App\Core;

/**
 * ترقية خفيفة لبنية جداول النواة على المواقع المُثبَّتة مسبقاً، دون الحاجة لأي
 * وصول لـ phpMyAdmin أو Terminal.
 *
 * كل ترقية تُتبَّع بعلامة مستقلة في جدول settings (company_id NULL,
 * key="core_migration_{n}") تُضبط على "applied" عند النجاح فقط - وليس عند
 * الفشل - حتى تبقى حالة "غير مكتملة" ظاهرة وصحيحة لصفحة الإدارة وزر التحديث
 * اليدوي، بدل أن تختفي المشكلة بصمت.
 *
 * - run() تلقائية وصامتة، تُستدعى في كل طلب من bootstrap.php، لكنها لا تُعيد
 *   محاولة الترقيات الفاشلة إلا كل 5 دقائق كحد أدنى (لتفادي تكرار استعلام فاشل
 *   في كل طلب على استضافة تمنع ALTER TABLE مثلاً).
 * - applyAll() تُستدعى يدوياً من زر "تحديث قاعدة بيانات النواة" في صفحة
 *   الإضافات، وتتجاهل التهدئة الزمنية فتُعيد المحاولة فوراً.
 *
 * عند إضافة عمود/جدول جديد للنواة مستقبلاً: أضفه دائماً في مكانين معاً حتى
 * تبقى التركيبتان متطابقتين - (1) install/schema.php لتشمله التثبيتات
 * الجديدة من أول لحظة، و(2) هنا في self::migrations() لترقية المواقع
 * المثبتة مسبقاً.
 */
class CoreMigrator
{
    public const CURRENT_VERSION = 15;
    private const RETRY_COOLDOWN_SECONDS = 300;

    private static function migrations(): array
    {
        return [
            2 => [
                'label' => 'إضافة عمود لون خلفية القائمة الجانبية للشركات',
                'run' => fn () => self::addColumnIfMissing(
                    'companies',
                    'sidebar_color',
                    "VARCHAR(7) NOT NULL DEFAULT '#111827' AFTER `primary_color`"
                ),
            ],
            3 => [
                'label' => 'نقل شعارات الشركات المرفوعة سابقاً إلى storage/uploads/core',
                'run' => fn () => self::moveLegacyCompanyLogos(),
            ],
            4 => [
                'label' => 'إضافة مستوى افتراضي (موظف/مدير) إرشادي لكل صلاحية',
                'run' => fn () => self::addPermissionDefaultLevel(),
            ],
            5 => [
                'label' => 'إضافة جدول أحداث التقويم الخاصة بالشركة',
                'run' => fn () => self::createCalendarEventsTable(),
            ],
            6 => [
                'label' => 'إضافة عمود تذكير أحداث التقويم الخاصة بالشركة',
                'run' => fn () => self::addColumnIfMissing(
                    'calendar_events',
                    'reminder_sent_at',
                    "DATETIME NULL AFTER `created_by`"
                ),
            ],
            7 => [
                'label' => 'إضافة خيار تفعيل/إيقاف التنبيه لكل حدث تقويم خاص بالشركة',
                'run' => fn () => self::addColumnIfMissing(
                    'calendar_events',
                    'send_reminder',
                    "TINYINT(1) NOT NULL DEFAULT 1 AFTER `created_by`"
                ),
            ],
            8 => [
                'label' => 'إضافة عمود ترتيب الإضافات بالقائمة الجانبية',
                'run' => fn () => self::addModuleSortOrder(),
            ],
            9 => [
                'label' => 'إضافة جدول اشتراكات إشعارات الدفع للجوال (Web Push)',
                'run' => fn () => self::createPushSubscriptionsTable(),
            ],
            10 => [
                'label' => 'إضافة خيار المنطقة الزمنية المفضلة لكل مستخدم',
                'run' => fn () => self::addColumnIfMissing(
                    'users',
                    'timezone',
                    "VARCHAR(50) NULL COMMENT 'منطقة زمنية للعرض فقط - NULL تعني توقيت النظام الافتراضي' AFTER `avatar`"
                ),
            ],
            11 => [
                'label' => 'إضافة عمود آخر نشاط لكل مستخدم',
                'run' => fn () => self::addColumnIfMissing(
                    'users',
                    'last_activity_at',
                    "DATETIME NULL COMMENT 'آخر طلب مصادق للمستخدم - يُحدَّث بتهدئة دقيقة من Auth::user()' AFTER `last_login_at`"
                ),
            ],
            12 => [
                'label' => 'نقل شعارات الشركات لمجلد محمي يُخدم للمسجلين دخولاً فقط',
                'run' => fn () => self::moveCompanyLogosToProtectedDir(),
            ],
            13 => [
                'label' => 'فصل مرفقات كل شركة في مجلد خاص بها داخل كل إضافة',
                'run' => fn () => self::moveUploadsIntoCompanyDirs(),
            ],
            14 => [
                'label' => 'دعم الأحداث الشخصية بالتقويم (حدث خاص بالموظف بجانب أحداث الشركة)',
                'run' => fn () => self::addColumnIfMissing(
                    'calendar_events',
                    'user_id',
                    "INT UNSIGNED NULL COMMENT 'حدث شخصي لهذا المستخدم فقط - NULL يعني حدثاً عاماً للشركة' AFTER `company_id`"
                ),
            ],
            15 => [
                'label' => 'إضافة ثيم التصميم لكل شركة',
                'run' => fn () => self::addColumnIfMissing(
                    'companies',
                    'theme',
                    "VARCHAR(30) NOT NULL DEFAULT 'classic' COMMENT 'مفتاح ثيم التصميم - App\\\\Core\\\\Theme' AFTER `sidebar_color`"
                ),
            ],
        ];
    }

    /**
     * إعادة تنظيم الرفوعات: من مجلد مشترك لكل إضافة إلى مجلد فرعي لكل شركة
     * (storage/uploads/tasks/{company_id}/... إلخ) - لتسهيل النسخ الاحتياطي
     * وتصدير/حذف بيانات شركة بعينها. أسماء الملفات تُجلب من جداول كل إضافة،
     * والإضافات غير المثبتة (جداولها غائبة) تُتجاوز بهدوء. العملية آمنة للإعادة.
     */
    private static function moveUploadsIntoCompanyDirs(): void
    {
        $groups = [
            'tasks' => 'SELECT t.company_id AS cid, a.stored_name AS f
                          FROM tasks_attachments a JOIN tasks_tasks t ON t.id = a.task_id',
            'employees' => 'SELECT company_id AS cid, photo AS f FROM employees_profiles WHERE photo IS NOT NULL
                            UNION SELECT p.company_id, d.stored_name
                              FROM employees_documents d JOIN employees_profiles p ON p.id = d.employee_id',
            'documents' => 'SELECT company_id AS cid, background_image AS f FROM documents_templates WHERE background_image IS NOT NULL
                            UNION SELECT company_id, signature_image FROM documents_settings WHERE signature_image IS NOT NULL
                            UNION SELECT company_id, stamp_image FROM documents_settings WHERE stamp_image IS NOT NULL',
            'archive' => 'SELECT company_id AS cid, stored_name AS f FROM archive_files
                          UNION SELECT af.company_id, v.stored_name
                            FROM archive_file_versions v JOIN archive_files af ON af.id = v.file_id',
        ];

        foreach ($groups as $area => $sql) {
            try {
                $rows = Database::select($sql);
            } catch (\Throwable $e) {
                continue; // الإضافة غير مثبتة - لا جداول لها
            }

            $base = BASE_PATH . '/storage/uploads/' . $area;
            foreach ($rows as $row) {
                $file = (string) $row['f'];
                $companyId = (int) $row['cid'];
                if ($companyId < 1 || !preg_match('/^[A-Za-z0-9_.-]+$/', $file)) {
                    continue;
                }

                $from = $base . '/' . $file;
                $toDir = $base . '/' . $companyId;
                if (!is_file($from) || is_file($toDir . '/' . $file)) {
                    continue;
                }
                if (!is_dir($toDir) && !@mkdir($toDir, 0755, true) && !is_dir($toDir)) {
                    throw new \RuntimeException("تعذر إنشاء المجلد {$toDir}");
                }
                if (!@rename($from, $toDir . '/' . $file)) {
                    throw new \RuntimeException("تعذر نقل الملف {$file} إلى مجلد الشركة {$companyId}");
                }
            }
        }
    }

    /**
     * شعارات الشركات كانت في storage/uploads/core المفتوح للعموم (لأن شعار النظام
     * وأيقونات الجوال تظهر قبل تسجيل الدخول)، لكنها خاصة بالشركات ولا تظهر إلا
     * داخل النظام - تُنقل لمجلد companies المحجوب وتُخدم عبر /media بمصادقة.
     * شعار النظام وأيقوناته يبقيان في core عمداً.
     */
    private static function moveCompanyLogosToProtectedDir(): void
    {
        $source = BASE_PATH . '/storage/uploads/core';
        $target = BASE_PATH . '/storage/uploads/companies';

        $logos = array_column(
            Database::select("SELECT logo FROM companies WHERE logo IS NOT NULL AND logo != ''"),
            'logo'
        );
        if (!$logos) {
            return;
        }

        if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
            throw new \RuntimeException("تعذر إنشاء المجلد {$target}");
        }

        foreach (array_unique($logos) as $file) {
            if (!preg_match('/^[A-Za-z0-9_.-]+$/', $file)) {
                continue;
            }
            $from = $source . '/' . $file;
            $to = $target . '/' . $file;
            if (is_file($from) && !is_file($to) && !@rename($from, $to)) {
                throw new \RuntimeException("تعذر نقل الشعار {$file} إلى storage/uploads/companies/");
            }
        }
    }

    /** يُستدعى تلقائياً في كل طلب - صامت وسريع، مع تهدئة بين محاولات الترقيات الفاشلة. */
    public static function run(): void
    {
        foreach (self::migrations() as $number => $migration) {
            if (self::isApplied($number)) {
                continue;
            }

            $lastAttempt = (int) (Setting::get(self::attemptKey($number), null, '0'));
            if ($lastAttempt > 0 && (time() - $lastAttempt) < self::RETRY_COOLDOWN_SECONDS) {
                continue;
            }

            self::attempt($number, $migration);
        }
    }

    /**
     * يُعاد تشغيلها يدوياً (زر الإدارة) بغضّ النظر عن مهلة التهدئة.
     * تعيد مصفوفة نتائج: ['label' => ..., 'status' => 'applied'|'already'|'failed', 'error' => ?string]
     */
    public static function applyAll(): array
    {
        $results = [];
        foreach (self::migrations() as $number => $migration) {
            if (self::isApplied($number)) {
                $results[] = ['label' => $migration['label'], 'status' => 'already', 'error' => null];
                continue;
            }
            $results[] = self::attempt($number, $migration);
        }
        return $results;
    }

    /** هل كل ترقيات النواة المعروفة مُطبَّقة بنجاح؟ (تُستخدم لعرض شارة الحالة) */
    public static function isUpToDate(): bool
    {
        foreach (array_keys(self::migrations()) as $number) {
            if (!self::isApplied($number)) {
                return false;
            }
        }
        return true;
    }

    /**
     * تُستدعى من معالج التثبيت فقط: install/schema.php ينشئ الجداول بأحدث تركيبة
     * أصلاً، فلا داعي لتشغيل الترقيات فعلياً - فقط نضع علامة "مُطبَّقة" على الجميع.
     */
    public static function markAllApplied(): void
    {
        foreach (array_keys(self::migrations()) as $number) {
            Setting::set(self::appliedKey($number), '1');
        }
    }

    private static function attempt(int $number, array $migration): array
    {
        Setting::set(self::attemptKey($number), (string) time());

        try {
            ($migration['run'])();
            Setting::set(self::appliedKey($number), '1');
            return ['label' => $migration['label'], 'status' => 'applied', 'error' => null];
        } catch (\Throwable $e) {
            log_exception($e);
            return ['label' => $migration['label'], 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private static function isApplied(int $number): bool
    {
        return Setting::get(self::appliedKey($number), null) === '1';
    }

    private static function appliedKey(int $number): string
    {
        return "core_migration_{$number}_applied";
    }

    private static function attemptKey(int $number): string
    {
        return "core_migration_{$number}_last_attempt";
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

    /**
     * تضيف عمود default_level (تصنيف إرشادي فقط: موظف/مدير) لجدول الصلاحيات، وتُصحّح
     * قيمته بأثر رجعي للصلاحيات الحساسة الموجودة مسبقاً في المواقع المثبَّتة (التي
     * كانت سترث القيمة الافتراضية "موظف" من تعريف العمود وحده لو تُركت بلا تصحيح،
     * لأن ModuleManager::syncPermissions() لا يُحدِّث صفوفاً موجودة أصلاً، فقط يُضيف
     * الناقص). الصلاحيات المستقبلية تُصنَّف مباشرة عبر permissions.php لكل إضافة.
     */
    private static function addPermissionDefaultLevel(): void
    {
        self::addColumnIfMissing(
            'permissions',
            'default_level',
            "ENUM('employee','manager') NOT NULL DEFAULT 'employee' AFTER `label`"
        );

        $managerKeys = [
            'tasks.delete', 'tasks.approve', 'tasks.manage',
            'phone.manage_contacts',
            'documents.delete', 'documents.approve', 'documents.sign', 'documents.manage',
            'archive.delete', 'archive.share',
            'archive.categories.create', 'archive.categories.edit', 'archive.categories.delete', 'archive.manage',
        ];
        $placeholders = implode(',', array_fill(0, count($managerKeys), '?'));
        $stmt = Database::pdo()->prepare("UPDATE `permissions` SET `default_level` = 'manager' WHERE `permission_key` IN ({$placeholders})");
        $stmt->execute($managerKeys);
    }

    /**
     * تضيف عمود sort_order لجدول modules (ترتيب رابط كل إضافة بالقائمة الجانبية)، وتُصحّح
     * قيمته بأثر رجعي بنفس ترتيب التثبيت الحالي (id) للمواقع المثبَّتة مسبقاً - نفس الترتيب
     * الظاهر لهم أصلاً حالياً، فلا يتغيّر شيء بصرياً إلا بعد إعادة الترتيب يدوياً من صفحة الإضافات.
     */
    private static function addModuleSortOrder(): void
    {
        self::addColumnIfMissing('modules', 'sort_order', "INT NOT NULL DEFAULT 0 AFTER `status`");
        Database::pdo()->exec('UPDATE `modules` SET `sort_order` = `id` WHERE `sort_order` = 0');
    }

    /**
     * جدول اشتراكات إشعارات الدفع (Web Push): كل صف جهاز/متصفح فعّل التنبيهات لمستخدم.
     * endpoint_hash (sha1) للفهرس الفريد لأن endpoint نفسه أطول من حد مفاتيح MySQL الفريدة.
     */
    private static function createPushSubscriptionsTable(): void
    {
        Database::pdo()->exec("
            CREATE TABLE IF NOT EXISTS `push_subscriptions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `endpoint` VARCHAR(500) NOT NULL,
                `endpoint_hash` CHAR(40) NOT NULL,
                `p256dh` VARCHAR(255) NOT NULL,
                `auth` VARCHAR(255) NOT NULL,
                `created_at` DATETIME NOT NULL,
                UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint_hash`),
                KEY `push_subscriptions_user_index` (`user_id`),
                CONSTRAINT `push_subscriptions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /** جدول أحداث التقويم الخاصة بكل شركة (يضيفها مدير النظام/الشركة يدوياً من صفحة التقويم). */
    private static function createCalendarEventsTable(): void
    {
        Database::pdo()->exec("
            CREATE TABLE IF NOT EXISTS `calendar_events` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `description` VARCHAR(500) NULL,
                `event_date` DATE NOT NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                KEY `calendar_events_company_index` (`company_id`),
                KEY `calendar_events_date_index` (`event_date`),
                CONSTRAINT `calendar_events_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * توحيد تنظيم الرفوعات: شعارات الشركات كانت تُحفظ في جذر storage/uploads/،
     * بينما كل إضافة تحفظ رفوعاتها في مجلد خاص باسمها (storage/uploads/tasks/ مثلاً).
     * هذه الترقية تنقل أي شعار قديم فعلياً على القرص إلى storage/uploads/core/
     * ليطابق نفس القاعدة، دون أن تختفي شعارات الشركات المرفوعة مسبقاً.
     */
    private static function moveLegacyCompanyLogos(): void
    {
        $root = BASE_PATH . '/storage/uploads';
        if (!is_dir($root)) {
            return;
        }

        $target = $root . '/core';
        $moved = false;

        foreach (scandir($root) as $file) {
            if ($file === '.' || $file === '..' || !is_file($root . '/' . $file)) {
                continue;
            }
            if (!preg_match('/^company_[0-9a-f]+\.(png|jpe?g|webp)$/i', $file)) {
                continue;
            }

            if (!$moved) {
                if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new \RuntimeException("تعذر إنشاء المجلد {$target}");
                }
                $moved = true;
            }

            if (!@rename($root . '/' . $file, $target . '/' . $file)) {
                throw new \RuntimeException("تعذر نقل الملف {$file} إلى storage/uploads/core/");
            }
        }
    }
}
