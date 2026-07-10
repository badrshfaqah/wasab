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

        View::render('dashboard.index', [
            'pageTitle' => 'الرئيسية',
            'summary' => $summary,
            'widgets' => $widgets,
        ]);
    }
}
