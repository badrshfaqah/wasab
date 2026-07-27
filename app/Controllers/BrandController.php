<?php

namespace App\Controllers;

/**
 * ملف تعريف تطبيق الجوال (Web App Manifest) ديناميكياً: يعكس اسم النظام وشعاره
 * المخصصين من الإعدادات (علامة بيضاء) بدل ملف manifest.json الثابت. عام بلا
 * مصادقة - المتصفح يجلبه أثناء التثبيت للشاشة الرئيسية.
 */
class BrandController
{
    /**
     * خدمة صور هوية النظام العامة (شعار النظام + أيقونتا الجوال) عبر PHP مباشرة:
     * لا تعتمد على السماح بقراءة storage/ من الويب، فتعمل على أي استضافة مهما
     * كان تعاملها مع ملفات .htaccess. عامة عمداً - تظهر ببوابة الدخول قبل المصادقة.
     * المسموح حصراً: ملف الشعار المسجل بالإعدادات، والأيقونتان المولَّدتان.
     */
    public function asset(array $params): void
    {
        $file = (string) ($params['file'] ?? '');

        $allowed = ['app-icon-192.png', 'app-icon-512.png'];
        $appLogo = (string) \App\Core\Setting::get('app_logo', null, '');
        if ($appLogo !== '') {
            $allowed[] = $appLogo;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeTypes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

        if (!in_array($file, $allowed, true) || !isset($mimeTypes[$extension])) {
            http_response_code(404);
            exit;
        }

        $path = BASE_PATH . '/storage/uploads/core/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . $mimeTypes[$extension]);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }

    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');

        $icons = [
            ['src' => app_icon_url(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => app_icon_url(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => app_icon_url(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];

        echo json_encode([
            'name' => app_name(),
            'short_name' => mb_substr(app_name(), 0, 12),
            'description' => 'نظام إداري متكامل وخفيف لإدارة أعمال الشركات',
            'lang' => 'ar',
            'dir' => 'rtl',
            'id' => './',
            'start_url' => './',
            'scope' => './',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#f4f6f9',
            'theme_color' => '#2563eb',
            'icons' => $icons,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
