<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Phone\Controllers\PhoneAdminController;
use Modules\Phone\Controllers\PhoneController;

/**
 * يُحمَّل فقط عندما تكون إضافة التلفون مفعّلة.
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];
    $systemAdmin = [Middleware::class, 'systemAdmin'];

    $router->get('/phone/settings', [PhoneController::class, 'settings'], [$auth]);
    $router->post('/phone/settings', [PhoneController::class, 'updateSettings'], [$auth]);
    $router->post('/phone/toggle', [PhoneController::class, 'toggle'], [$auth]);

    // إدارة مفتاح API لكل شركة - مدير النظام فقط
    $router->get('/phone/admin', [PhoneAdminController::class, 'index'], [$auth, $systemAdmin]);
    $router->post('/phone/admin/{companyId}', [PhoneAdminController::class, 'update'], [$auth, $systemAdmin]);
};
