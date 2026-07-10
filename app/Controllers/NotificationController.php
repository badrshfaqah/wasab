<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Notification;
use App\Core\Request;
use App\Core\View;

class NotificationController
{
    public function index(): void
    {
        $page = max(1, (int) Request::query('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $userId = Auth::id();

        $total = Database::count('notifications', 'user_id = :u', ['u' => $userId]);
        $notifications = Database::select(
            "SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            ['u' => $userId]
        );

        View::render('notifications.index', [
            'pageTitle' => 'الإشعارات',
            'notifications' => $notifications,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function markRead(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            $this->respond(false);
            return;
        }
        Notification::markRead(Auth::id(), (int) Request::input('id'));
        $this->respond(true);
    }

    public function markAllRead(): void
    {
        if (Csrf::verify(Request::input('_csrf'))) {
            Notification::markAllRead(Auth::id());
        }
        redirect('/notifications');
    }

    private function respond(bool $ok): void
    {
        if (Request::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $ok]);
            return;
        }
        redirect('/notifications');
    }
}
