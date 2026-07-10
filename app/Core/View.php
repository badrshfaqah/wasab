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

    public static function renderPartial(string $view, array $data = []): string
    {
        $file = self::resolve($view);
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
