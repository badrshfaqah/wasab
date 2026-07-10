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

    $router->get('/documents/settings', [DocumentSettingController::class, 'edit'], [$auth]);
    $router->post('/documents/settings', [DocumentSettingController::class, 'update'], [$auth]);

    $router->get('/documents/{id}', [DocumentController::class, 'show'], [$auth]);
    $router->get('/documents/{id}/edit', [DocumentController::class, 'edit'], [$auth]);
    $router->post('/documents/{id}', [DocumentController::class, 'update'], [$auth]);
    $router->post('/documents/{id}/delete', [DocumentController::class, 'destroy'], [$auth]);
    $router->post('/documents/{id}/status', [DocumentController::class, 'updateStatus'], [$auth]);
    $router->get('/documents/{id}/print', [DocumentController::class, 'print'], [$auth]);
};
