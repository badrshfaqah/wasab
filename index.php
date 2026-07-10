<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/bootstrap.php';

use App\Core\ModuleManager;
use App\Core\Request;
use App\Core\Router;

$router = new Router();

require BASE_PATH . '/app/routes.php';
ModuleManager::registerActiveRoutes($router);

$router->dispatch(Request::method(), Request::path());
