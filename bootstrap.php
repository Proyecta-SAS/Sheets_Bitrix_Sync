<?php

declare(strict_types=1);

use App\Application;
use App\Support\Env;

define('APP_ROOT', __DIR__);

$autoload = APP_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Falta vendor/autoload.php. Ejecute: composer install --no-dev --optimize-autoloader');
}

require $autoload;

Env::load(APP_ROOT . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/Bogota'));

return Application::boot(APP_ROOT);
