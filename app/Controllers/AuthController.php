<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;

class AuthController
{
    public function showLogin(): void
    {
        View::render('auth.login', ['pageTitle' => 'تسجيل الدخول'], '');
    }

    public function login(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/login');
        }

        $email = trim((string) Request::input('email', ''));
        $password = (string) Request::input('password', '');

        if ($email === '' || $password === '') {
            flash_set('error', 'يرجى إدخال البريد الإلكتروني وكلمة المرور.');
            Session::setOld(['email' => $email]);
            redirect('/login');
        }

        if (Auth::attempt($email, $password)) {
            ActivityLog::log('auth.login', 'user', Auth::id(), 'تسجيل دخول');
            redirect('/');
        }

        flash_set('error', 'البريد الإلكتروني أو كلمة المرور غير صحيحة.');
        Session::setOld(['email' => $email]);
        redirect('/login');
    }

    public function logout(): void
    {
        if (Auth::check()) {
            ActivityLog::log('auth.logout', 'user', Auth::id(), 'تسجيل خروج');
        }
        Auth::logout();
        redirect('/login');
    }
}
