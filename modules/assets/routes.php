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

    $router->get('/assets', [AssetController::class, 'index'], [$auth]);
    $router->get('/assets/my', [AssetController::class, 'mine'], [$auth]);

    // التصنيفات
    $router->get('/assets/categories', [AssetCategoryController::class, 'index'], [$auth]);
    $router->post('/assets/categories', [AssetCategoryController::class, 'store'], [$auth]);
    $router->post('/assets/categories/{id}/delete', [AssetCategoryController::class, 'destroy'], [$auth]);

    // محاضر التسليم
    $router->get('/assets/handovers', [AssetHandoverController::class, 'index'], [$auth]);
    $router->get('/assets/handovers/create', [AssetHandoverController::class, 'create'], [$auth]);
    $router->post('/assets/handovers', [AssetHandoverController::class, 'store'], [$auth]);
    $router->get('/assets/handovers/{id}', [AssetHandoverController::class, 'show'], [$auth]);
    $router->post('/assets/handovers/items/{itemId}/return', [AssetHandoverController::class, 'returnItem'], [$auth]);

    // الأصول
    $router->get('/assets/create', [AssetController::class, 'create'], [$auth]);
    $router->post('/assets', [AssetController::class, 'store'], [$auth]);
    $router->get('/assets/{id}/edit', [AssetController::class, 'edit'], [$auth]);
    $router->post('/assets/{id}', [AssetController::class, 'update'], [$auth]);
    $router->post('/assets/{id}/delete', [AssetController::class, 'destroy'], [$auth]);
    $router->post('/assets/{id}/status', [AssetController::class, 'changeStatus'], [$auth]);
    $router->get('/assets/{id}', [AssetController::class, 'show'], [$auth]);
};
