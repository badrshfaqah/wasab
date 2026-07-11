<?php

namespace App\Controllers;

use App\Core\ModuleManager;
use App\Core\View;
use App\Core\Wasab;

/**
 * صفحة "وصاب" عامة بلا تسجيل دخول عمداً (انظر تعليق المسار في routes.php) - مخصصة
 * للنشر كصفحة تعريفية عن المنتج بموقع الشركة، فلا تستخدم تخطيط النظام الداخلي
 * (layouts.app) المبني على افتراض وجود مستخدم مسجّل دخول، بل صفحة مستقلة بالكامل.
 */
class WasabController
{
    public function index(): void
    {
        View::render('wasab.index', [
            'pageTitle' => 'وصاب - نظام إدارة الأعمال',
            'changelog' => Wasab::changelog(),
            'modules' => ModuleManager::discover(),
        ], '');
    }
}
