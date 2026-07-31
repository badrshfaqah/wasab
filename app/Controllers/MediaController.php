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
    private const COMPANY_AREAS = ['documents', 'employees', 'forms'];

    private const MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /**
     * صور الوحدات المفصولة لكل شركة: /media/{area}/{cid}/{file} - لا يخدمها إلا
     * لأعضاء الشركة نفسها (عزل كامل بين الشركات على مستوى الرابط أيضاً).
     */
    public function serve(array $params): void
    {
        $area = (string) ($params['area'] ?? '');
        $companyId = (int) ($params['cid'] ?? 0);
        $file = (string) ($params['file'] ?? '');

        if (!in_array($area, self::COMPANY_AREAS, true)
            || $companyId < 1
            || (int) \App\Core\Auth::companyId() !== $companyId) {
            http_response_code(404);
            exit;
        }

        $this->stream(BASE_PATH . '/storage/uploads/' . $area . '/' . $companyId, $file);
    }

    /** شعارات الشركات (تُعرض بقوائم مدير النظام أيضاً): أي مستخدم مسجل دخولاً. */
    public function serveCompanyLogo(array $params): void
    {
        $this->stream(BASE_PATH . '/storage/uploads/companies', (string) ($params['file'] ?? ''));
    }

    private function stream(string $dir, string $file): void
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $file)
            || str_contains($file, '..')
            || !isset(self::MIME_TYPES[$extension])) {
            http_response_code(404);
            exit;
        }

        $path = $dir . '/' . $file;
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
