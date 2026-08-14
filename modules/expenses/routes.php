<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Expenses\Controllers\ExpenseController;

/** يُحمَّل فقط عندما تكون إضافة المصروفات مفعّلة. */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/expenses', [ExpenseController::class, 'index'], [$auth]);
    $router->get('/expenses/new', [ExpenseController::class, 'create'], [$auth]);
    $router->post('/expenses', [ExpenseController::class, 'store'], [$auth]);
    $router->post('/expenses/{id}/approve', [ExpenseController::class, 'approve'], [$auth]);
    $router->post('/expenses/{id}/reject', [ExpenseController::class, 'reject'], [$auth]);
};
