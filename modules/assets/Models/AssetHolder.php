<?php

namespace Modules\Assets\Models;

use App\Core\Database;
use App\Core\ModuleManager;

/**
 * حامل العهدة بثلاثة مصادر (نفس مرونة الملف الوظيفي):
 *  - employee: ملف وظيفي (بمن فيهم المربوطون بحسابات) - المصدر المفضّل
 *  - user: مستخدم بالنظام مباشرة
 *  - manual: شخص يدوي غير موجود بالنظام (اسم فقط)
 *
 * التخزين polymorphic (نوع + معرّف + لقطة اسم) فيبقى السجل صحيحاً حتى لو حُذف
 * الموظف/المستخدم لاحقاً. هذا الموديل يوفّر خيارات الاختيار والتحقق من صحة المرجع.
 */
class AssetHolder
{
    /** قوائم الحاملين المتاحين للاختيار في نموذج الإسناد، مقيّدة بالشركة. */
    public static function selectable(int $companyId): array
    {
        $employees = [];
        if (ModuleManager::isActive('employees')) {
            $employees = Database::select(
                "SELECT id, full_name, job_title FROM employees_profiles
                  WHERE company_id = :c AND status != 'terminated'
                  ORDER BY full_name",
                ['c' => $companyId]
            );
        }

        $users = Database::select(
            "SELECT id, name, email FROM users
              WHERE company_id = :c AND status = 'active'
              ORDER BY name",
            ['c' => $companyId]
        );

        return ['employees' => $employees, 'users' => $users];
    }

    /**
     * تُتحقّق من أن (النوع + المعرّف) يخصّ الشركة فعلاً، وتُرجع لقطة اسم الحامل،
     * أو null إن كان المرجع غير صالح. للـ manual يُعتمد الاسم الممرَّر مباشرة.
     */
    public static function resolveName(int $companyId, string $type, ?int $ref, string $manualName): ?string
    {
        if ($type === 'manual') {
            $manualName = trim($manualName);
            return $manualName !== '' ? mb_substr($manualName, 0, 180) : null;
        }

        if ($type === 'employee') {
            if (!ModuleManager::isActive('employees') || !$ref) {
                return null;
            }
            $row = Database::first(
                'SELECT full_name FROM employees_profiles WHERE id = :id AND company_id = :c',
                ['id' => $ref, 'c' => $companyId]
            );
            return $row['full_name'] ?? null;
        }

        if ($type === 'user') {
            if (!$ref) {
                return null;
            }
            $row = Database::first(
                'SELECT name FROM users WHERE id = :id AND company_id = :c',
                ['id' => $ref, 'c' => $companyId]
            );
            return $row['name'] ?? null;
        }

        return null;
    }

    /**
     * معرّف مستخدم النظام المرتبط بهذا الحامل (لإرسال التنبيهات) أو null:
     *  - user: المعرّف نفسه
     *  - employee: linked_user_id للملف الوظيفي إن كان مربوطاً
     *  - manual: لا حساب
     */
    public static function linkedUserId(int $companyId, string $type, ?int $ref): ?int
    {
        if ($type === 'user' && $ref) {
            return $ref;
        }
        if ($type === 'employee' && $ref && ModuleManager::isActive('employees')) {
            $row = Database::first(
                'SELECT linked_user_id FROM employees_profiles WHERE id = :id AND company_id = :c',
                ['id' => $ref, 'c' => $companyId]
            );
            return $row && $row['linked_user_id'] ? (int) $row['linked_user_id'] : null;
        }
        return null;
    }
}
