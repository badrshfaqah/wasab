<?php

namespace Modules\Inbox\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;
use Modules\Inbox\Models\InboxMessage;
use Modules\Inbox\Models\InboxSite;

class InboxController
{
    private const SCOPES = ['all', 'unread', 'read'];

    public function index(): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('inbox.view')) {
            $this->forbidden();
            return;
        }

        $scope = Request::query('scope', 'unread');
        if (!in_array($scope, self::SCOPES, true)) {
            $scope = 'unread';
        }

        $filters = [];
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        if ($siteId = (int) Request::query('site_id', 0)) {
            $filters['site_id'] = $siteId;
        }

        $page = max(1, (int) Request::query('page', 1));
        $result = InboxMessage::paginate($companyId, $scope, $page, 20, $filters);

        View::render('inbox::index', [
            'pageTitle' => 'مركز المراسلات',
            'messages' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'scope' => $scope,
            'filters' => $filters,
            'sites' => InboxSite::forCompany($companyId),
            'unreadCount' => InboxMessage::unreadCount($companyId),
            'canManageSites' => $this->canManageSites(),
        ]);
    }

    public function show(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('inbox.view')) {
            $this->forbidden();
            return;
        }
        $message = $this->findVisible((int) $params['id'], $companyId);

        // فتح الرسالة يؤشرها مقروءة تلقائياً لمن يملك صلاحية التأشير.
        if (!$message['is_read'] && $this->canMarkRead()) {
            InboxMessage::markRead((int) $message['id'], Auth::id());
            $message = InboxMessage::find((int) $message['id']);
        }

        View::render('inbox::show', [
            'pageTitle' => $message['subject'] ?: 'رسالة من ' . ($message['site_name'] ?? 'موقع محذوف'),
            'message' => $message,
            'canMarkRead' => $this->canMarkRead(),
            'canDelete' => $this->canDelete(),
        ]);
    }

    public function toggleRead(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canMarkRead()) {
            $this->forbidden();
            return;
        }
        $message = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/inbox/' . $message['id']);

        if ($message['is_read']) {
            InboxMessage::markUnread((int) $message['id']);
            flash_set('success', 'تم تأشير الرسالة كغير مقروءة.');
        } else {
            InboxMessage::markRead((int) $message['id'], Auth::id());
            flash_set('success', 'تم تأشير الرسالة كمقروءة.');
        }

        redirect('/inbox/' . $message['id']);
    }

    public function destroy(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->canDelete()) {
            $this->forbidden();
            return;
        }
        $message = $this->findVisible((int) $params['id'], $companyId);
        $this->verifyCsrf('/inbox/' . $message['id']);

        InboxMessage::delete((int) $message['id']);
        ActivityLog::log('inbox.delete', 'inbox_message', (int) $message['id'], 'حذف رسالة من مركز المراسلات - المصدر: ' . ($message['site_name'] ?? '-'));

        flash_set('success', 'تم حذف الرسالة.');
        redirect('/inbox');
    }

    // ---------------------------------------------------------------

    private function requireCompanyContext(): int
    {
        $companyId = Auth::companyId();
        if (!$companyId) {
            View::render('inbox::no-company', ['pageTitle' => 'مركز المراسلات']);
            exit;
        }
        return $companyId;
    }

    private function can(string $key): bool
    {
        return \App\Core\Permission::check($key);
    }

    private function canManageSites(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || $this->can('inbox.manage_sites');
    }

    private function canMarkRead(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || $this->can('inbox.mark_read');
    }

    private function canDelete(): bool
    {
        return Auth::isSystemAdmin() || Auth::isCompanyAdmin() || $this->can('inbox.delete');
    }

    /** تصدير قائمة الرسائل (بنفس نطاق وفلاتر الصفحة) إلى CSV أو طباعة/PDF. */
    public function export(array $params): void
    {
        $companyId = $this->requireCompanyContext();
        if (!$this->can('inbox.view')) {
            $this->forbidden();
            return;
        }
        $scope = Request::query('scope', 'all');
        if (!in_array($scope, self::SCOPES, true)) {
            $scope = 'all';
        }
        $filters = [];
        if ($q = trim((string) Request::query('q', ''))) {
            $filters['q'] = $q;
        }
        if ($siteId = (int) Request::query('site_id', 0)) {
            $filters['site_id'] = $siteId;
        }
        $messages = InboxMessage::paginate($companyId, $scope, 1, 100000, $filters)['rows'];

        $headers = ['الموقع', 'المرسل', 'البريد', 'الجوال', 'الموضوع', 'الرسالة', 'الحالة', 'وقت الاستلام'];
        $rows = array_map(fn ($m) => [
            $m['site_name'] ?? '-',
            $m['sender_name'] ?? '-',
            $m['sender_email'] ?? '-',
            $m['sender_phone'] ?? '-',
            $m['subject'] ?? '-',
            mb_substr((string) $m['body'], 0, 500),
            $m['is_read'] ? 'مقروءة' : 'غير مقروءة',
            format_date($m['received_at'], 'Y-m-d H:i'),
        ], $messages);

        if (($params['format'] ?? 'csv') === 'print') {
            \App\Core\Export::printable('رسائل مركز المراسلات', $headers, $rows);
        }
        \App\Core\Export::csv('inbox_messages', $headers, $rows);
    }

    private function findVisible(int $id, int $companyId): array
    {
        $message = InboxMessage::find($id);
        if (!$message || (int) $message['company_id'] !== $companyId) {
            flash_set('error', 'الرسالة غير موجودة.');
            redirect('/inbox');
        }
        return $message;
    }

    private function verifyCsrf(string $back): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect($back);
        }
    }

    private function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [], '');
    }
}
