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

function base_url(string $path = ''): string
{
    return Request::baseUrl() . '/' . ltrim($path, '/');
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

function format_date(?string $date, string $format = 'Y-m-d'): string
{
    if (!$date) {
        return '-';
    }
    try {
        return (new DateTime($date))->format($format);
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

function status_badge(string $status): string
{
    $map = [
        'active' => ['نشط', 'success'],
        'inactive' => ['غير نشط', 'muted'],
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
    ];
    [$label, $class] = $map[$status] ?? [$status, 'muted'];
    return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
}
