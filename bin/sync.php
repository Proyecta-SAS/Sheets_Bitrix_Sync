<?php

declare(strict_types=1);

use App\Support\SensitiveData;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

try {
    /** @var App\Application $app */
    $app = require dirname(__DIR__) . '/bootstrap.php';
    $row = null;
    $force = false;
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--force') {
            $force = true;
        } elseif (str_starts_with($argument, '--row=')) {
            $row = filter_var(substr($argument, 6), FILTER_VALIDATE_INT);
            if ($row === false || $row < 1) {
                throw new InvalidArgumentException('--row debe ser un entero positivo.');
            }
        }
    }

    $result = $app->sync->run($app->config(), $row, $force);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($result['errors'] > 0 ? 2 : 0);
} catch (Throwable $exception) {
    $message = SensitiveData::clean($exception->getMessage());
    fwrite(STDERR, '[Sheets Bitrix Sync] ' . $message . PHP_EOL);
    exit(1);
}
