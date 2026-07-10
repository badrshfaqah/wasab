<?php
/**
 * أوتولودر خفيف بدون Composer.
 * App\Foo\Bar         => app/Foo/Bar.php
 * Modules\Tasks\Xyz   => modules/tasks/Xyz.php   (اسم المجلد بحروف صغيرة = مفتاح الإضافة)
 */

spl_autoload_register(function ($class) {
    $rootPath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    if (strpos($class, 'App\\') === 0) {
        $relative = substr($class, strlen('App\\'));
        $file = $rootPath . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }

    if (strpos($class, 'Modules\\') === 0) {
        $relative = substr($class, strlen('Modules\\'));
        $parts = explode('\\', $relative);
        $moduleKey = strtolower(array_shift($parts));
        $file = $rootPath . '/modules/' . $moduleKey . '/' . implode('/', $parts) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
});
