<?php

namespace App\Core;

/**
 * رفع صور بسيط وموثوق - يعتمد على getimagesize() كمصدر وحيد لنوع الملف
 * (بدلاً من mime_content_type() الذي يحتاج امتداد fileinfo وقد يكون معطّلاً
 * على بعض الاستضافات المشتركة)، ويتحقق فعلياً من نجاح الكتابة على القرص
 * بدلاً من افتراض النجاح بصمت.
 */
class Uploads
{
    /**
     * يحوّل صورة مخزّنة إلى data URI لتضمينها بصفحات عامة (كصفحة التحقق) حيث لا
     * تعمل مسارات /media المحمية بتسجيل الدخول. يعيد null إن غاب الملف أو كبر (2MB).
     */
    public static function dataUri(?string $path): ?string
    {
        if (!$path || !is_file($path) || filesize($path) > 2 * 1024 * 1024) {
            return null;
        }
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
        if (!$mime) {
            return null;
        }
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    private const ALLOWED = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    private const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * يعيد ['filename' => string, 'error' => null] عند النجاح،
     * أو ['filename' => null, 'error' => string] عند الفشل،
     * أو ['filename' => null, 'error' => null] إن لم يُرفع أي ملف أصلاً.
     */
    public static function handleImage(string $fieldName, string $destDir): array
    {
        $file = Request::file($fieldName);
        if (!$file) {
            return ['filename' => null, 'error' => null];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['filename' => null, 'error' => 'تعذر رفع الملف (خطأ رقم ' . $file['error'] . ').'];
        }

        if ($file['size'] > self::MAX_BYTES) {
            return ['filename' => null, 'error' => 'الحد الأقصى لحجم الصورة هو 2 ميجابايت.'];
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false || !isset(self::ALLOWED[$imageInfo['mime']])) {
            return ['filename' => null, 'error' => 'صيغة الملف غير مدعومة. الصيغ المسموحة: PNG, JPG, WEBP.'];
        }

        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return ['filename' => null, 'error' => 'تعذر الوصول لمجلد الرفع على السيرفر.'];
        }
        if (!is_writable($destDir)) {
            return ['filename' => null, 'error' => 'مجلد الرفع على السيرفر غير قابل للكتابة (راجع صلاحيات ' . $destDir . ').'];
        }

        $filename = 'img_' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED[$imageInfo['mime']];
        $dest = rtrim($destDir, '/') . '/' . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            return ['filename' => null, 'error' => 'تعذر حفظ الملف المرفوع على السيرفر.'];
        }

        return ['filename' => $filename, 'error' => null];
    }

    /**
     * توقيعات الأبواب الأولى (magic bytes) لأنواع الملفات المدعومة - بديل عن fileinfo/
     * mime_content_type() (قد يكون معطّلاً على استضافات مشتركة، وهو أصلاً غير موثوق
     * لأنه يعتمد على نفس القاعدة). التحقق من المحتوى الفعلي، وليس فقط الامتداد،
     * يمنع رفع ملف قابل للتنفيذ منتحلاً امتداداً "آمناً" مثل .pdf أو .docx.
     *
     * ملاحظة: صيغ Office الحديثة (docx/xlsx/pptx) هي أرشيف ZIP داخلياً فتشترك بنفس
     * توقيع zip - يكفي هذا كخط دفاع أول مع فحص الامتداد، دون الحاجة لتحليل بنية
     * الأرشيف الداخلية بالكامل.
     */
    private const SIGNATURES = [
        'pdf' => ["%PDF"],
        'zip' => ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"],
        'docx' => ["PK\x03\x04"],
        'xlsx' => ["PK\x03\x04"],
        'pptx' => ["PK\x03\x04"],
        'doc' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"],
        'xls' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"],
        'ppt' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"],
    ];

    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * رفع عام متعدد الصيغ (PDF، Word، Excel، PowerPoint، صور، ZIP). يتحقق من الامتداد
     * وتوقيع الملف الفعلي معاً، ويعيد بيانات الملف الأصلية للاستخدام في قاعدة البيانات.
     *
     * $allowedExtensions بصيغة lowercase بدون نقطة، مثل ['pdf','doc','docx'].
     *
     * يعيد ['filename'=>?, 'original'=>?, 'extension'=>?, 'size'=>?, 'error'=>?].
     */
    public static function handleFile(string $fieldName, string $destDir, array $allowedExtensions, int $maxBytes): array
    {
        $file = Request::file($fieldName);
        if (!$file) {
            return ['filename' => null, 'original' => null, 'extension' => null, 'size' => null, 'error' => null];
        }

        $empty = ['filename' => null, 'original' => null, 'extension' => null, 'size' => null];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $empty + ['error' => 'تعذر رفع الملف (خطأ رقم ' . $file['error'] . ').'];
        }
        if ($file['size'] > $maxBytes) {
            return $empty + ['error' => 'حجم الملف أكبر من الحد المسموح (' . self::humanSize($maxBytes) . ').'];
        }

        $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return $empty + ['error' => 'صيغة الملف غير مدعومة (' . $extension . ').'];
        }

        if (!self::verifySignature($file['tmp_name'], $extension)) {
            return $empty + ['error' => 'محتوى الملف لا يطابق صيغته المعلنة. تأكد من رفع ملف سليم.'];
        }

        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return $empty + ['error' => 'تعذر الوصول لمجلد الرفع على السيرفر.'];
        }
        if (!is_writable($destDir)) {
            return $empty + ['error' => 'مجلد الرفع على السيرفر غير قابل للكتابة (راجع صلاحيات ' . $destDir . ').'];
        }

        $filename = 'file_' . bin2hex(random_bytes(12)) . '.' . $extension;
        $dest = rtrim($destDir, '/') . '/' . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            return $empty + ['error' => 'تعذر حفظ الملف المرفوع على السيرفر.'];
        }

        return [
            'filename' => $filename,
            'original' => $file['name'],
            'extension' => $extension,
            'size' => (int) $file['size'],
            'error' => null,
        ];
    }

    private static function verifySignature(string $tmpPath, string $extension): bool
    {
        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return @getimagesize($tmpPath) !== false;
        }

        $signatures = self::SIGNATURES[$extension] ?? null;
        if ($signatures === null) {
            return true;
        }

        $handle = @fopen($tmpPath, 'rb');
        if (!$handle) {
            return false;
        }
        $head = fread($handle, 8);
        fclose($handle);

        foreach ($signatures as $sig) {
            if (substr($head, 0, strlen($sig)) === $sig) {
                return true;
            }
        }
        return false;
    }

    private static function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' ميجابايت';
        }
        return round($bytes / 1024) . ' كيلوبايت';
    }
}
