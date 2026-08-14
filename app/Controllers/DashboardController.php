<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\View;

class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $widgets = ModuleManager::collectDashboardWidgets($user);

        $summary = [];
        if (Auth::isSystemAdmin()) {
            $summary[] = ['label' => 'الشركات', 'value' => Database::count('companies'), 'icon' => '🏢', 'color' => 'primary'];
            $summary[] = ['label' => 'المستخدمون', 'value' => Database::count('users'), 'icon' => '👥', 'color' => 'success'];
            $summary[] = ['label' => 'الإضافات المفعلة', 'value' => Database::count('modules', 'status = "active"'), 'icon' => '🧩', 'color' => 'warning'];

            $pendingUpdates = ModuleManager::countPendingUpdates();
            if ($pendingUpdates > 0) {
                $summary[] = [
                    'label' => $pendingUpdates > 1 ? 'إضافات بحاجة إلى تحديث' : 'إضافة بحاجة إلى تحديث',
                    'value' => $pendingUpdates,
                    'icon' => '⬆️',
                    'color' => 'danger',
                    'url' => route('/extensions'),
                ];
            }
        } elseif (Auth::isCompanyAdmin() || Auth::companyId()) {
            $summary[] = ['label' => 'مستخدمو الشركة', 'value' => Database::count('users', 'company_id = :c', ['c' => Auth::companyId()]), 'icon' => '👥', 'color' => 'primary'];
        }

        // تخصيص البطاقات: كل بطاقة تُعرَّف باسمها (label للإحصائيات والاختصارات،
        // title للقوائم)، والمخفية محفوظة على المستخدم كمصفوفة JSON.
        $labelOf = fn (array $w): string => (string) ($w['label'] ?? $w['title'] ?? '');
        $allLabels = array_values(array_unique(array_filter(array_map($labelOf, array_merge($summary, $widgets)))));
        $hidden = [];
        if (!empty($user['dashboard_prefs'])) {
            $decoded = json_decode((string) $user['dashboard_prefs'], true);
            if (is_array($decoded)) {
                $hidden = array_values(array_filter(array_map('strval', $decoded)));
            }
        }
        if ($hidden) {
            $keep = fn (array $w): bool => !in_array($labelOf($w), $hidden, true);
            $summary = array_values(array_filter($summary, $keep));
            $widgets = array_values(array_filter($widgets, $keep));
        }

        View::render('dashboard.index', [
            'pageTitle' => 'الرئيسية',
            'summary' => $summary,
            'widgets' => $widgets,
            'allLabels' => $allLabels,
            'hiddenLabels' => $hidden,
        ]);
    }

    /** حفظ تخصيص بطاقات الرئيسية: المحدد يبقى ظاهراً وغير المحدد يُخفى. */
    public function savePrefs(): void
    {
        if (!\App\Core\Csrf::verify(\App\Core\Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/');
        }
        $all = (array) \App\Core\Request::input('all_labels', []);
        $visible = (array) \App\Core\Request::input('visible', []);
        $hidden = array_values(array_diff(array_map('strval', $all), array_map('strval', $visible)));
        Database::update('users', [
            'dashboard_prefs' => $hidden ? json_encode($hidden, JSON_UNESCAPED_UNICODE) : null,
        ], 'id = :id', ['id' => Auth::id()]);
        flash_set('success', 'حُفظ تخصيص لوحتك.');
        redirect('/');
    }
}
