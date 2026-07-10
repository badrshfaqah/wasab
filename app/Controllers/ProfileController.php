<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Validator;
use App\Core\View;

class ProfileController
{
    public function show(): void
    {
        View::render('profile.index', ['pageTitle' => 'الملف الشخصي']);
    }

    public function update(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/profile');
        }

        $user = Auth::user();
        $name = trim((string) Request::input('name', ''));
        $password = (string) Request::input('password', '');

        $v = Validator::make(['name' => $name], ['name' => 'required|max:150']);
        if ($v->fails()) {
            flash_set('error', $v->firstError());
            redirect('/profile');
        }

        $update = ['name' => $name, 'updated_at' => date('Y-m-d H:i:s')];
        if ($password !== '') {
            if (strlen($password) < 8) {
                flash_set('error', 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.');
                redirect('/profile');
            }
            $update['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', $update, 'id = :id', ['id' => $user['id']]);
        ActivityLog::log('profile.update', 'user', $user['id'], 'تحديث الملف الشخصي');
        flash_set('success', 'تم تحديث ملفك الشخصي.');
        redirect('/profile');
    }
}
