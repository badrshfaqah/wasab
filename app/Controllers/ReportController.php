<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\View;

/**
 * صفحة تقارير مجمّعة على مستوى الشركة كاملة - خاصة بمدير الشركة (أو مدير النظام
 * لاطلاعه العام)، وليست موزّعة حسب صلاحيات كل مستخدم كودجات الصفحة الرئيسية.
 */
class ReportController
{
    public function index(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            return;
        }

        $companyId = Auth::companyId();
        if (!$companyId && !Auth::isSystemAdmin()) {
            View::render('reports.no-company', ['pageTitle' => 'التقارير']);
            return;
        }

        $user = Auth::user();
        $stats = $companyId ? ModuleManager::collectReportStats($user) : [];

        $coreStats = [];
        if ($companyId) {
            $coreStats[] = ['label' => 'عدد المستخدمين', 'value' => Database::count('users', 'company_id = :c AND status = "active"', ['c' => $companyId]), 'icon' => '👥', 'color' => 'primary'];
            $coreStats[] = ['label' => 'الإضافات المفعّلة', 'value' => Database::count('modules', 'status = "active"'), 'icon' => '🧩', 'color' => 'muted'];
        }

        View::render('reports.index', [
            'pageTitle' => 'التقارير',
            'coreStats' => $coreStats,
            'moduleStats' => $stats,
        ]);
    }
}
