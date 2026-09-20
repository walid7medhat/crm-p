<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// PHP 8.4 vendor deprecations can leak into responses; keep real errors visible.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// public/.user.ini sets max_execution_time=300, but .user.ini is only honored by
// PHP-FPM/CGI — the "php artisan serve" built-in dev server (cli-server SAPI) ignores it
// entirely and falls back to a 60s default, which real (slower, single-threaded-contended)
// local requests can exceed. Set it explicitly here so it applies under every SAPI.
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
