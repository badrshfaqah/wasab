<?php

use App\Core\Database;
use App\Core\Notification;
use App\Core\Setting;
use Modules\Checkins\Controllers\CheckinController;

/**
 * التذكير اليومي: عند بلوغ وقت التذكير المحدد لكل شركة (في أيام عملها فقط)،
 * يُرسل تنبيه لكل مستخدم نشط لم يسجل متابعة اليوم - مرة واحدة يومياً لكل شركة
 * (علامة checkins_reminded_on بإعدادات الشركة تمنع التكرار بين دورات الجدولة).
 */
return function (): void {
    $today = date('Y-m-d');
    $nowTime = date('H:i');
    $todayW = (int) date('w');

    $companies = Database::select("SELECT id FROM companies WHERE status = 'active'");
    foreach ($companies as $company) {
        $companyId = (int) $company['id'];

        if (!in_array($todayW, CheckinController::workdays($companyId), true)) {
            continue;
        }
        if ($nowTime < (string) Setting::get('checkins_reminder_time', $companyId, '08:30')) {
            continue;
        }
        if ((string) Setting::get('checkins_reminded_on', $companyId, '') === $today) {
            continue;
        }

        // المستحقون: مدراء الشركة (يملكون كل الصلاحيات ضمنياً) + من له صلاحية التسجيل - ممن لم يسجل اليوم
        $pending = Database::select(
            'SELECT DISTINCT u.id
               FROM users u
               LEFT JOIN user_roles ur ON ur.user_id = u.id
               LEFT JOIN role_permissions rp ON rp.role_id = ur.role_id
               LEFT JOIN permissions p ON p.id = rp.permission_id
              WHERE u.company_id = :c AND u.status = "active"
                AND (u.membership_type = "company_admin" OR p.permission_key = "checkins.submit")
                AND NOT EXISTS (
                    SELECT 1 FROM checkins_entries e WHERE e.user_id = u.id AND e.entry_date = :d
                )',
            ['c' => $companyId, 'd' => $today]
        );

        foreach ($pending as $user) {
            Notification::send(
                (int) $user['id'],
                '📝 وقت المتابعة اليومية',
                'دقيقتان: ماذا أنجزت؟ ماذا ستعمل؟ هل من معوقات؟',
                route('/checkins')
            );
        }

        Setting::set('checkins_reminded_on', $today, $companyId);
    }
};
