<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Assets\Controllers\AssetCategoryController;
use Modules\Assets\Controllers\AssetController;
use Modules\Assets\Controllers\AssetHandoverController;

/**
 * يُحمَّل فقط عند تفعيل إضافة العهد والأصول. المسارات الثابتة (my/handovers/
 * categories) مسجّلة قبل /assets/{id} حتى لا يلتقطها الموجّه كمعرّف أصل.
 */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/custody', [AssetController::class, 'index'], [$auth]);
    $router->get('/custody/export/{format}', [AssetController::class, 'export'], [$auth]);
    $router->get('/custody/my', [AssetController::class, 'mine'], [$auth]);

    // الاستيراد الجماعي (ميزة 10)
    $router->get('/custody/import', [AssetController::class, 'importForm'], [$auth]);
    $router->post('/custody/import', [AssetController::class, 'import'], [$auth]);

    // كشوف العهد (ميزة 8)
    $router->get('/custody/statements', [AssetController::class, 'statements'], [$auth]);
    $router->get('/custody/statements/{type}/{ref}/print', [AssetController::class, 'statementPrint'], [$auth]);

    // التصنيفات
    $router->get('/custody/categories', [AssetCategoryController::class, 'index'], [$auth]);
    $router->post('/custody/categories', [AssetCategoryController::class, 'store'], [$auth]);
    $router->post('/custody/categories/{id}/delete', [AssetCategoryController::class, 'destroy'], [$auth]);
    $router->post('/custody/categories/{id}/fields', [AssetCategoryController::class, 'updateFields'], [$auth]);

    // محاضر التسليم
    $router->get('/custody/handovers', [AssetHandoverController::class, 'index'], [$auth]);
    $router->get('/custody/handovers/create', [AssetHandoverController::class, 'create'], [$auth]);
    $router->post('/custody/handovers', [AssetHandoverController::class, 'store'], [$auth]);
    $router->get('/custody/handovers/{id}', [AssetHandoverController::class, 'show'], [$auth]);
    $router->get('/custody/handovers/{id}/print', [AssetHandoverController::class, 'print'], [$auth]);
    $router->post('/custody/handovers/{id}/acknowledge', [AssetHandoverController::class, 'acknowledge'], [$auth]);
    $router->post('/custody/handovers/items/{itemId}/return', [AssetHandoverController::class, 'returnItem'], [$auth]);

    // الأصول
    $router->get('/custody/create', [AssetController::class, 'create'], [$auth]);
    $router->post('/custody', [AssetController::class, 'store'], [$auth]);
    $router->get('/custody/{id}/edit', [AssetController::class, 'edit'], [$auth]);
    $router->get('/custody/{id}/transfer', [AssetHandoverController::class, 'transferForm'], [$auth]);
    $router->post('/custody/{id}/transfer', [AssetHandoverController::class, 'transfer'], [$auth]);
    $router->post('/custody/{id}', [AssetController::class, 'update'], [$auth]);
    $router->post('/custody/{id}/delete', [AssetController::class, 'destroy'], [$auth]);
    $router->post('/custody/{id}/status', [AssetController::class, 'changeStatus'], [$auth]);
    $router->get('/custody/{id}', [AssetController::class, 'show'], [$auth]);
};
