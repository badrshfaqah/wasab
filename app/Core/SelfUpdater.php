<?php

namespace App\Core;

/**
 * التحديث الذاتي من GitHub: يسحب أرشيف الفرع الرئيسي للمستودع ويطبّقه على
 * التثبيت الحالي بضغطة زر من صفحة الإضافات (مدير النظام فقط)، دون Terminal
 * ولا FTP - مناسب للاستضافات المشتركة.
 *
 * قواعد التطبيق:
 * - config.php لا يُلمس أبداً (بيانات اتصال هذا التثبيت).
 * - ملفات storage/ لا تُستبدل أبداً (رفوعات، جلسات، سجلات، قفل التثبيت) -
 *   تُنسخ فقط الملفات الجديدة غير الموجودة (مثل ملفات .htaccess المستحدثة).
 * - .git و .claude تُتجاهل، ولا يُحذف أي ملف (التحديثات إضافية/استبدالية فقط).
 * - بعد التحديث تعمل ترقيات النواة تلقائياً مع أول طلب، وتحديثات الإضافات
 *   تظهر بشارة "تحديث" بصفحة الإضافات كالمعتاد.
 */
class SelfUpdater
{
    private const ZIP_URL = 'https://codeload.github.com/badrshfaqah/wasab/zip/refs/heads/main';
    private const DOWNLOAD_TIMEOUT_SECONDS = 120;

    /** @return array{success: bool, message: string} */
    public static function run(): array
    {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'امتداد curl غير متاح على الاستضافة.'];
        }
        if (!class_exists(\ZipArchive::class)) {
            return ['success' => false, 'message' => 'امتداد zip غير متاح على الاستضافة.'];
        }

        $fromVersion = Wasab::currentVersion();
        $tmpBase = BASE_PATH . '/storage/tmp/selfupdate';
        self::removeDir($tmpBase);
        if (!@mkdir($tmpBase, 0755, true) && !is_dir($tmpBase)) {
            return ['success' => false, 'message' => 'تعذر إنشاء مجلد مؤقت داخل storage/.'];
        }

        try {
            // 1) تنزيل أرشيف الفرع من GitHub
            $zipPath = $tmpBase . '/update.zip';
            $error = self::download(self::ZIP_URL, $zipPath);
            if ($error !== null) {
                return ['success' => false, 'message' => 'فشل التنزيل من GitHub: ' . $error];
            }

            // 2) فك الأرشيف
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return ['success' => false, 'message' => 'الأرشيف المُنزَّل تالف.'];
            }
            $extractDir = $tmpBase . '/extract';
            @mkdir($extractDir, 0755, true);
            if (!$zip->extractTo($extractDir)) {
                $zip->close();
                return ['success' => false, 'message' => 'تعذر فك الأرشيف (مساحة أو صلاحيات).'];
            }
            $zip->close();

            // 3) تحديد جذر النسخة داخل الأرشيف والتحقق من سلامتها
            $roots = glob($extractDir . '/*', GLOB_ONLYDIR) ?: [];
            $sourceRoot = $roots[0] ?? null;
            if (!$sourceRoot
                || !is_file($sourceRoot . '/index.php')
                || !is_file($sourceRoot . '/app/wasab/changelog.php')
                || !is_file($sourceRoot . '/app/Core/Wasab.php')) {
                return ['success' => false, 'message' => 'محتوى الأرشيف لا يشبه نسخة وصاب صالحة - أُلغي التحديث.'];
            }

            preg_match("/'version'\s*=>\s*'([^']+)'/", (string) file_get_contents($sourceRoot . '/app/wasab/changelog.php'), $m);
            $toVersion = $m[1] ?? '؟';

            // 4) التطبيق
            $updated = self::copyTree($sourceRoot, BASE_PATH);

            ActivityLog::log('system.self_update', 'system', null, "تحديث النظام من GitHub: {$fromVersion} ← {$toVersion} ({$updated} ملفاً)");

            if ($toVersion === $fromVersion) {
                return ['success' => true, 'message' => "أنت على آخر إصدار أصلاً ({$fromVersion}) - أُعيد تطبيق الملفات ({$updated} ملفاً)."];
            }
            return ['success' => true, 'message' => "تم التحديث بنجاح من {$fromVersion} إلى {$toVersion} ({$updated} ملفاً). ترقيات قاعدة البيانات ستعمل تلقائياً، وراجع شارات \"تحديث\" على الإضافات أدناه إن ظهرت."];
        } catch (\Throwable $e) {
            log_exception($e);
            return ['success' => false, 'message' => 'خطأ غير متوقع أثناء التحديث: ' . $e->getMessage()];
        } finally {
            self::removeDir($tmpBase);
        }
    }

    private static function download(string $url, string $destination): ?string
    {
        $fh = fopen($destination, 'wb');
        if ($fh === false) {
            return 'تعذر إنشاء الملف المؤقت.';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => self::DOWNLOAD_TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => 'Wasab-SelfUpdater',
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        fclose($fh);

        if ($ok === false) {
            return $curlError ?: 'انقطاع الاتصال.';
        }
        if ($status !== 200) {
            return "رمز استجابة {$status} من GitHub.";
        }
        if ((int) filesize($destination) < 10000) {
            return 'حجم الأرشيف غير منطقي.';
        }
        return null;
    }

    /** نسخ شجرة النسخة الجديدة فوق التثبيت وفق قواعد الحفاظ - يعيد عدد الملفات المنسوخة. */
    private static function copyTree(string $sourceRoot, string $targetRoot): int
    {
        $updated = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = ltrim(substr((string) $item, strlen($sourceRoot)), '/');

            if ($relative === 'config.php'
                || str_starts_with($relative, '.git')
                || str_starts_with($relative, '.claude')) {
                continue;
            }

            $target = $targetRoot . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
                continue;
            }

            // ملفات storage/ لا تُستبدل أبداً - تُضاف الجديدة فقط
            if (str_starts_with($relative, 'storage/') && file_exists($target)) {
                continue;
            }

            if (!is_dir(dirname($target))) {
                @mkdir(dirname($target), 0755, true);
            }
            if (@copy((string) $item, $target)) {
                $updated++;
            }
        }

        return $updated;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir((string) $item) : @unlink((string) $item);
        }
        @rmdir($dir);
    }
}
