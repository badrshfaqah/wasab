<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Archive\Controllers\ArchiveCategoryController;
use Modules\Archive\Controllers\ArchiveFileController;
use Modules\Archive\Controllers\ArchiveSettingController;

/**
 * يُحمَّل فقط عندما تكون إضافة أرشيف الملفات مفعّلة. لا علاقة له بملفات النواة.
 * المسارات الثابتة (upload/categories/settings) مسجّلة قبل مسارات {id} حتى لا
 * يلتقطها الموجّه كمعرّف ملف (يطابق أول مسار مسجَّل يوافق نمطه).
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/archive', [ArchiveFileController::class, 'index'], [$auth]);
    $router->get('/archive/upload', [ArchiveFileController::class, 'create'], [$auth]);
    $router->post('/archive/upload', [ArchiveFileController::class, 'store'], [$auth]);

    $router->get('/archive/categories', [ArchiveCategoryController::class, 'index'], [$auth]);
    $router->get('/archive/categories/create', [ArchiveCategoryController::class, 'create'], [$auth]);
    $router->post('/archive/categories', [ArchiveCategoryController::class, 'store'], [$auth]);
    $router->get('/archive/categories/{id}/edit', [ArchiveCategoryController::class, 'edit'], [$auth]);
    $router->post('/archive/categories/{id}', [ArchiveCategoryController::class, 'update'], [$auth]);
    $router->post('/archive/categories/{id}/delete', [ArchiveCategoryController::class, 'destroy'], [$auth]);

    $router->get('/archive/settings', [ArchiveSettingController::class, 'edit'], [$auth]);
    $router->post('/archive/settings', [ArchiveSettingController::class, 'update'], [$auth]);

    $router->get('/archive/{id}', [ArchiveFileController::class, 'show'], [$auth]);
    $router->get('/archive/{id}/edit', [ArchiveFileController::class, 'edit'], [$auth]);
    $router->post('/archive/{id}', [ArchiveFileController::class, 'update'], [$auth]);
    $router->post('/archive/{id}/move', [ArchiveFileController::class, 'move'], [$auth]);
    $router->post('/archive/{id}/replace', [ArchiveFileController::class, 'replace'], [$auth]);
    $router->post('/archive/{id}/status', [ArchiveFileController::class, 'statusAction'], [$auth]);
    $router->post('/archive/{id}/delete', [ArchiveFileController::class, 'destroy'], [$auth]);
    $router->get('/archive/{id}/download', [ArchiveFileController::class, 'download'], [$auth]);
    $router->get('/archive/{id}/preview', [ArchiveFileController::class, 'preview'], [$auth]);
};
