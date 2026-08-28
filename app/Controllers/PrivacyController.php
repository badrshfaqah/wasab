<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Wasab;

/**
 * سياسة خصوصية تطبيق الجوال - عامة بلا تسجيل دخول عمداً، تماماً كصفحة
 * /wasab (انظر تعليق المسار في routes.php). متجر آبل يشترط رابطاً عاماً
 * يفتحه المراجع وأي مستخدم قبل التحميل، فلا يصح أن يحرسها Middleware::auth.
 * ولنفس السبب لا تستخدم layouts.app المبني على افتراض وجود مستخدم مسجّل.
 */
class PrivacyController
{
    public function index(): void
    {
        View::render('privacy.index', [
            'pageTitle' => 'سياسة الخصوصية - تطبيق وصاب',
            'currentVersion' => Wasab::currentVersion(),
        ], '');
    }
}
