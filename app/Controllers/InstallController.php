<?php

namespace App\Controllers;

use App\Core\View;

/**
 * صفحة إرشادية لتثبيت النظام كتطبيق على الجوال (PWA) — خطوات مصوّرة لكل من
 * آيفون (Safari) وأندرويد (Chrome)، مع التقاط نوع الجهاز تلقائياً لإبراز القسم
 * المناسب. تُعرَض ضمن تخطيط النظام لأنها موجّهة لمستخدم مسجّل دخول على جواله.
 */
class InstallController
{
    public function index(): void
    {
        View::render('install.index', [
            'pageTitle' => 'تثبيت التطبيق على الجوال',
            'appName' => app_name(),
            'appIcon' => app_icon_url(192),
        ]);
    }
}
