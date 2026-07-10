<?php

namespace App\Core;

class Notification
{
    public static function send(int $userId, string $title, string $message = '', string $url = ''): void
    {
        Database::insert('notifications', [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function unreadCount(int $userId): int
    {
        return Database::count('notifications', 'user_id = :u AND is_read = 0', ['u' => $userId]);
    }

    public static function recent(int $userId, int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        return Database::select(
            "SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT {$limit}",
            ['u' => $userId]
        );
    }

    public static function markRead(int $userId, int $id): void
    {
        Database::update('notifications', ['is_read' => 1], 'id = :id AND user_id = :u', ['id' => $id, 'u' => $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::update('notifications', ['is_read' => 1], 'user_id = :u AND is_read = 0', ['u' => $userId]);
    }
}
