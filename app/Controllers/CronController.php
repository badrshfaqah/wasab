<?php

namespace App\Controllers;

use App\Core\CronRunner;
use App\Core\Setting;

/**
 * تشغيل مهام الجدولة عبر رابط ويب (بديل عن cron.php من سطر الأوامر) - للاستضافات
 * التي يسهل فيها جدولة "زيارة رابط" أكثر من أمر CLI، أو لخدمات المراقبة الخارجية.
 * محمي بتوكن عشوائي طويل يُعرض لمدير النظام في صفحة الإضافات، والمقارنة بـ
 * hash_equals ضد التخمين الزمني. نفس منطق cron.php تماماً (CronRunner::run).
 */
class CronController
{
    public function run(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $stored = (string) Setting::get('cron_web_token', null, '');
        if ($stored === '' || !hash_equals($stored, (string) ($params['token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'توكن غير صحيح.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            CronRunner::run();
            echo json_encode(['success' => true, 'ran_at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            log_exception($e);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'فشل تشغيل مهام الجدولة، راجع سجل الأخطاء.'], JSON_UNESCAPED_UNICODE);
        }
    }
}
