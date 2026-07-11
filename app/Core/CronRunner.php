<?php

namespace App\Core;

/**
 * نقطة الدخول المنطقية لمهام الصيانة الدورية (يُشغّلها cron.php بجذر المشروع، عادة
 * كمهمة Cron من لوحة تحكم الاستضافة كل ٥-١٥ دقيقة). تتولى تذكيرات النواة (أحداث
 * التقويم الخاصة بالشركة)، ثم تُفوّض لكل إضافة مفعّلة تملك modules/{key}/cron.php
 * الخاص بها (مثل تذكير الاجتماعات القريبة) عبر ModuleManager::runCron().
 */
class CronRunner
{
    public static function run(): void
    {
        self::remindCompanyEvents();
        ModuleManager::runCron();
    }

    /** تذكير أعضاء الشركة يوم الحدث بأحداث التقويم الخاصة بالشركة - مرة واحدة فقط لكل حدث. */
    private static function remindCompanyEvents(): void
    {
        $events = Database::select(
            'SELECT * FROM calendar_events WHERE event_date = :d AND reminder_sent_at IS NULL',
            ['d' => date('Y-m-d')]
        );

        foreach ($events as $event) {
            $users = Database::select(
                'SELECT id FROM users WHERE company_id = :c AND status = "active"',
                ['c' => $event['company_id']]
            );
            foreach ($users as $user) {
                Notification::send(
                    (int) $user['id'],
                    'تذكير: ' . $event['title'],
                    $event['description'] ?? '',
                    route('/calendar')
                );
            }

            Database::update('calendar_events', ['reminder_sent_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $event['id']]);
        }
    }
}
