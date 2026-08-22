<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Permission;
use Modules\Inbox\Models\InboxMessage;
use Modules\Inbox\Models\InboxSite;
use Modules\Mobileapi\Support\Api;

/**
 * نقاط JSON لمركز المراسلات - نفس قواعد InboxController الويب:
 * العرض يؤشر الرسالة مقروءة تلقائياً لمن يملك الصلاحية، والحذف نهائي.
 */
class InboxApiController
{
    private const SCOPES = ['all', 'unread', 'read'];

    /** GET /api/v1/inbox?scope=&q=&site_id=&page= */
    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('inbox.view');

        $scope = (string) Api::input('scope', 'unread');
        if (!in_array($scope, self::SCOPES, true)) {
            $scope = 'unread';
        }

        $filters = [];
        $q = trim((string) Api::input('q', ''));
        if ($q !== '') {
            $filters['q'] = $q;
        }
        $siteId = (int) Api::input('site_id', 0);
        if ($siteId > 0) {
            $filters['site_id'] = $siteId;
        }

        $page = max(1, (int) Api::input('page', 1));
        $result = InboxMessage::paginate($companyId, $scope, $page, 20, $filters);

        Api::ok([
            'messages' => array_map([$this, 'summaryPayload'], $result['rows']),
            'total' => (int) $result['total'],
            'page' => $page,
            'per_page' => 20,
            'unread' => InboxMessage::unreadCount($companyId),
            'sites' => array_map(fn ($s) => [
                'id' => (int) $s['id'],
                'name' => $s['name'],
            ], InboxSite::forCompany($companyId)),
            'can_mark_read' => $this->canMarkRead(),
            'can_delete' => $this->canDelete(),
        ]);
    }

    /** GET /api/v1/inbox/{id} - يؤشرها مقروءة تلقائياً (نفس سلوك الويب). */
    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        Api::requirePermission('inbox.view');
        $message = $this->findVisible((int) $params['id'], $companyId);

        if (!$message['is_read'] && $this->canMarkRead()) {
            InboxMessage::markRead((int) $message['id'], Auth::id());
            $message = InboxMessage::find((int) $message['id']);
        }

        Api::ok([
            'message' => $this->detailPayload($message),
            'can_mark_read' => $this->canMarkRead(),
            'can_delete' => $this->canDelete(),
        ]);
    }

    /** POST /api/v1/inbox/{id}/read  {read: true|false} - ضبط صريح لا تبديل. */
    public function setRead(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canMarkRead()) {
            Api::error('لا تملك صلاحية تأشير الرسائل.', 403, 'forbidden');
        }
        $message = $this->findVisible((int) $params['id'], $companyId);

        $read = filter_var(Api::input('read', true), FILTER_VALIDATE_BOOLEAN);
        if ($read) {
            InboxMessage::markRead((int) $message['id'], Auth::id());
        } else {
            InboxMessage::markUnread((int) $message['id']);
        }

        Api::ok([
            'is_read' => $read,
            'unread' => InboxMessage::unreadCount($companyId),
        ]);
    }

    /** POST /api/v1/inbox/{id}/delete */
    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canDelete()) {
            Api::error('لا تملك صلاحية حذف الرسائل.', 403, 'forbidden');
        }
        $message = $this->findVisible((int) $params['id'], $companyId);

        InboxMessage::delete((int) $message['id']);
        ActivityLog::log('inbox.delete', 'inbox_message', (string) $message['id'], 'حذف رسالة من تطبيق الجوال - المصدر: ' . ($message['site_name'] ?? ''));

        Api::ok(['message' => 'تم حذف الرسالة.']);
    }

    // ---------- مساعدات ----------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            Api::error('حسابك غير مرتبط بشركة.', 422, 'no_company');
        }
        return $companyId;
    }

    private function canMarkRead(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('inbox.mark_read');
    }

    private function canDelete(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || Permission::check('inbox.delete');
    }

    private function findVisible(int $id, int $companyId): array
    {
        $message = InboxMessage::find($id);
        if (!$message || (int) $message['company_id'] !== $companyId) {
            Api::error('الرسالة غير موجودة.', 404, 'not_found');
        }
        return $message;
    }

    private function summaryPayload(array $m): array
    {
        return [
            'id' => (int) $m['id'],
            'site_name' => $m['site_name'] ?? null,
            'sender_name' => $m['sender_name'] ?? null,
            'subject' => $m['subject'] ?? null,
            'preview' => mb_substr(trim((string) ($m['body'] ?? '')), 0, 120),
            'is_read' => (bool) $m['is_read'],
            'received_at' => $m['received_at'],
        ];
    }

    private function detailPayload(array $m): array
    {
        // بيانات إضافية كأزواج نصية جاهزة للعرض (بدل JSON حر يصعب فكه في التطبيق).
        $extra = [];
        if (!empty($m['extra'])) {
            $decoded = json_decode((string) $m['extra'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    $extra[] = [
                        'label' => (string) $key,
                        'value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                    ];
                }
            } else {
                $extra[] = ['label' => 'بيانات إضافية', 'value' => (string) $m['extra']];
            }
        }

        return [
            'id' => (int) $m['id'],
            'site_name' => $m['site_name'] ?? null,
            'site_url' => $m['site_url'] ?? null,
            'sender_name' => $m['sender_name'] ?? null,
            'sender_email' => $m['sender_email'] ?? null,
            'sender_phone' => $m['sender_phone'] ?? null,
            'subject' => $m['subject'] ?? null,
            'body' => $m['body'] ?? '',
            'extra' => $extra,
            'is_read' => (bool) $m['is_read'],
            'reader_name' => $m['reader_name'] ?? null,
            'read_at' => $m['read_at'] ?? null,
            'received_at' => $m['received_at'],
        ];
    }
}
