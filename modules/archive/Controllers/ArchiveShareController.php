<?php

namespace Modules\Archive\Controllers;

use App\Core\View;
use Modules\Archive\Models\ArchiveFile;
use Modules\Archive\Models\ArchiveFileShare;

/**
 * نقطة وصول عامة بدون تسجيل دخول - هذا هو المقصود من "رابط مشاركة مؤقت"، فلا يوجد
 * middleware للمصادقة هنا عمداً. الأمان يعتمد فقط على توكن عشوائي طويل (48 بايت hex)
 * غير قابل للتخمين، مع التحقق من الانتهاء/الإلغاء/حد التحميلات قبل أي بث للملف.
 */
class ArchiveShareController
{
    private const STORAGE_DIR = '/storage/uploads/archive';

    public function download(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $share = $token !== '' ? ArchiveFileShare::findByToken($token) : null;

        if (!$share || !ArchiveFileShare::isUsable($share)) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }

        $file = ArchiveFile::find((int) $share['file_id']);
        if (!$file || $file['deleted_at'] !== null) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }

        $path = BASE_PATH . self::STORAGE_DIR . '/' . $file['company_id'] . '/' . $file['stored_name'];
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        ArchiveFileShare::incrementDownload($share['id']);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
