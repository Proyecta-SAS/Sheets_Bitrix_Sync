<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$checks = [
    'PHP >= 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'ext-curl' => extension_loaded('curl'),
    'ext-pdo' => extension_loaded('pdo'),
    'pdo_sqlite' => in_array('sqlite', PDO::getAvailableDrivers(), true),
    'vendor/autoload.php' => is_file($root . '/vendor/autoload.php'),
    'storage escribible' => is_dir($root . '/storage') && is_writable($root . '/storage'),
    '.env' => is_file($root . '/.env'),
];

$failed = false;
foreach ($checks as $label => $ok) {
    printf("[%s] %s\n", $ok ? 'OK' : 'FALTA', $label);
    $failed = $failed || !$ok;
}

exit($failed ? 1 : 0);
