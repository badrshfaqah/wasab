<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\Database;
use App\Core\Notification;
use Modules\Mobileapi\Support\Api;

class NotificationsApiController
{
    /** GET /api/v1/notifications?page= */
    public function index(): void
    {
        $user = Api::user();
        $userId = (int) $user['id'];

        $page = max(1, (int) Api::input('page', 1));
        $perPage = 20;
        $total = Database::count('notifications', 'user_id = :u', ['u' => $userId]);

        $rows = Database::select(
            'SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            ['u' => $userId]
        );

        Api::ok([
            'notifications' => array_map(fn ($n) => [
                'id' => (int) $n['id'],
                'title' => $n['title'],
                'message' => $n['message'] ?? null,
                // نعيد المسار النسبي فقط ليحوّله التطبيق لشاشة داخلية.
                'path' => $this->relativePath($n['url'] ?? null),
                'is_read' => (bool) $n['is_read'],
                'created_at' => $n['created_at'],
            ], $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'unread' => Notification::unreadCount($userId),
        ]);
    }

    /** POST /api/v1/notifications/read  {id} */
    public function markRead(): void
    {
        $user = Api::user();
        $id = (int) Api::input('id', 0);
        if ($id > 0) {
            Notification::markRead((int) $user['id'], $id);
        }
        Api::ok(['unread' => Notification::unreadCount((int) $user['id'])]);
    }

    /** POST /api/v1/notifications/read-all */
    public function markAllRead(): void
    {
        $user = Api::user();
        Notification::markAllRead((int) $user['id']);
        Api::ok(['unread' => 0]);
    }

    private function relativePath(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: null;
        if ($path === null) {
            return null;
        }
        $query = parse_url($url, PHP_URL_QUERY);
        return $path . ($query ? '?' . $query : '');
    }
}
