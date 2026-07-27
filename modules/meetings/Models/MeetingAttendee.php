<?php

namespace Modules\Meetings\Models;

use App\Core\Database;

class MeetingAttendee
{
    public static function forMeeting(int $meetingId): array
    {
        return Database::select(
            'SELECT a.*, u.name AS user_name, u.email AS user_email
               FROM meetings_attendees a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE a.meeting_id = :id
              ORDER BY a.id',
            ['id' => $meetingId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM meetings_attendees WHERE id = :id', ['id' => $id]);
    }

    public static function findForUser(int $meetingId, int $userId): ?array
    {
        return Database::first(
            'SELECT * FROM meetings_attendees WHERE meeting_id = :m AND user_id = :u',
            ['m' => $meetingId, 'u' => $userId]
        );
    }

    /** المدعو الخارجي عبر رمز رابط تأكيد الحضور (بلا تسجيل دخول). */
    public static function findByToken(string $token): ?array
    {
        return Database::first('SELECT * FROM meetings_attendees WHERE token = :t', ['t' => $token]);
    }

    public static function addInternal(int $meetingId, int $userId): void
    {
        // منشئ الاجتماع لا يُسأل عن تأكيد حضور اجتماعه - يُعتمد حضوره تلقائياً
        $meeting = Database::first('SELECT created_by FROM meetings_meetings WHERE id = :id', ['id' => $meetingId]);
        $isCreator = $meeting && (int) $meeting['created_by'] === $userId;

        Database::insert('meetings_attendees', [
            'meeting_id' => $meetingId,
            'user_id' => $userId,
            'response' => $isCreator ? 'accepted' : 'pending',
            'responded_at' => $isCreator ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function addExternal(int $meetingId, string $name, ?string $contact): void
    {
        Database::insert('meetings_attendees', [
            'meeting_id' => $meetingId,
            'external_name' => $name,
            'external_contact' => $contact,
            'token' => bin2hex(random_bytes(24)),
            'response' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function clearForMeeting(int $meetingId): void
    {
        Database::delete('meetings_attendees', 'meeting_id = :id', ['id' => $meetingId]);
    }

    public static function respond(int $id, string $response): void
    {
        Database::update('meetings_attendees', [
            'response' => $response,
            'responded_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }
}
