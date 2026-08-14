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

    /**
     * التقرير الشهري الموحّد: أرقام الشهر من كل إضافة مفعّلة في صفحة طباعة أنيقة
     * يقدّمها المدير للإدارة. كل قسم معزول بـ try/catch فلا يُسقط غيابُ إضافةٍ الصفحة.
     */
    public function monthly(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            return;
        }
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('reports.no-company', ['pageTitle' => 'التقرير الشهري']);
            return;
        }

        $month = (string) \App\Core\Request::query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $safe = function (callable $fn) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                log_exception($e);
                return null;
            }
        };
        $active = fn (string $key) => ModuleManager::isActive($key);
        $sections = [];

        if ($active('tasks')) {
            $sections['المهام'] = $safe(fn () => [
                'أُنشئت خلال الشهر' => Database::count('tasks_tasks', 'company_id = :c AND created_at BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to . ' 23:59:59']),
                'أُنجزت خلال الشهر' => Database::count('tasks_tasks', 'company_id = :c AND completed_at BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to . ' 23:59:59']),
                'متأخرة حالياً' => Database::count('tasks_tasks', "company_id = :c AND status IN ('todo','in_progress','in_review') AND due_date < CURDATE()", ['c' => $companyId]),
            ]);
        }
        if ($active('checkins')) {
            $sections['المتابعة والحضور'] = $safe(fn () => [
                'متابعات يومية مسجّلة' => Database::count('checkins_entries', 'company_id = :c AND entry_date BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to]),
                'أيام حضور مسجّلة' => Database::count('checkins_attendance', 'company_id = :c AND work_date BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to]),
            ]);
        }
        if ($active('employees')) {
            $sections['الملف الوظيفي'] = $safe(fn () => [
                'الموظفون النشطون' => Database::count('employees_profiles', "company_id = :c AND status = 'active'", ['c' => $companyId]),
                'إجازات اعتُمدت' => Database::count('employees_leaves', "company_id = :c AND status = 'approved' AND start_date <= :t AND end_date >= :f", ['c' => $companyId, 'f' => $from, 't' => $to]),
                'طلبات معلّقة' => Database::count('employees_leaves', "company_id = :c AND status = 'pending'", ['c' => $companyId]),
            ]);
        }
        if ($active('documents')) {
            $sections['المستندات'] = $safe(fn () => [
                'مستندات أُنشئت' => Database::count('documents_documents', 'company_id = :c AND created_at BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to . ' 23:59:59']),
                'مستندات وُقّعت' => Database::count('documents_documents', 'company_id = :c AND signed_at BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to . ' 23:59:59']),
            ]);
        }
        if ($active('forms')) {
            $sections['الخطابات'] = $safe(fn () => [
                'خطابات صدرت' => Database::count('forms_letters', 'company_id = :c AND created_at BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to . ' 23:59:59']),
            ]);
        }
        if ($active('assets')) {
            $sections['العهد والأصول'] = $safe(fn () => [
                'أصول بعهدة حالياً' => Database::count('assets_assets', "company_id = :c AND status = 'assigned'", ['c' => $companyId]),
                'محاضر تسليم خلال الشهر' => Database::count('assets_handovers', 'company_id = :c AND created_at BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to . ' 23:59:59']),
            ]);
        }
        if ($active('meetings')) {
            $sections['الاجتماعات'] = $safe(fn () => [
                'اجتماعات عُقدت' => Database::count('meetings_meetings', "company_id = :c AND status != 'cancelled' AND starts_at BETWEEN :f AND :t", ['c' => $companyId, 'f' => $from, 't' => $to . ' 23:59:59']),
            ]);
        }
        if ($active('inbox')) {
            $sections['مركز المراسلات'] = $safe(fn () => [
                'رسائل وردت' => Database::count('inbox_messages', 'company_id = :c AND received_at BETWEEN :f AND :t', ['c' => $companyId, 'f' => $from, 't' => $to . ' 23:59:59']),
            ]);
        }

        View::render('reports.monthly', [
            'pageTitle' => 'التقرير الشهري',
            'month' => $month,
            'sections' => array_filter($sections),
            'company' => Database::first('SELECT name FROM companies WHERE id = :id', ['id' => $companyId]),
        ], '');
    }
}
