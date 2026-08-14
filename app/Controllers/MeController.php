<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\View;

/**
 * صفحة «ملفي»: بوابة الموظف الشخصية - تجمع في مكان واحد كل ما يخصّه من
 * الإضافات المفعّلة (عهدي، إجازاتي ورصيدي، خطاباتي، مهامي، حضوري، اجتماعاتي)
 * بدل تنقّله بين خمس صفحات. كل قسم معزول بـ try/catch فلا يُسقط خطأ قسمٍ الصفحة.
 */
class MeController
{
    public function index(): void
    {
        $userId = Auth::id();
        $companyId = (int) (Auth::companyId() ?? 0);
        $sections = [];

        if ($companyId) {
            $sections['tasks'] = $this->safe(fn () => $this->tasksSection($companyId, $userId));
            $sections['leaves'] = $this->safe(fn () => $this->leavesSection($companyId, $userId));
            $sections['assets'] = $this->safe(fn () => $this->assetsSection($companyId, $userId));
            $sections['letters'] = $this->safe(fn () => $this->lettersSection($companyId, $userId));
            $sections['attendance'] = $this->safe(fn () => $this->attendanceSection($companyId, $userId));
            $sections['meetings'] = $this->safe(fn () => $this->meetingsSection($companyId, $userId));
            $sections['payslips'] = $this->safe(fn () => $this->payslipSection($companyId, $userId));
        }

        View::render('me.index', [
            'pageTitle' => 'ملفي',
            'sections' => array_filter($sections),
        ]);
    }

    private function safe(callable $fn): ?array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            log_exception($e);
            return null;
        }
    }

    private function tasksSection(int $companyId, int $userId): ?array
    {
        if (!ModuleManager::isActive('tasks')) {
            return null;
        }
        $rows = Database::select(
            "SELECT id, title, status, priority, due_date FROM tasks_tasks
              WHERE company_id = :c AND assignee_id = :u AND status IN ('todo','in_progress','in_review')
              ORDER BY (due_date IS NULL), due_date LIMIT 6",
            ['c' => $companyId, 'u' => $userId]
        );
        $late = Database::count(
            'tasks_tasks',
            "company_id = :c AND assignee_id = :u AND status IN ('todo','in_progress','in_review') AND due_date IS NOT NULL AND due_date < CURDATE()",
            ['c' => $companyId, 'u' => $userId]
        );
        return ['rows' => $rows, 'late' => $late];
    }

    private function leavesSection(int $companyId, int $userId): ?array
    {
        if (!ModuleManager::isActive('employees')) {
            return null;
        }
        $own = Database::first(
            'SELECT * FROM employees_profiles WHERE company_id = :c AND linked_user_id = :u',
            ['c' => $companyId, 'u' => $userId]
        );
        if (!$own) {
            return null;
        }
        return [
            'balance' => (int) ($own['annual_leave_balance'] ?? 0),
            'rows' => Database::select(
                'SELECT * FROM employees_leaves WHERE employee_id = :e ORDER BY id DESC LIMIT 4',
                ['e' => $own['id']]
            ),
            'employeeId' => (int) $own['id'],
        ];
    }

    private function assetsSection(int $companyId, int $userId): ?array
    {
        if (!ModuleManager::isActive('assets')) {
            return null;
        }
        return ['rows' => \Modules\Assets\Models\Asset::currentlyHeldByUser($companyId, $userId)];
    }

    private function lettersSection(int $companyId, int $userId): ?array
    {
        if (!ModuleManager::isActive('forms') || !ModuleManager::isActive('employees')) {
            return null;
        }
        $own = Database::first(
            'SELECT id FROM employees_profiles WHERE company_id = :c AND linked_user_id = :u',
            ['c' => $companyId, 'u' => $userId]
        );
        if (!$own) {
            return null;
        }
        return ['rows' => Database::select(
            'SELECT id, title, number, created_at FROM forms_letters
              WHERE company_id = :c AND employee_id = :e ORDER BY id DESC LIMIT 5',
            ['c' => $companyId, 'e' => $own['id']]
        )];
    }

    private function attendanceSection(int $companyId, int $userId): ?array
    {
        if (!ModuleManager::isActive('checkins')) {
            return null;
        }
        return [
            'today' => Database::first(
                'SELECT * FROM checkins_attendance WHERE company_id = :c AND user_id = :u AND work_date = :d',
                ['c' => $companyId, 'u' => $userId, 'd' => date('Y-m-d')]
            ),
            'monthDays' => Database::count(
                'checkins_attendance',
                'company_id = :c AND user_id = :u AND work_date >= :m',
                ['c' => $companyId, 'u' => $userId, 'm' => date('Y-m-01')]
            ),
        ];
    }

    private function payslipSection(int $companyId, int $userId): ?array
    {
        if (!ModuleManager::isActive('employees')) {
            return null;
        }
        $rows = Database::select(
            "SELECT i.*, r.month FROM employees_payroll_items i
               JOIN employees_payroll_runs r ON r.id = i.run_id
               JOIN employees_profiles e ON e.id = i.employee_id
              WHERE r.company_id = :c AND r.status = 'approved' AND e.linked_user_id = :u
              ORDER BY r.month DESC LIMIT 3",
            ['c' => $companyId, 'u' => $userId]
        );
        return $rows ? ['rows' => $rows] : null;
    }

    private function meetingsSection(int $companyId, int $userId): ?array
    {
        if (!ModuleManager::isActive('meetings')) {
            return null;
        }
        return ['rows' => Database::select(
            "SELECT DISTINCT m.id, m.title, m.starts_at, m.location FROM meetings_meetings m
               LEFT JOIN meetings_attendees a ON a.meeting_id = m.id
              WHERE m.company_id = :c AND m.status = 'scheduled' AND m.starts_at >= NOW()
                AND (m.created_by = :u1 OR a.user_id = :u2)
              ORDER BY m.starts_at LIMIT 5",
            ['c' => $companyId, 'u1' => $userId, 'u2' => $userId]
        )];
    }
}
