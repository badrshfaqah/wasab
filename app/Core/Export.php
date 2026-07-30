<?php

namespace App\Core;

/**
 * مُصدِّر موحّد للقوائم بصيغتين، بلا أي مكتبات خارجية (يعمل على أي استضافة):
 *  - CSV: يُفتح مباشرة في Excel، مع BOM لضمان ظهور العربية صحيحة.
 *  - طباعة/PDF: صفحة جدول أنيقة بنفس آلية طباعة المستندات (window.print).
 *
 * كل إضافة تجمع صفوفها (بنفس فلاتر وصلاحيات وعزل شركة صفحة القائمة) ثم تستدعي
 * csv() أو printable() - فلا يُكرّر منطق التصدير في كل إضافة.
 */
class Export
{
    /**
     * بثّ ملف CSV للتنزيل.
     * @param string $filename اسم الملف بلا امتداد
     * @param array $headers عناوين الأعمدة (الصف الأول)
     * @param array $rows مصفوفة صفوف، كل صف مصفوفة قيم بنفس ترتيب العناوين
     */
    public static function csv(string $filename, array $headers, array $rows): void
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]/u', '_', $filename) ?: 'export';
        $stamp = date('Ymd_Hi');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safe . '_' . $stamp . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        // BOM يجعل Excel يتعرّف على UTF-8 فتظهر العربية سليمة
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($v) => self::cell($v), $row));
        }
        fclose($out);
        exit;
    }

    /**
     * عرض صفحة طباعة/PDF لجدول. تفتح جاهزة للطباعة عبر متصفح المستخدم (حفظ PDF).
     */
    public static function printable(string $title, array $headers, array $rows, string $subtitle = ''): void
    {
        View::render('exports.table', [
            'pageTitle' => $title,
            'title' => $title,
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
        ], '');
        exit;
    }

    private static function cell($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }
        return (string) $value;
    }
}
