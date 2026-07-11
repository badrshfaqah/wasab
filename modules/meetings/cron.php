<?php

use App\Core\Database;
use App\Core\Notification;

/**
 * تذكير بالاجتماعات القريبة (خلال ٣٠ دقيقة القادمة) - مرة واحدة فقط لكل اجتماع، يصل
 * لمنشئ الاجتماع وكل حاضر داخلي (المدعوون الخارجيون لا يملكون حساباً بالنظام فلا
 * تصلهم إشعارات داخلية - لديهم رابط تأكيد الحضور فقط). يُستدعى من ModuleManager::runCron()
 * عبر cron.php بجذر المشروع.
 */
return function (): void {
    $meetings = Database::select(
        "SELECT * FROM meetings_meetings
          WHERE status = 'scheduled' AND reminder_sent_at IS NULL
            AND starts_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 MINUTE)"
    );

    foreach ($meetings as $meeting) {
        $recipients = [(int) $meeting['created_by']];
        $attendees = Database::select(
            'SELECT user_id FROM meetings_attendees WHERE meeting_id = :id AND user_id IS NOT NULL',
            ['id' => $meeting['id']]
        );
        foreach ($attendees as $attendee) {
            $recipients[] = (int) $attendee['user_id'];
        }

        foreach (array_unique($recipients) as $userId) {
            Notification::send(
                $userId,
                'تذكير باجتماع قريب',
                $meeting['title'] . ' الساعة ' . date('H:i', strtotime($meeting['starts_at'])),
                route('/meetings/' . $meeting['id'])
            );
        }

        Database::update('meetings_meetings', ['reminder_sent_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $meeting['id']]);
    }
};
