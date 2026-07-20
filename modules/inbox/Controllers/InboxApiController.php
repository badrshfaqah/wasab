<?php

namespace Modules\Inbox\Controllers;

use App\Core\Database;
use App\Core\Notification;
use Modules\Inbox\Models\InboxMessage;
use Modules\Inbox\Models\InboxSite;

/**
 * نقطة استقبال الرسائل من المواقع الخارجية (POST /api/inbox). عامة بلا مصادقة جلسة
 * ولا CSRF عمداً - التحقق يتم بمفتاح API فقط (ترويسة X-Api-Key أو حقل api_key)،
 * والمفتاح يحدد الموقع المصدر والشركة معاً. تقبل JSON أو Form-Data وترد JSON دائماً،
 * بنفس فلسفة المسارات العامة بالتوكن (رابط RSVP بالاجتماعات وروابط مشاركة الأرشيف).
 */
class InboxApiController
{
    private const MAX_BODY = 10000;
    private const MAX_EXTRA = 5000;

    public function receive(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $json = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        $input = function (string $key, $default = '') use ($json) {
            $value = $json[$key] ?? $_POST[$key] ?? $default;
            return is_string($value) ? trim($value) : $value;
        };

        $apiKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? $input('api_key')));
        $site = InboxSite::findByApiKey($apiKey);
        if (!$site) {
            $this->respond(401, ['success' => false, 'error' => 'مفتاح API غير صحيح أو الموقع معطّل.']);
            return;
        }

        $body = (string) ($input('message') ?: $input('body'));
        if ($body === '') {
            $this->respond(422, ['success' => false, 'error' => 'نص الرسالة (message) مطلوب.']);
            return;
        }

        // بيانات إضافية حرة من نموذج الموقع (أي حقول زائدة) تُخزَّن كـ JSON للعرض فقط.
        $extra = $json['extra'] ?? $_POST['extra'] ?? null;
        if (is_array($extra)) {
            $extra = json_encode($extra, JSON_UNESCAPED_UNICODE);
        }
        $extra = is_string($extra) && $extra !== '' ? mb_substr($extra, 0, self::MAX_EXTRA) : null;

        $messageId = InboxMessage::create([
            'company_id' => (int) $site['company_id'],
            'site_id' => (int) $site['id'],
            'sender_name' => mb_substr((string) $input('name'), 0, 150) ?: null,
            'sender_email' => mb_substr((string) $input('email'), 0, 190) ?: null,
            'sender_phone' => mb_substr((string) $input('phone'), 0, 50) ?: null,
            'subject' => mb_substr((string) $input('subject'), 0, 255) ?: null,
            'body' => mb_substr($body, 0, self::MAX_BODY),
            'extra' => $extra,
            'source_ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'is_read' => 0,
            'received_at' => date('Y-m-d H:i:s'),
        ]);

        InboxSite::touchLastMessage((int) $site['id']);
        $this->notifyRecipients($site, $messageId, (string) $input('name'), $body);

        $this->respond(201, ['success' => true, 'id' => $messageId]);
    }

    /**
     * تنبيه مستحقي الاطلاع بالشركة برسالة جديدة: مدراء الشركة + كل مستخدم يملك
     * صلاحية inbox.view عبر أدواره. يمر عبر Notification::send فيصل الإشعار داخل
     * النظام وعلى الجوال (Web Push) معاً لمن فعّل التنبيهات. أفضل جهد: أي فشل
     * يُسجَّل بصمت ولا يؤثر على استلام الرسالة (الرد 201 يصدر في كل الأحوال).
     */
    private function notifyRecipients(array $site, int $messageId, string $senderName, string $body): void
    {
        try {
            $recipients = Database::select(
                'SELECT DISTINCT u.id
                   FROM users u
                   LEFT JOIN user_roles ur ON ur.user_id = u.id
                   LEFT JOIN role_permissions rp ON rp.role_id = ur.role_id
                   LEFT JOIN permissions p ON p.id = rp.permission_id
                  WHERE u.company_id = :c AND u.status = "active"
                    AND (u.membership_type = "company_admin" OR p.permission_key = "inbox.view")',
                ['c' => (int) $site['company_id']]
            );

            $excerpt = mb_substr($body, 0, 80) . (mb_strlen($body) > 80 ? '...' : '');
            $title = '📨 رسالة جديدة من ' . $site['name'];
            $message = ($senderName !== '' ? $senderName . ': ' : '') . $excerpt;
            $url = route('/inbox/' . $messageId);

            foreach ($recipients as $r) {
                Notification::send((int) $r['id'], $title, $message, $url);
            }
        } catch (\Throwable $e) {
            log_exception($e);
        }
    }

    private function respond(int $code, array $data): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
