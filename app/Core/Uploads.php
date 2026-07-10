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

        $filename = 'company_' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED[$imageInfo['mime']];
        $dest = rtrim($destDir, '/') . '/' . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            return ['filename' => null, 'error' => 'تعذر حفظ الملف المرفوع على السيرفر.'];
        }

        return ['filename' => $filename, 'error' => null];
    }
}
