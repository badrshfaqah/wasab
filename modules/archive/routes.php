<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Archive\Controllers\ArchiveCategoryController;
use Modules\Archive\Controllers\ArchiveFileController;
use Modules\Archive\Controllers\ArchiveSettingController;
use Modules\Archive\Controllers\ArchiveShareController;

/**
 * يُحمَّل فقط عندما تكون إضافة أرشيف الملفات مفعّلة. لا علاقة له بملفات النواة.
 * المسارات الثابتة (upload/categories/settings/trash) مسجّلة قبل مسارات {id} حتى لا
 * يلتقطها الموجّه كمعرّف ملف (يطابق أول مسار مسجَّل يوافق نمطه).
 *
 * مسار /archive/share/{token} وحده بلا middleware مصادقة عمداً - هذا هو معنى رابط
 * المشاركة المؤقت (يُفتح بدون تسجيل دخول)، والأمان هنا يعتمد على التوكن العشوائي
 * الطويل مع التحقق من الانتهاء/الإلغاء/حد التحميلات داخل الكونترولر نفسه.
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/archive', [ArchiveFileController::class, 'index'], [$auth]);
    $router->get('/archive/upload', [ArchiveFileController::class, 'create'], [$auth]);
    $router->post('/archive/upload', [ArchiveFileController::class, 'store'], [$auth]);
    $router->get('/archive/trash', [ArchiveFileController::class, 'trash'], [$auth]);
    $router->get('/archive/share/{token}', [ArchiveShareController::class, 'download'], []);

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
    $router->post('/archive/{id}/restore', [ArchiveFileController::class, 'restore'], [$auth]);
    $router->post('/archive/{id}/permanent-delete', [ArchiveFileController::class, 'permanentDelete'], [$auth]);
    $router->get('/archive/{id}/download', [ArchiveFileController::class, 'download'], [$auth]);
    $router->get('/archive/{id}/preview', [ArchiveFileController::class, 'preview'], [$auth]);
    $router->post('/archive/{id}/share', [ArchiveFileController::class, 'createShare'], [$auth]);
    $router->post('/archive/{id}/share/{shareId}/revoke', [ArchiveFileController::class, 'revokeShare'], [$auth]);
};
