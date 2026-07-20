<?php

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function config_get(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = defined('APP_CONFIG') ? APP_CONFIG : [];
    }
    $segments = explode('.', $key);
    $value = $config;
    foreach ($segments as $seg) {
        if (!is_array($value) || !array_key_exists($seg, $value)) {
            return $default;
        }
        $value = $value[$seg];
    }
    return $value;
}

function app_name(): string
{
    try {
        return \App\Core\Setting::get('app_name', null, config_get('app_name', 'نظام الإدارة'));
    } catch (\Throwable $e) {
        return config_get('app_name', 'نظام الإدارة');
    }
}

/**
 * خارج سياق طلب ويب حي (مثل cron.php) لا يوجد Host نستنتج منه عنوان الموقع، فنستخدم
 * "عنوان الموقع" من الإعدادات إن عُرِّف؛ إن لم يُعرَّف نرجع لنفس الاستنتاج المعتاد
 * (سيُنتج رابطاً غير صحيح كـ "http://localhost/..." لكن دون أي كسر أو استثناء).
 */
function base_url(string $path = ''): string
{
    if (PHP_SAPI === 'cli') {
        $configured = rtrim((string) app_url(), '/');
        if ($configured !== '') {
            return $configured . '/' . ltrim($path, '/');
        }
    }
    return Request::baseUrl() . '/' . ltrim($path, '/');
}

function app_url(): string
{
    try {
        return \App\Core\Setting::get('app_url', null, config_get('app_url', ''));
    } catch (\Throwable $e) {
        return config_get('app_url', '');
    }
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/')) . '?v=' . APP_ASSET_VERSION;
}

function route(string $path = ''): string
{
    return base_url(ltrim($path, '/'));
}

/**
 * يسجّل تفاصيل الاستثناء في ملف السجلات بدلاً من عرضها للمستخدم مباشرة (منع كشف المعلومات).
 */
function log_exception(\Throwable $e): void
{
    $line = sprintf('[%s] %s in %s:%d', date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine());
    @file_put_contents(BASE_PATH . '/storage/logs/app.log', $line . PHP_EOL, FILE_APPEND);
}

function redirect(string $path): void
{
    header('Location: ' . route($path));
    exit;
}

function old(string $key, $default = '')
{
    return e((string) Session::old($key, $default));
}

function csrf_field(): string
{
    return Csrf::field();
}

function flash_get(string $key): ?string
{
    return Session::flash($key);
}

function flash_set(string $key, string $message): void
{
    Session::flash($key, $message);
}

function current_user(): ?array
{
    return \App\Core\Auth::user();
}

function can(string $permissionKey): bool
{
    return \App\Core\Permission::check($permissionKey);
}

function module_active(string $key): bool
{
    return \App\Core\ModuleManager::isActive($key);
}

/**
 * المنطقة الزمنية المفضلة للمستخدم الحالي (من ملفه الشخصي) إن اختلفت عن توقيت
 * النظام الافتراضي، وإلا null. تُستخدم للعرض فقط - التخزين دائماً بتوقيت النظام.
 */
function user_timezone(): ?string
{
    static $resolved = false, $tz = null;
    if (!$resolved) {
        $resolved = true;
        try {
            $candidate = current_user()['timezone'] ?? null;
            if ($candidate
                && $candidate !== date_default_timezone_get()
                && in_array($candidate, timezone_identifiers_list(), true)) {
                $tz = $candidate;
            }
        } catch (\Throwable $e) {
            $tz = null;
        }
    }
    return $tz;
}

function format_date(?string $date, string $format = 'Y-m-d'): string
{
    if (!$date) {
        return '-';
    }
    try {
        $dt = new DateTime($date);
        // تحويل العرض لمنطقة المستخدم الزمنية - فقط للقيم التي تحمل وقتاً فعلياً؛
        // التواريخ المجردة (استحقاق مهمة مثلاً) لا تُحوَّل حتى لا ينزاح اليوم نفسه.
        if (str_contains($date, ':') && ($tz = user_timezone())) {
            $dt->setTimezone(new DateTimeZone($tz));
        }
        return $dt->format($format);
    } catch (Exception $e) {
        return '-';
    }
}

function render_pagination(int $total, int $perPage, int $page, string $baseUrl): string
{
    $pages = (int) ceil($total / max(1, $perPage));
    if ($pages <= 1) {
        return '';
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $page) {
            $html .= '<span class="active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
        }
    }
    return $html . '</div>';
}

/** خريطة تسميات الحالات نفسها المستخدمة داخل status_badge()، لعرض نص الحالة فقط بلا HTML (مثلاً بنتائج البحث الموحّد). */
function status_label(string $status): string
{
    return status_badge_map()[$status][0] ?? $status;
}

function status_badge(string $status): string
{
    $map = status_badge_map();
    [$label, $class] = $map[$status] ?? [$status, 'muted'];
    return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
}

function status_badge_map(): array
{
    return [
        'active' => ['نشط', 'success'],
        'inactive' => ['غير نشط', 'muted'],
        'on_leave' => ['إجازة', 'warning'],
        'suspended' => ['موقوف', 'danger'],
        'terminated' => ['منتهي الخدمة', 'muted'],
        'pending' => ['قيد الانتظار', 'warning'],
        'todo' => ['لم تبدأ', 'muted'],
        'in_progress' => ['قيد التنفيذ', 'info'],
        'in_review' => ['قيد المراجعة', 'warning'],
        'done' => ['مكتملة', 'success'],
        'cancelled' => ['ملغاة', 'danger'],
        'low' => ['منخفضة', 'muted'],
        'medium' => ['متوسطة', 'info'],
        'high' => ['عالية', 'warning'],
        'urgent' => ['عاجلة', 'danger'],
        'draft' => ['مسودة', 'muted'],
        'pending_approval' => ['بانتظار الموافقة', 'warning'],
        'approved' => ['معتمد', 'info'],
        'signed' => ['موقّع', 'success'],
        'archived' => ['مؤرشف', 'muted'],
        'expired' => ['منتهي الصلاحية', 'danger'],
        'closed' => ['مغلق', 'muted'],
        'scheduled' => ['مجدول', 'info'],
        'completed' => ['منتهٍ', 'success'],
        'accepted' => ['مقبول', 'success'],
        'declined' => ['معتذر', 'danger'],
    ];
}
