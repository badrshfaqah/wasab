<?php

declare(strict_types=1);

require BASE_PATH . '/app/Core/Autoload.php';
require BASE_PATH . '/app/Core/helpers.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;

if (!is_file(BASE_PATH . '/config.php')) {
    $installUrl = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/install/';
    header('Location: ' . $installUrl);
    exit;
}

define('APP_CONFIG', require BASE_PATH . '/config.php');
define('APP_ASSET_VERSION', '1');

date_default_timezone_set(config_get('timezone', 'Asia/Riyadh'));

Session::start();
Database::connect(APP_CONFIG['db']);
View::addPath(BASE_PATH . '/app/Views');
