<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Clients\Controllers\ClientController;

/** يُحمَّل فقط عندما تكون إضافة العملاء مفعّلة. */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/clients', [ClientController::class, 'index'], [$auth]);
    $router->get('/clients/create', [ClientController::class, 'create'], [$auth]);
    $router->post('/clients', [ClientController::class, 'store'], [$auth]);
    $router->get('/clients/{id}', [ClientController::class, 'show'], [$auth]);
    $router->get('/clients/{id}/edit', [ClientController::class, 'edit'], [$auth]);
    $router->post('/clients/{id}', [ClientController::class, 'update'], [$auth]);
    $router->post('/clients/{id}/archive', [ClientController::class, 'archive'], [$auth]);
};
