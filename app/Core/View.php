<?php

namespace App\Core;

class View
{
    private static array $sections = [];
    private static array $viewPaths = [];

    public static function addPath(string $path): void
    {
        self::$viewPaths[] = rtrim($path, '/');
    }

    private static function resolve(string $view): string
    {
        // صيغة خاصة بالإضافات: "tasks::index" => modules/tasks/Views/index.php
        if (str_contains($view, '::')) {
            [$moduleKey, $viewName] = explode('::', $view, 2);
            $file = BASE_PATH . '/modules/' . $moduleKey . '/Views/' . str_replace('.', '/', $viewName) . '.php';
            if (is_file($file)) {
                return $file;
            }
            throw new \RuntimeException("الملف غير موجود: {$view}");
        }

        $relative = str_replace('.', '/', $view) . '.php';
        foreach (array_reverse(self::$viewPaths) as $base) {
            $file = $base . '/' . $relative;
            if (is_file($file)) {
                return $file;
            }
        }
        throw new \RuntimeException("الملف غير موجود: {$view}");
    }

    public static function render(string $view, array $data = [], string $layout = 'layouts.app'): void
    {
        $content = self::renderPartial($view, $data);

        if ($layout === '') {
            echo $content;
            return;
        }

        $data['content'] = $content;
        echo self::renderPartial($layout, $data);
    }

    /**
     * تُشغَّل داخل دالة مغلقة معزولة لا تحمل أي متغيرات محلية أخرى عدا __path/__data
     * (تُحذف قبل include) - بدونها كان أي مفتاح بيانات باسم "file" أو "view" أو "data"
     * يصطدم صامتاً مع متغيرات هذه الدالة نفسها بسبب EXTR_SKIP فيفقد المُستدعي قيمته
     * الحقيقية دون أي خطأ ظاهر (نفس الحل التاريخي المعروف من محرّكات عرض PHP المشابهة).
     */
    public static function renderPartial(string $view, array $data = []): string
    {
        return (function (string $__path, array $__data) {
            extract($__data, EXTR_SKIP);
            unset($__data);
            ob_start();
            include $__path;
            return ob_get_clean();
        })(self::resolve($view), $data);
    }
}
