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
    public const CURRENT_VERSION = 2;
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
        ];
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
}
