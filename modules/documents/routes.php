<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Documents\Controllers\DocumentController;
use Modules\Documents\Controllers\DocumentSettingController;
use Modules\Documents\Controllers\DocumentTemplateController;

/**
 * يُحمَّل فقط عندما تكون إضافة المستندات مفعّلة. لا علاقة له بملفات النواة.
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/documents', [DocumentController::class, 'index'], [$auth]);
    $router->get('/documents/create', [DocumentController::class, 'create'], [$auth]);
    $router->post('/documents', [DocumentController::class, 'store'], [$auth]);

    // ثابتة قبل المسارات ذات {id} حتى لا يلتقطها الموجّه كمعرّف مستند
    $router->get('/documents/templates', [DocumentTemplateController::class, 'index'], [$auth]);
    $router->get('/documents/templates/create', [DocumentTemplateController::class, 'create'], [$auth]);
    $router->post('/documents/templates', [DocumentTemplateController::class, 'store'], [$auth]);
    $router->get('/documents/templates/{id}/edit', [DocumentTemplateController::class, 'edit'], [$auth]);
    $router->post('/documents/templates/{id}', [DocumentTemplateController::class, 'update'], [$auth]);
    $router->post('/documents/templates/{id}/delete', [DocumentTemplateController::class, 'destroy'], [$auth]);
    $router->post('/documents/templates/{id}/share', [DocumentTemplateController::class, 'share'], [$auth]);

    $router->get('/documents/settings', [DocumentSettingController::class, 'edit'], [$auth]);
    $router->post('/documents/settings', [DocumentSettingController::class, 'update'], [$auth]);

    // صفحة تحقّق عامة بلا مصادقة (رمز عشوائي طويل غير قابل للتخمين) - قبل /documents/{id}
    $router->get('/documents/verify/{token}', [DocumentController::class, 'verify'], []);
    $router->get('/documents/verify/{token}/view', [DocumentController::class, 'verifyView'], []);

    $router->get('/documents/{id}', [DocumentController::class, 'show'], [$auth]);
    $router->get('/documents/{id}/edit', [DocumentController::class, 'edit'], [$auth]);
    $router->post('/documents/{id}', [DocumentController::class, 'update'], [$auth]);
    $router->post('/documents/{id}/delete', [DocumentController::class, 'destroy'], [$auth]);
    $router->post('/documents/{id}/status', [DocumentController::class, 'updateStatus'], [$auth]);
    $router->post('/documents/{id}/comments', [DocumentController::class, 'comment'], [$auth]);
    $router->post('/documents/{id}/duplicate', [DocumentController::class, 'duplicate'], [$auth]);
    $router->post('/documents/{id}/share', [DocumentController::class, 'share'], [$auth]);
    $router->post('/documents/{id}/share/{userId}/unshare', [DocumentController::class, 'unshare'], [$auth]);
    $router->get('/documents/{id}/print', [DocumentController::class, 'print'], [$auth]);
    $router->get('/documents/{id}/versions/{versionId}', [DocumentController::class, 'viewVersion'], [$auth]);
    $router->get('/documents/{id}/versions/{versionId}/diff', [DocumentController::class, 'versionDiff'], [$auth]);
    $router->post('/documents/{id}/versions/{versionId}/restore', [DocumentController::class, 'restoreVersion'], [$auth]);
};
