<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Phone\Controllers\PhoneAdminController;
use Modules\Phone\Controllers\PhoneContactController;
use Modules\Phone\Controllers\PhoneController;

/**
 * يُحمَّل فقط عندما تكون إضافة التلفون مفعّلة.
 * مسارات جهات الاتصال الثابتة (create) مسجّلة قبل مسار {id} حتى لا يلتقطها
 * الموجّه كمعرّف جهة اتصال.
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];
    $systemAdmin = [Middleware::class, 'systemAdmin'];

    $router->get('/phone/settings', [PhoneController::class, 'settings'], [$auth]);
    $router->post('/phone/settings', [PhoneController::class, 'updateSettings'], [$auth]);
    $router->post('/phone/toggle', [PhoneController::class, 'toggle'], [$auth]);

    $router->get('/phone/contacts', [PhoneContactController::class, 'index'], [$auth]);
    $router->get('/phone/contacts/create', [PhoneContactController::class, 'create'], [$auth]);
    $router->post('/phone/contacts', [PhoneContactController::class, 'store'], [$auth]);
    $router->get('/phone/contacts/{id}/edit', [PhoneContactController::class, 'edit'], [$auth]);
    $router->post('/phone/contacts/{id}', [PhoneContactController::class, 'update'], [$auth]);
    $router->post('/phone/contacts/{id}/delete', [PhoneContactController::class, 'destroy'], [$auth]);

    // إدارة مفتاح API لكل شركة - مدير النظام فقط
    $router->get('/phone/admin', [PhoneAdminController::class, 'index'], [$auth, $systemAdmin]);
    $router->post('/phone/admin/{companyId}', [PhoneAdminController::class, 'update'], [$auth, $systemAdmin]);
};
