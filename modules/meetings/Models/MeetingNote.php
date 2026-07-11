<?php

namespace Modules\Meetings\Models;

use App\Core\Database;

class MeetingNote
{
    public static function forMeeting(int $meetingId): array
    {
        return Database::select(
            'SELECT n.*, u.name AS user_name
               FROM meetings_notes n
               LEFT JOIN users u ON u.id = n.user_id
              WHERE n.meeting_id = :id
              ORDER BY n.created_at DESC',
            ['id' => $meetingId]
        );
    }

    public static function add(int $meetingId, ?int $userId, string $phase, string $body): void
    {
        Database::insert('meetings_notes', [
            'meeting_id' => $meetingId,
            'user_id' => $userId,
            'phase' => $phase,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
