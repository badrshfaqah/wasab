<?php

namespace App\Controllers;

/**
 * خدمة صور الوحدات المعروضة داخل الواجهات (خلفيات المستندات، التوقيع والختم،
 * صور الموظفين) للمستخدمين المسجلين دخولاً فقط - storage/ كله محجوب عن الويب
 * عمداً، وهذه الصور حساسة فلا تُفتح للعموم كصور الهوية في uploads/core.
 *
 * الحماية: مصادقة إلزامية (middleware) + قائمة مجلدات مسموحة + تحقق صارم من اسم
 * الملف يمنع اجتياز المسارات، والأسماء أصلاً عشوائية غير قابلة للتخمين.
 */
class MediaController
{
    private const AREAS = ['documents', 'employees'];

    private const MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public function serve(array $params): void
    {
        $area = (string) ($params['area'] ?? '');
        $file = (string) ($params['file'] ?? '');

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($area, self::AREAS, true)
            || !preg_match('/^[A-Za-z0-9_.-]+$/', $file)
            || str_contains($file, '..')
            || !isset(self::MIME_TYPES[$extension])) {
            http_response_code(404);
            exit;
        }

        $path = BASE_PATH . '/storage/uploads/' . $area . '/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . self::MIME_TYPES[$extension]);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }
}
