<?php

namespace Modules\Forms\Models;

use App\Core\Database;
use App\Core\ModuleManager;

/**
 * محرّك حقول الدمج: يستخرج الحقول {بين_الأقواس} من نص القالب، ويملأ المعروف منها
 * من الملف الوظيفي + بيانات الشركة، ويترك الباقي للإدخال اليدوي.
 */
class MergeFields
{
    /** الحقول المعروفة التي تُملأ تلقائياً من الملف الوظيفي/الشركة/النظام. */
    public static function knownFields(): array
    {
        return [
            'الاسم', 'رقم_الهوية', 'الجنسية', 'المسمى', 'القسم', 'الفرع',
            'تاريخ_التعيين', 'الراتب_الأساسي', 'البدلات', 'الراتب_الإجمالي',
            'الجوال', 'البريد', 'نوع_العقد', 'الشركة', 'التاريخ',
        ];
    }

    /** استخراج كل الحقول الموجودة في نص (بلا تكرار). */
    public static function extract(string $body): array
    {
        preg_match_all('/\{([^{}]+)\}/u', $body, $m);
        return array_values(array_unique(array_map('trim', $m[1] ?? [])));
    }

    /**
     * القيم المعروفة لموظف معيّن (أو فارغة لو لا موظف). يُرجع خريطة الحقل => القيمة.
     * company اسم الشركة يُمرَّر لأن الموديل مستقل عن سياق الطلب.
     */
    public static function knownValues(int $companyId, ?int $employeeId, string $companyName): array
    {
        $values = [
            'الشركة' => $companyName,
            'التاريخ' => format_date(date('Y-m-d'), 'Y-m-d'),
        ];

        if ($employeeId && ModuleManager::isActive('employees')) {
            $e = Database::first(
                'SELECT * FROM employees_profiles WHERE id = :id AND company_id = :c',
                ['id' => $employeeId, 'c' => $companyId]
            );
            if ($e) {
                $base = $e['salary_base'] !== null ? (float) $e['salary_base'] : null;
                $allow = $e['salary_allowances'] !== null ? (float) $e['salary_allowances'] : null;
                $total = ($base !== null || $allow !== null) ? (($base ?? 0) + ($allow ?? 0)) : null;
                $values += [
                    'الاسم' => $e['full_name'] ?? '',
                    'رقم_الهوية' => $e['national_id'] ?? '',
                    'الجنسية' => $e['nationality'] ?? '',
                    'المسمى' => $e['job_title'] ?? '',
                    'القسم' => $e['department'] ?? '',
                    'الفرع' => $e['branch'] ?? '',
                    'تاريخ_التعيين' => $e['hire_date'] ? format_date($e['hire_date']) : '',
                    'الراتب_الأساسي' => $base !== null ? number_format($base, 2) : '',
                    'البدلات' => $allow !== null ? number_format($allow, 2) : '',
                    'الراتب_الإجمالي' => $total !== null ? number_format($total, 2) : '',
                    'الجوال' => $e['phone'] ?? '',
                    'البريد' => $e['personal_email'] ?? '',
                    'نوع_العقد' => $e['contract_type'] ?? '',
                ];
            }
        }

        return $values;
    }

    /**
     * الحقول التي تحتاج إدخالاً يدوياً في هذا القالب لهذا الموظف: الحقول الموجودة
     * بالقالب وليست ضمن المعروف المُعبّأ (أو معروفة لكن قيمتها فارغة).
     */
    public static function manualFields(string $body, array $knownValues): array
    {
        $manual = [];
        foreach (self::extract($body) as $field) {
            if (!isset($knownValues[$field]) || $knownValues[$field] === '') {
                $manual[] = $field;
            }
        }
        return $manual;
    }

    /** استبدال كل الحقول في النص بقيمها (معروفة + يدوية). ما لا قيمة له يبقى فارغاً. */
    public static function render(string $body, array $values): string
    {
        return preg_replace_callback('/\{([^{}]+)\}/u', function ($m) use ($values) {
            $key = trim($m[1]);
            return $values[$key] ?? '';
        }, $body);
    }
}
