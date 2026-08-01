<?php

namespace Modules\Employees\Models;

/**
 * حاسبة مكافأة نهاية الخدمة وفق نظام العمل السعودي (المادتان 84 و85):
 *  - نصف شهر عن كل سنة من السنوات الخمس الأولى، وشهر كامل عن كل سنة بعدها.
 *  - الأجر المعتمد = آخر أجر (الأساسي + البدلات الثابتة).
 *  - عند إنهاء العقد من صاحب العمل: المكافأة كاملة.
 *  - عند الاستقالة (المادة 85):
 *      أقل من سنتين: لا شيء · من 2 لأقل من 5: الثلث · من 5 لأقل من 10: الثلثان · 10 فأكثر: كاملة.
 * دالة حسابية صرفة (بلا قاعدة بيانات) ليسهل اختبارها والتحقق منها.
 */
class EndOfService
{
    public const REASONS = ['terminated', 'resignation'];

    public static function reasonLabels(): array
    {
        return [
            'terminated' => 'إنهاء العقد من صاحب العمل',
            'resignation' => 'استقالة الموظف',
        ];
    }

    /**
     * @param float  $wage      الأجر الشهري المعتمد (أساسي + بدلات)
     * @param string $startDate تاريخ بدء الخدمة (Y-m-d)
     * @param string $endDate   تاريخ نهاية الخدمة (Y-m-d)
     * @param string $reason    terminated | resignation
     * @return array تفصيل الحساب (سنوات الخدمة، المكافأة الأساسية، عامل الاستقالة، الصافي)
     */
    public static function calculate(float $wage, string $startDate, string $endDate, string $reason): array
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if (!$start || !$end || $end <= $start || $wage < 0) {
            return self::emptyResult($wage, $reason);
        }

        $days = (int) floor(($end - $start) / 86400);
        $years = $days / 365; // مدة الخدمة بالسنوات (تشمل الكسور)

        $firstFive = min($years, 5.0);
        $beyondFive = max(0.0, $years - 5.0);
        // نصف شهر لكل سنة من الخمس الأولى + شهر كامل لكل سنة بعدها
        $baseAward = (0.5 * $wage * $firstFive) + (1.0 * $wage * $beyondFive);

        $factor = self::factor($reason, $years);
        $finalAward = $baseAward * $factor;

        return [
            'wage' => round($wage, 2),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days' => $days,
            'years' => round($years, 2),
            'first_five_years' => round($firstFive, 2),
            'beyond_five_years' => round($beyondFive, 2),
            'base_award' => round($baseAward, 2),
            'reason' => $reason,
            'factor' => $factor,
            'factor_label' => self::factorLabel($reason, $years),
            'final_award' => round($finalAward, 2),
        ];
    }

    /** معامل الاستحقاق حسب سبب انتهاء الخدمة ومدتها. */
    private static function factor(string $reason, float $years): float
    {
        if ($reason !== 'resignation') {
            return 1.0; // إنهاء من صاحب العمل: كاملة
        }
        if ($years < 2) {
            return 0.0;
        }
        if ($years < 5) {
            return 1 / 3;
        }
        if ($years < 10) {
            return 2 / 3;
        }
        return 1.0;
    }

    private static function factorLabel(string $reason, float $years): string
    {
        if ($reason !== 'resignation') {
            return 'مكافأة كاملة (إنهاء من صاحب العمل)';
        }
        if ($years < 2) {
            return 'لا تُستحق مكافأة (استقالة قبل سنتين)';
        }
        if ($years < 5) {
            return 'ثلث المكافأة (استقالة بين 2 و5 سنوات)';
        }
        if ($years < 10) {
            return 'ثلثا المكافأة (استقالة بين 5 و10 سنوات)';
        }
        return 'مكافأة كاملة (استقالة بعد 10 سنوات)';
    }

    private static function emptyResult(float $wage, string $reason): array
    {
        return [
            'wage' => round(max(0, $wage), 2),
            'start_date' => null, 'end_date' => null, 'days' => 0, 'years' => 0.0,
            'first_five_years' => 0.0, 'beyond_five_years' => 0.0, 'base_award' => 0.0,
            'reason' => $reason, 'factor' => 0.0, 'factor_label' => 'بيانات غير كافية للحساب',
            'final_award' => 0.0,
        ];
    }
}
