<?php

namespace App\Core;

/**
 * ثيمات النظام: مجموعات منسّقة كاملة (لون أساسي + خلفية القائمة + لون خلفية الصفحة
 * + نمط الأشكال: حواف مدورة/حادة/ناعمة). يختارها المديران كما يختارون اللون والشعار.
 *
 * اختيار ثيم يكتب أيضاً primary_color و sidebar_color للشركة (توافقاً مع الكود الذي
 * يقرؤهما مباشرة)، فتبقى الشركات القديمة بألوانها، ويبقى "التخصيص اللوني المتقدم"
 * متاحاً فوق الثيم. مفتاح الثيم يحفظ نمط الشكل ولون خلفية الصفحة.
 */
class Theme
{
    public const DEFAULT = 'classic';

    /**
     * كل ثيم: label الاسم، primary اللون الأساسي، sidebar خلفية القائمة،
     * bg خلفية الصفحة، shape نمط الحواف (rounded|sharp|soft).
     */
    public static function presets(): array
    {
        return [
            'classic' => ['label' => 'أزرق كلاسيكي', 'primary' => '#2563eb', 'sidebar' => '#111827', 'bg' => '#f4f6f9', 'shape' => 'rounded'],
            'violet' => ['label' => 'بنفسجي المجرات', 'primary' => '#7c3aed', 'sidebar' => '#2e1065', 'bg' => '#faf9ff', 'shape' => 'rounded'],
            'emerald' => ['label' => 'أخضر احترافي', 'primary' => '#059669', 'sidebar' => '#064e3b', 'bg' => '#f2faf6', 'shape' => 'rounded'],
            'sky' => ['label' => 'سماوي ناعم', 'primary' => '#0284c7', 'sidebar' => '#0c4a6e', 'bg' => '#f0f9ff', 'shape' => 'soft'],
            'rose' => ['label' => 'وردي دافئ', 'primary' => '#e11d48', 'sidebar' => '#4c0519', 'bg' => '#fff5f7', 'shape' => 'rounded'],
            'charcoal' => ['label' => 'فحمي أنيق', 'primary' => '#334155', 'sidebar' => '#0f172a', 'bg' => '#f1f5f9', 'shape' => 'sharp'],
            'amber' => ['label' => 'كهرماني', 'primary' => '#d97706', 'sidebar' => '#451a03', 'bg' => '#fffbf3', 'shape' => 'rounded'],
            'teal' => ['label' => 'أزرق مخضر', 'primary' => '#0d9488', 'sidebar' => '#134e4a', 'bg' => '#f0fdfa', 'shape' => 'soft'],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::presets());
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::presets());
    }

    /** ثيم بمفتاحه، أو الافتراضي إن كان المفتاح غير صالح. */
    public static function resolve(?string $key): array
    {
        $presets = self::presets();
        return $presets[$key] ?? $presets[self::DEFAULT];
    }

    /** أنماط الأشكال المتاحة (يُبنى منها class على body). */
    public static function shapes(): array
    {
        return ['rounded', 'sharp', 'soft'];
    }
}
