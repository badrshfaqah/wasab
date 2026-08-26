<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ModuleManager;
use App\Core\Permission;
use App\Core\Request;

/**
 * مزوّد لوحة الأوامر: يجمع في استجابة واحدة الشاشات المتاحة للمستخدم، وأوامر
 * الإنشاء السريعة، ونتائج البحث الموحّد من الإضافات - كلها مقيّدة بصلاحياته.
 */
class PaletteController
{
    public function search(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = Auth::user();
        if (!$user) {
            echo json_encode(['groups' => []]);
            exit;
        }

        $q = trim((string) Request::query('q', ''));
        $groups = [];

        $screens = array_values(array_filter(self::screens($user), fn ($s) => self::matches($s['label'], $q)));
        if ($screens) {
            $groups[] = ['title' => 'الشاشات', 'items' => array_slice($screens, 0, 8)];
        }

        $actions = array_values(array_filter(self::actions(), fn ($a) => self::matches($a['label'], $q)));
        if ($actions) {
            $groups[] = ['title' => 'إجراءات سريعة', 'items' => array_slice($actions, 0, 6)];
        }

        // السجلات: تحتاج حرفين على الأقل حتى لا نُثقل القاعدة بكل ضغطة
        if (mb_strlen($q) >= 2) {
            // مزوّدو البحث يعيدون قائمة مسطّحة، كل عنصر يحمل إضافته واسمها
            $records = [];
            foreach (ModuleManager::collectSearchResults($user, $q) as $row) {
                $label = trim((string) ($row['title'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $records[] = [
                    'label' => $label,
                    'hint' => (string) ($row['subtitle'] ?: ($row['module_name'] ?? '')),
                    'url' => (string) ($row['url'] ?? ''),
                ];
            }
            if ($records) {
                $groups[] = ['title' => 'السجلات', 'items' => array_slice($records, 0, 12)];
            }
        }

        echo json_encode(['groups' => $groups], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function matches(string $label, string $q): bool
    {
        return $q === '' || mb_stripos($label, $q) !== false;
    }

    /** الشاشات = نفس روابط القائمة الجانبية، فلا يظهر ما لا يملكه المستخدم. */
    private static function screens(array $user): array
    {
        $items = [
            ['label' => 'الرئيسية', 'url' => route('/'), 'hint' => ''],
            ['label' => 'بانتظار قرارك', 'url' => route('/approvals'), 'hint' => ''],
            ['label' => 'ملفي', 'url' => route('/me'), 'hint' => ''],
            ['label' => 'التقويم', 'url' => route('/calendar'), 'hint' => ''],
        ];
        foreach (ModuleManager::collectNavItems($user) as $item) {
            $items[] = ['label' => (string) $item['label'], 'url' => (string) $item['url'], 'hint' => ''];
        }
        if (Auth::isCompanyAdmin() || Auth::isSystemAdmin()) {
            foreach ([
                ['التقارير', '/reports'], ['المستخدمون', '/users'], ['الأدوار والصلاحيات', '/roles'],
                ['الإعدادات', '/settings'], ['أختام الشركة', '/stamps'], ['سجل العمليات', '/activity-log'],
            ] as [$label, $path]) {
                $items[] = ['label' => $label, 'url' => route($path), 'hint' => 'إدارة'];
            }
        }
        if (Auth::isSystemAdmin()) {
            foreach ([['الإضافات', '/extensions'], ['لوحة النظام', '/admin'], ['الشركات', '/companies']] as [$label, $path]) {
                $items[] = ['label' => $label, 'url' => route($path), 'hint' => 'النظام'];
            }
        }
        return $items;
    }

    /** أوامر الإنشاء - تظهر فقط إن كانت إضافتها مفعّلة والصلاحية متوفرة. */
    private static function actions(): array
    {
        $out = [];
        $add = function (string $label, string $path, string $module, string $permission) use (&$out): void {
            if (ModuleManager::isActive($module) && Permission::check($permission)) {
                $out[] = ['label' => $label, 'url' => route($path), 'hint' => 'إنشاء'];
            }
        };
        $add('مهمة جديدة', '/tasks/create', 'tasks', 'tasks.create');
        $add('مستند جديد', '/documents/create', 'documents', 'documents.create');
        $add('اجتماع جديد', '/meetings/create', 'meetings', 'meetings.create');
        $add('جهة جديدة', '/contacts/orgs/create', 'contacts', 'contacts.create');
        $add('فرد جديد', '/contacts/people/create', 'contacts', 'contacts.create');
        $add('رفع ملف للأرشيف', '/archive/upload', 'archive', 'archive.create');
        $add('مصروف جديد', '/expenses/create', 'expenses', 'expenses.create');
        return $out;
    }
}
