<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\WebPush;

/**
 * اشتراك/إلغاء اشتراك إشعارات الدفع (Web Push) لجهاز المستخدم الحالي.
 * تُستدعى بـ fetch من assets/js/push.js بجسم JSON، والرد JSON دائماً.
 */
class PushController
{
    public function subscribe(): void
    {
        $data = $this->jsonInput();
        if (!Csrf::verify((string) ($data['_csrf'] ?? ''))) {
            $this->respond(419, ['success' => false, 'error' => 'انتهت صلاحية الجلسة، أعد تحميل الصفحة.']);
            return;
        }

        $endpoint = (string) ($data['endpoint'] ?? '');
        $p256dh = (string) ($data['keys']['p256dh'] ?? '');
        $auth = (string) ($data['keys']['auth'] ?? '');

        if (!WebPush::saveSubscription(Auth::id(), $endpoint, $p256dh, $auth)) {
            $this->respond(422, ['success' => false, 'error' => 'بيانات اشتراك غير صالحة.']);
            return;
        }

        $this->respond(200, ['success' => true]);
    }

    public function unsubscribe(): void
    {
        $data = $this->jsonInput();
        if (!Csrf::verify((string) ($data['_csrf'] ?? ''))) {
            $this->respond(419, ['success' => false, 'error' => 'انتهت صلاحية الجلسة، أعد تحميل الصفحة.']);
            return;
        }

        WebPush::deleteSubscription(Auth::id(), (string) ($data['endpoint'] ?? ''));
        $this->respond(200, ['success' => true]);
    }

    private function jsonInput(): array
    {
        $decoded = json_decode(file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function respond(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
