<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$applicationPath = getenv('LARAVEL_BASE_PATH') ?: dirname(__DIR__);

if (! is_file($applicationPath.'/bootstrap/app.php')) {
    $applicationPath = dirname(__DIR__, 2).'/repositories/desego-web';
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $applicationPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $applicationPath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $applicationPath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
