<?php

namespace App\Core;

class Auth
{
    private static ?array $userCache = null;
    private static bool $loaded = false;

    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900; // 15 دقيقة

    /**
     * يعيد: "success" | "invalid" | "locked"
     */
    public static function attempt(string $email, string $password): string
    {
        $user = Database::first(
            'SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1',
            ['email' => $email]
        );

        if (!$user) {
            return 'invalid';
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return 'locked';
        }

        if (!password_verify($password, $user['password'])) {
            self::registerFailedAttempt($user);
            return 'invalid';
        }

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        self::$userCache = $user;
        self::$loaded = true;

        Database::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ], 'id = :id', ['id' => $user['id']]);

        return 'success';
    }

    private static function registerFailedAttempt(array $user): void
    {
        $attempts = (int) $user['failed_login_attempts'] + 1;
        $update = ['failed_login_attempts' => $attempts];
        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $update['locked_until'] = date('Y-m-d H:i:s', time() + self::LOCKOUT_SECONDS);
        }
        Database::update('users', $update, 'id = :id', ['id' => $user['id']]);
    }

    public static function logout(): void
    {
        self::$userCache = null;
        self::$loaded = false;
        Session::destroy();
    }

    /**
     * يسمح لمدير النظام الحالي بالدخول بالنيابة عن مستخدم آخر (غير مدير نظام).
     * يُسجَّل هوية مدير النظام الأصلي في الجلسة للعودة إليه لاحقاً.
     */
    public static function startImpersonation(int $targetUserId): bool
    {
        if (!self::isSystemAdmin() || self::isImpersonating()) {
            return false;
        }

        $target = Database::first('SELECT * FROM users WHERE id = :id AND status = "active" LIMIT 1', ['id' => $targetUserId]);
        if (!$target || $target['membership_type'] === 'system_admin') {
            return false;
        }

        $adminId = self::id();
        Session::regenerate();
        Session::set('impersonator_id', $adminId);
        Session::set('user_id', $target['id']);
        self::$userCache = $target;
        self::$loaded = true;

        return true;
    }

    public static function stopImpersonation(): bool
    {
        $adminId = Session::get('impersonator_id');
        if (!$adminId) {
            return false;
        }

        Session::remove('impersonator_id');
        Session::regenerate();
        Session::set('user_id', $adminId);
        self::$userCache = null;
        self::$loaded = false;

        return true;
    }

    public static function isImpersonating(): bool
    {
        return Session::has('impersonator_id');
    }

    public static function impersonatorId(): ?int
    {
        $id = Session::get('impersonator_id');
        return $id ? (int) $id : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$userCache;
        }
        self::$loaded = true;

        $id = Session::get('user_id');
        if (!$id) {
            self::$userCache = null;
            return null;
        }

        $user = Database::first('SELECT * FROM users WHERE id = :id AND status = "active" LIMIT 1', ['id' => $id]);
        self::$userCache = $user ?: null;
        return self::$userCache;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function isSystemAdmin(): bool
    {
        $u = self::user();
        return $u && $u['membership_type'] === 'system_admin';
    }

    public static function isCompanyAdmin(): bool
    {
        $u = self::user();
        return $u && $u['membership_type'] === 'company_admin';
    }

    public static function companyId(): ?int
    {
        $u = self::user();
        return $u && $u['company_id'] ? (int) $u['company_id'] : null;
    }
}
