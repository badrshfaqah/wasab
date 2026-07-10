<?php

namespace App\Core;

class Middleware
{
    public static function auth(): bool
    {
        if (!Auth::check()) {
            redirect('/login');
            return false;
        }
        return true;
    }

    public static function guest(): bool
    {
        if (Auth::check()) {
            redirect('/');
            return false;
        }
        return true;
    }

    public static function systemAdmin(): bool
    {
        if (!Auth::isSystemAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            return false;
        }
        return true;
    }

    public static function can(string $permissionKey): callable
    {
        return function () use ($permissionKey) {
            if (!Permission::check($permissionKey)) {
                http_response_code(403);
                View::render('errors/403', [], '');
                return false;
            }
            return true;
        };
    }
}
