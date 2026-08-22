<?php

namespace Modules\Mobileapi\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ModuleManager;
use App\Core\Notification;
use Modules\Mobileapi\Support\Api;

/**
 * الصفحة الرئيسية للتطبيق: نفس عناصر لوحة الويب (نواة + إضافات مفعلة)
 * عبر ModuleManager::collectDashboardWidgets، بصيغة JSON موحّدة.
 */
class HomeApiController
{
    /** GET /api/v1/dashboard */
    public function index(): void
    {
        $user = Api::user();

        // بطاقات النواة (نفس DashboardController::index).
        $summary = [];
        if (Auth::isSystemAdmin()) {
            $summary[] = ['type' => 'stat', 'label' => 'الشركات', 'value' => (string) Database::count('companies'), 'icon' => '🏢', 'color' => 'primary', 'url' => '/companies'];
            $summary[] = ['type' => 'stat', 'label' => 'المستخدمون', 'value' => (string) Database::count('users'), 'icon' => '👥', 'color' => 'primary', 'url' => '/users'];
            $summary[] = ['type' => 'stat', 'label' => 'الإضافات المفعلة', 'value' => (string) Database::count('modules', 'status = "active"'), 'icon' => '🧩', 'color' => 'success', 'url' => null];
        } elseif (Auth::isCompanyAdmin() || Auth::companyId()) {
            $summary[] = ['type' => 'stat', 'label' => 'مستخدمو الشركة', 'value' => (string) Database::count('users', 'company_id = :c', ['c' => Auth::companyId()]), 'icon' => '👥', 'color' => 'primary', 'url' => '/users'];
        }

        $widgets = array_map(
            [$this, 'widgetPayload'],
            ModuleManager::collectDashboardWidgets($user)
        );

        Api::ok([
            'summary' => $summary,
            'widgets' => $widgets,
            'unread_notifications' => Notification::unreadCount((int) $user['id']),
        ]);
    }

    /** توحيد شكل عناصر اللوحة القادمة من الإضافات وتحويل الروابط لمسارات نسبية. */
    private function widgetPayload(array $w): array
    {
        $type = $w['type'] ?? 'stat';
        $out = [
            'type' => $type,
            'label' => $w['label'] ?? ($w['title'] ?? ''),
            'value' => isset($w['value']) ? (string) $w['value'] : null,
            'icon' => $w['icon'] ?? null,
            'color' => $w['color'] ?? null,
            'url' => $this->relativeUrl($w['url'] ?? null),
        ];
        if ($type === 'list') {
            $out['empty_text'] = $w['empty_text'] ?? null;
            $out['items'] = array_map(fn ($item) => [
                'label' => $item['label'] ?? '',
                'meta' => $item['meta'] ?? null,
                'url' => $this->relativeUrl($item['url'] ?? null),
            ], $w['items'] ?? []);
        }
        return $out;
    }

    /** الروابط تأتي مطلقة من route() - نعيد المسار فقط ليتعامل معه التطبيق. */
    private function relativeUrl(?string $url): ?string
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
