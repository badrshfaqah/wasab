<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;

/**
 * لوحة مدير النظام: نظرة شاملة على كل الشركات (نشاط، مستخدمون، مساحة تخزين، إضافات).
 * محميّة بوسيط systemAdmin - بيانات مجمّعة فقط، بلا محتوى حسّاس لأي شركة.
 */
class AdminController
{
    public function dashboard(): void
    {
        // إحصاءات الشركات والمستخدمين
        $companyStats = Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'active') AS active,
                    SUM(status = 'inactive') AS inactive
               FROM companies"
        );
        $userStats = Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'active') AS active,
                    SUM(membership_type = 'system_admin') AS admins,
                    SUM(membership_type = 'company_admin') AS company_admins
               FROM users"
        );
        $moduleStats = Database::first(
            "SELECT COUNT(*) AS total, SUM(status = 'active') AS active FROM modules"
        );

        // الشركات مع عدّاد المستخدمين وآخر نشاط ومساحة التخزين
        $companies = Database::select(
            "SELECT c.id, c.name, c.status, c.created_at,
                    (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.status = 'active') AS users_count,
                    (SELECT MAX(a.created_at) FROM activity_log a WHERE a.company_id = c.id) AS last_activity
               FROM companies c
              ORDER BY c.id DESC"
        );
        foreach ($companies as &$c) {
            $c['storage_bytes'] = $this->companyStorageBytes((int) $c['id']);
        }
        unset($c);

        $totalStorage = array_sum(array_column($companies, 'storage_bytes'));

        // آخر النشاط عبر النظام (كل الشركات)
        $recentActivity = Database::select(
            "SELECT a.action, a.description, a.created_at, u.name AS user_name, c.name AS company_name
               FROM activity_log a
               LEFT JOIN users u ON u.id = a.user_id
               LEFT JOIN companies c ON c.id = a.company_id
              ORDER BY a.id DESC LIMIT 15"
        );

        View::render('admin.dashboard', [
            'pageTitle' => 'لوحة النظام',
            'companyStats' => $companyStats,
            'userStats' => $userStats,
            'moduleStats' => $moduleStats,
            'companies' => $companies,
            'totalStorage' => $totalStorage,
            'recentActivity' => $recentActivity,
        ]);
    }

    /** إجمالي بايتات ملفات شركة عبر كل مجلدات الرفع storage/uploads/{module}/{companyId}. */
    private function companyStorageBytes(int $companyId): int
    {
        $root = BASE_PATH . '/storage/uploads';
        if (!is_dir($root)) {
            return 0;
        }
        $total = 0;
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $companyDir = $moduleDir . '/' . $companyId;
            if (is_dir($companyDir)) {
                $total += $this->dirSize($companyDir);
            }
        }
        return $total;
    }

    private function dirSize(string $dir): int
    {
        $size = 0;
        $items = @scandir($dir);
        if ($items === false) {
            return 0;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $size += $this->dirSize($path);
            } elseif (is_file($path)) {
                $size += (int) @filesize($path);
            }
        }
        return $size;
    }
}
