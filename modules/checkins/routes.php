<?php

use App\Core\Middleware;
use App\Core\Router;
use Modules\Checkins\Controllers\CheckinController;

/** يُحمَّل فقط عندما تكون إضافة المتابعة اليومية مفعّلة. */
return function (Router $router): void {
    $auth = [Middleware::class, 'auth'];

    $router->get('/checkins', [CheckinController::class, 'index'], [$auth]);
    $router->post('/checkins', [CheckinController::class, 'store'], [$auth]);
    $router->get('/checkins/team', [CheckinController::class, 'team'], [$auth]);
    $router->get('/checkins/report', [CheckinController::class, 'report'], [$auth]);
    $router->post('/checkins/blocker-to-task', [CheckinController::class, 'blockerToTask'], [$auth]);
    $router->get('/checkins/settings', [CheckinController::class, 'settings'], [$auth]);
    $router->post('/checkins/settings', [CheckinController::class, 'saveSettings'], [$auth]);
};
