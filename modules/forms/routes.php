<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Forms\Controllers\FormController;
use Modules\Forms\Controllers\FormSettingController;
use Modules\Forms\Controllers\FormTemplateController;

/**
 * يُحمَّل فقط عند تفعيل إضافة النماذج. المسارات الثابتة (generate/templates/
 * settings) قبل /forms/{id} حتى لا يلتقطها الموجّه كمعرّف خطاب.
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/forms', [FormController::class, 'index'], [$auth]);
    $router->get('/forms/generate', [FormController::class, 'generate'], [$auth]);
    $router->post('/forms', [FormController::class, 'store'], [$auth]);

    // القوالب
    $router->get('/forms/templates', [FormTemplateController::class, 'index'], [$auth]);
    $router->get('/forms/templates/create', [FormTemplateController::class, 'create'], [$auth]);
    $router->post('/forms/templates', [FormTemplateController::class, 'store'], [$auth]);
    $router->get('/forms/templates/{id}/edit', [FormTemplateController::class, 'edit'], [$auth]);
    $router->post('/forms/templates/{id}', [FormTemplateController::class, 'update'], [$auth]);
    $router->post('/forms/templates/{id}/delete', [FormTemplateController::class, 'destroy'], [$auth]);

    // الإعدادات
    $router->get('/forms/settings', [FormSettingController::class, 'index'], [$auth]);
    $router->post('/forms/settings', [FormSettingController::class, 'update'], [$auth]);

    // صفحة تحقّق عامة بلا مصادقة (رمز عشوائي طويل غير قابل للتخمين) - قبل /forms/{id}
    // طلبات الخطابات (الخدمة الذاتية) - مسارات ثابتة قبل /{id}
    $router->get('/forms/requests', [FormController::class, 'requests'], [$auth]);
    $router->get('/forms/requests/new', [FormController::class, 'requestForm'], [$auth]);
    $router->post('/forms/requests', [FormController::class, 'storeRequest'], [$auth]);
    $router->post('/forms/requests/{id}/approve', [FormController::class, 'approveRequest'], [$auth]);
    $router->post('/forms/requests/{id}/reject', [FormController::class, 'rejectRequest'], [$auth]);
    $router->post('/forms/{id}/email', [FormController::class, 'emailLetter'], [$auth]);

    $router->get('/forms/verify/{token}', [FormController::class, 'verify'], []);
    $router->get('/forms/verify/{token}/view', [FormController::class, 'verifyView'], []);

    // الخطابات المولّدة
    $router->get('/forms/{id}', [FormController::class, 'show'], [$auth]);
    $router->get('/forms/{id}/print', [FormController::class, 'print'], [$auth]);
    $router->post('/forms/{id}/delete', [FormController::class, 'destroy'], [$auth]);
};
