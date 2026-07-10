<?php

namespace App\Core;

class Auth
{
    private static ?array $userCache = null;
    private static bool $loaded = false;

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::first(
            'SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1',
            ['email' => $email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        self::$userCache = $user;
        self::$loaded = true;

        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);

        return true;
    }

    public static function logout(): void
    {
        self::$userCache = null;
        self::$loaded = false;
        Session::destroy();
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
