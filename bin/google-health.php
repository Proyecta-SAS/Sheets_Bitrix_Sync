<?php

declare(strict_types=1);

use App\Config\IntegrationConfig;
use App\Support\Env;
use App\Support\SensitiveData;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

try {
    /** @var App\Application $app */
    $app = require dirname(__DIR__) . '/bootstrap.php';
    $config = $app->config();
    $credentialPath = Env::get('GOOGLE_CREDENTIALS_PATH');
    $failed = false;

    $checks = [
        'GOOGLE_CREDENTIALS_PATH configurado' => $credentialPath !== '',
        'Credencial JSON existe' => $credentialPath !== '' && is_file($credentialPath),
        'Credencial JSON legible' => $credentialPath !== '' && is_readable($credentialPath),
        'GOOGLE_SPREADSHEET_ID configurado' => $config->spreadsheetId !== '',
        'GOOGLE_SHEET_NAME configurado' => $config->sheetName !== '',
    ];

    foreach ($checks as $label => $ok) {
        printf("[%s] %s\n", $ok ? 'OK' : 'FALTA', $label);
        $failed = $failed || !$ok;
    }

    if ($failed) {
        exit(1);
    }

    $spreadsheet = $app->sheets->testConnection($config->spreadsheetId);
    printf("[OK] Google Sheets API autenticada. Documento: %s\n", $spreadsheet['title'] !== '' ? $spreadsheet['title'] : '[sin titulo]');
    printf("[OK] Pestanas disponibles: %d\n", count($spreadsheet['sheets']));

    $headers = $app->sheets->getHeaders($config->spreadsheetId, $config->sheetName, $config->headerRow);
    printf("[OK] Encabezados leidos: %d\n", count($headers));

    $rows = $app->sheets->getRows($config->spreadsheetId, $config->sheetName, $config->headerRow, $config->headerRow + 1);
    printf("[%s] Fila de prueba #%d leida\n", $rows === [] ? 'INFO' : 'OK', $config->headerRow + 1);

    $app->sheets->ensureControlColumns(
        $config->spreadsheetId,
        $config->sheetName,
        $config->headerRow,
        IntegrationConfig::controlHeaders(),
    );
    echo "[OK] Escritura verificada: columnas de control presentes\n";
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . SensitiveData::clean($exception->getMessage(), [Env::get('GOOGLE_CREDENTIALS_PATH')]) . PHP_EOL);
    exit(1);
}
