<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Permission;
use App\Core\View;

/**
 * مركز الموافقات: صفحة واحدة تجمع كل ما ينتظر قرار المستخدم الحالي من الإضافات
 * المفعّلة (طلبات إجازة، مستندات للاعتماد/التوقيع، مهام تحتاج اعتماده، دعوات
 * اجتماعات، طلبات خطابات، مصروفات). كل قسم معزول فلا يُسقط خطأُ قسمٍ الصفحة.
 */
class ApprovalsController
{
    public function index(): void
    {
        View::render('approvals.index', [
            'pageTitle' => 'بانتظار قرارك',
            'sections' => array_filter(self::collect()),
        ]);
    }

    /** عدّاد شارة القائمة الجانبية (مجموع كل البنود المنتظرة). */
    public static function pendingCount(): int
    {
        $total = 0;
        foreach (array_filter(self::collect()) as $section) {
            $total += count($section['rows']);
        }
        return $total;
    }

    /** يجمع أقسام الموافقات - تُعاد المصفوفة نفسها للصفحة وللعدّاد. */
    private static function collect(): array
    {
        $userId = Auth::id();
        $companyId = (int) (Auth::companyId() ?? 0);
        if (!$companyId) {
            return [];
        }
        $safe = function (callable $fn) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                log_exception($e);
                return null;
            }
        };
        $isAdmin = Auth::isSystemAdmin() || Auth::isCompanyAdmin();
        $sections = [];

        // طلبات الإجازة (لمن يقرر فيها)
        if (ModuleManager::isActive('employees') && ($isAdmin || Permission::check('employees.manage') || Permission::check('employees.edit'))) {
            $sections['leaves'] = $safe(fn () => [
                'title' => '🌴 طلبات إجازة',
                'url' => route('/employees/leaves'),
                'rows' => Database::select(
                    "SELECT l.id, e.full_name AS label, CONCAT(l.start_date, ' ← ', l.end_date) AS meta
                       FROM employees_leaves l JOIN employees_profiles e ON e.id = l.employee_id
                      WHERE l.company_id = :c AND l.status = 'pending' ORDER BY l.id LIMIT 20",
                    ['c' => $companyId]
                ),
            ]);
        }

        // مستندات بانتظار الاعتماد / التوقيع
        if (ModuleManager::isActive('documents')) {
            if ($isAdmin || Permission::check('documents.approve')) {
                $sections['doc_approve'] = $safe(fn () => [
                    'title' => '📄 مستندات بانتظار الاعتماد',
                    'url' => route('/documents?status=pending_approval'),
                    'rows' => Database::select(
                        "SELECT id, title AS label, '' AS meta FROM documents_documents
                          WHERE company_id = :c AND status = 'pending_approval'
                            AND id NOT IN (SELECT document_id FROM documents_approvals WHERE approved_by = :me)
                          ORDER BY id DESC LIMIT 20",
                        ['c' => $companyId, 'me' => $userId]
                    ),
                    'itemUrl' => '/documents/',
                ]);
            }
            if ($isAdmin || Permission::check('documents.sign')) {
                $sections['doc_sign'] = $safe(fn () => [
                    'title' => '✍️ مستندات جاهزة للتوقيع',
                    'url' => route('/documents?status=approved'),
                    'rows' => Database::select(
                        "SELECT id, title AS label, number AS meta FROM documents_documents
                          WHERE company_id = :c AND status = 'approved' ORDER BY id DESC LIMIT 20",
                        ['c' => $companyId]
                    ),
                    'itemUrl' => '/documents/',
                ]);
            }
        }

        // مهام بانتظار اعتماد المستخدم (هو المعتمِد المحدد لها)
        if (ModuleManager::isActive('tasks')) {
            $sections['tasks'] = $safe(fn () => [
                'title' => '📋 مهام تنتظر اعتمادك',
                'url' => route('/tasks'),
                'rows' => Database::select(
                    "SELECT id, title AS label, '' AS meta FROM tasks_tasks
                      WHERE company_id = :c AND requires_approval = 1 AND approved_at IS NULL
                        AND approver_id = :me AND status = 'in_review'
                      ORDER BY id DESC LIMIT 20",
                    ['c' => $companyId, 'me' => $userId]
                ),
                'itemUrl' => '/tasks/',
            ]);
        }

        // دعوات اجتماعات قادمة لم يردّ عليها
        if (ModuleManager::isActive('meetings')) {
            $sections['meetings'] = $safe(fn () => [
                'title' => '📅 دعوات اجتماعات بانتظار ردّك',
                'url' => route('/meetings'),
                'rows' => Database::select(
                    "SELECT m.id, m.title AS label, DATE_FORMAT(m.starts_at, '%m-%d %H:%i') AS meta
                       FROM meetings_attendees a JOIN meetings_meetings m ON m.id = a.meeting_id
                      WHERE m.company_id = :c AND a.user_id = :me AND a.response = 'pending'
                        AND m.status = 'scheduled' AND m.starts_at >= NOW()
                      ORDER BY m.starts_at LIMIT 20",
                    ['c' => $companyId, 'me' => $userId]
                ),
                'itemUrl' => '/meetings/',
            ]);
        }

        // طلبات خطابات (لمن يدير النماذج)
        if (ModuleManager::isActive('forms') && ($isAdmin || Permission::check('forms.manage'))) {
            $sections['letter_requests'] = $safe(fn () => [
                'title' => '📨 طلبات خطابات',
                'url' => route('/forms/requests'),
                'rows' => Database::select(
                    "SELECT r.id, CONCAT(e.full_name, ' — ', t.name) AS label, '' AS meta
                       FROM forms_requests r
                       JOIN employees_profiles e ON e.id = r.employee_id
                       JOIN forms_templates t ON t.id = r.template_id
                      WHERE r.company_id = :c AND r.status = 'pending' ORDER BY r.id LIMIT 20",
                    ['c' => $companyId]
                ),
            ]);
        }

        // مصروفات بانتظار الاعتماد (لمن يديرها)
        if (ModuleManager::isActive('expenses') && ($isAdmin || Permission::check('expenses.manage'))) {
            $sections['expenses'] = $safe(fn () => [
                'title' => '💰 مصروفات بانتظار الاعتماد',
                'url' => route('/expenses'),
                'rows' => Database::select(
                    "SELECT x.id, CONCAT(u.name, ' — ', FORMAT(x.amount, 2)) AS label, x.expense_date AS meta
                       FROM expenses_claims x JOIN users u ON u.id = x.user_id
                      WHERE x.company_id = :c AND x.status = 'pending' ORDER BY x.id LIMIT 20",
                    ['c' => $companyId]
                ),
            ]);
        }

        return $sections;
    }
}
