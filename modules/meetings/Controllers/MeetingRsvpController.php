<?php

namespace Modules\Meetings\Controllers;

use App\Core\ActivityLog;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;
use Modules\Meetings\Models\Meeting;
use Modules\Meetings\Models\MeetingAttendee;

/**
 * نقطة وصول عامة بدون تسجيل دخول لتأكيد/رفض حضور مدعو خارجي لا يملك حساباً بالنظام
 * - نفس أسلوب رابط مشاركة الأرشيف المؤقت: الأمان يعتمد على توكن عشوائي طويل غير
 * قابل للتخمين (24 بايت hex) بدل جلسة مصادقة.
 */
class MeetingRsvpController
{
    public function show(array $params): void
    {
        $attendee = $this->findAttendee((string) ($params['token'] ?? ''));
        if (!$attendee) {
            return;
        }

        $meeting = Meeting::find((int) $attendee['meeting_id']);
        if (!$meeting) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }

        View::render('meetings::rsvp', [
            'pageTitle' => 'تأكيد الحضور',
            'meeting' => $meeting,
            'attendee' => $attendee,
            'typeLabels' => Meeting::typeLabels(),
        ], '');
    }

    public function respond(array $params): void
    {
        $attendee = $this->findAttendee((string) ($params['token'] ?? ''));
        if (!$attendee) {
            return;
        }

        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، أعد تحميل الصفحة والمحاولة مرة أخرى.');
            redirect('/meetings/rsvp/' . $attendee['token']);
        }

        $response = Request::input('response');
        if (!in_array($response, ['accepted', 'declined'], true)) {
            flash_set('error', 'استجابة غير صحيحة.');
            redirect('/meetings/rsvp/' . $attendee['token']);
        }

        MeetingAttendee::respond($attendee['id'], $response);
        ActivityLog::log(
            'meetings.respond',
            'meeting',
            $attendee['meeting_id'],
            ($response === 'accepted' ? 'قبول' : 'اعتذار') . " دعوة اجتماع (مدعو خارجي: {$attendee['external_name']})"
        );

        flash_set('success', 'تم تسجيل ردك، شكراً لك.');
        redirect('/meetings/rsvp/' . $attendee['token']);
    }

    private function findAttendee(string $token): ?array
    {
        $attendee = $token !== '' ? MeetingAttendee::findByToken($token) : null;
        if (!$attendee) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return null;
        }
        return $attendee;
    }
}
