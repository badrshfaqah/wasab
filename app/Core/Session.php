<?php

namespace App\Core;

class Session
{
    /**
     * جلسة طويلة العمر (٣٠ يوماً) مناسبة لتطبيق جوال (PWA):
     *
     * - lifetime كان 0 (كوكي جلسة) فيموت بمجرد إغلاق التطبيق - وأنظمة الجوال تقتل
     *   تطبيقات PWA باستمرار، فكان المستخدم يسجل خروجاً مع كل فتح تقريباً.
     * - مجلد جلسات خاص داخل storage/ بدل مجلد الاستضافة المشترك: على الاستضافات
     *   المشتركة يكنس جامع القمامة ملفات الجلسات بعد ~24 دقيقة خمول (gc_maxlifetime
     *   الافتراضي 1440 ثانية لأي سكربت يشارك نفس المجلد)، فيطرد المستخدم حتى أثناء
     *   عمله. المجلد الخاص يجعل مهلتنا نحن هي النافذة.
     */
    private const LIFETIME_SECONDS = 2592000; // ٣٠ يوماً

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

            $savePath = BASE_PATH . '/storage/sessions';
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0700, true);
            }
            if (is_dir($savePath) && is_writable($savePath)) {
                session_save_path($savePath);
            }
            ini_set('session.gc_maxlifetime', (string) self::LIFETIME_SECONDS);

            session_set_cookie_params([
                'lifetime' => self::LIFETIME_SECONDS,
                'path' => '/',
                'samesite' => 'Lax',
                'httponly' => true,
                'secure' => $isHttps,
            ]);
            session_start();
        }
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, ?string $message = null)
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function old(string $key, $default = '')
    {
        return $_SESSION['_old'][$key] ?? $default;
    }

    public static function setOld(array $data): void
    {
        $_SESSION['_old'] = $data;
    }

    public static function clearOld(): void
    {
        unset($_SESSION['_old']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
