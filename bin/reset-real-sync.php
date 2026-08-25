<?php

declare(strict_types=1);

use App\Config\IntegrationConfig;
use App\Support\Env;
use App\Support\SensitiveData;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);

try {
    /** @var App\Application $app */
    $app = require $root . '/bootstrap.php';
    $options = parseOptions(array_slice($argv, 1));
    $dryRun = isset($options['dry-run']);
    $deleteBitrix = isset($options['delete-bitrix']);
    $deleteContacts = isset($options['delete-contacts']);
    $clearSheet = isset($options['clear-sheet']);
    $clearDb = isset($options['clear-db']);

    if (($options['confirm'] ?? '') !== 'RESET_REAL_SYNC') {
        throw new InvalidArgumentException('Use --confirm=RESET_REAL_SYNC para ejecutar esta limpieza.');
    }
    if (!$deleteBitrix && !$clearSheet && !$clearDb) {
        throw new InvalidArgumentException('Indique al menos una accion: --delete-bitrix, --clear-sheet o --clear-db.');
    }

    $config = $app->config();
    $pdo = $app->database->pdo();
    $bitrix = new BitrixResetClient(Env::get('BITRIX_WEBHOOK_URL'));

    $sheetRows = $app->sheets->getRows($config->spreadsheetId, $config->sheetName, $config->headerRow);
    $dbRecords = loadDbRecords($pdo, $config);
    $deals = collectDeals($sheetRows, $dbRecords);
    $rowsToClear = collectRowsToClear($sheetRows);

    printf("Sheet: %s / %s\n", $config->spreadsheetId, $config->sheetName);
    printf("Registros DB: %d\n", count($dbRecords));
    printf("Deals detectados: %d\n", count($deals));
    printf("Filas con control a limpiar: %d\n", count($rowsToClear));
    if ($dryRun) {
        echo "DRY RUN: no se modificara nada.\n";
    }

    $deletedDeals = 0;
    $deletedContacts = 0;
    $bitrixErrors = 0;
    if ($deleteBitrix) {
        foreach ($deals as $dealId => $source) {
            $dealId = (string) $dealId;
            try {
                $deal = $bitrix->getDeal($dealId);
                $contactId = trim((string) ($deal['CONTACT_ID'] ?? ''));
                printf("Bitrix deal %s (%s)%s\n", $dealId, $source, $contactId !== '' ? ' contacto ' . $contactId : '');
                if (!$dryRun) {
                    $bitrix->deleteDeal($dealId);
                    $deletedDeals++;
                    if ($deleteContacts && $contactId !== '') {
                        $bitrix->deleteContact($contactId);
                        $deletedContacts++;
                    }
                }
            } catch (Throwable $exception) {
                $bitrixErrors++;
                fwrite(STDERR, 'No se pudo borrar deal ' . $dealId . ': ' . SensitiveData::clean($exception->getMessage()) . PHP_EOL);
            }
        }
    }

    $clearedRows = 0;
    if ($clearSheet) {
        foreach ($rowsToClear as $rowNumber) {
            printf("Limpiar controles fila %d\n", $rowNumber);
            if (!$dryRun) {
                $app->sheets->updateControlValues(
                    $config->spreadsheetId,
                    $config->sheetName,
                    $config->headerRow,
                    $rowNumber,
                    [
                        IntegrationConfig::CONTROL_STATUS => '',
                        IntegrationConfig::CONTROL_DEAL_ID => '',
                        IntegrationConfig::CONTROL_SYNCED_AT => '',
                        IntegrationConfig::CONTROL_ERROR => '',
                        IntegrationConfig::CONTROL_UNIQUE_ID => '',
                    ],
                );
                $clearedRows++;
            }
        }
    }

    if ($clearDb) {
        echo "Limpiar processed_rows, logs y rate_limits\n";
        if (!$dryRun) {
            $pdo->exec('DELETE FROM processed_rows');
            $pdo->exec('DELETE FROM logs');
            $pdo->exec('DELETE FROM rate_limits');
            $pdo->exec('VACUUM');
        }
    }

    echo json_encode([
        'ok' => $bitrixErrors === 0,
        'dry_run' => $dryRun,
        'deleted_deals' => $deletedDeals,
        'deleted_contacts' => $deletedContacts,
        'bitrix_errors' => $bitrixErrors,
        'cleared_sheet_rows' => $clearedRows,
        'cleared_db' => $clearDb && !$dryRun,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

    exit($bitrixErrors > 0 ? 2 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[reset-real-sync] ' . SensitiveData::clean($exception->getMessage()) . PHP_EOL);
    exit(1);
}

function parseOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--')) {
            continue;
        }
        $value = true;
        $name = substr($argument, 2);
        if (str_contains($name, '=')) {
            [$name, $value] = explode('=', $name, 2);
        }
        $options[$name] = $value;
    }

    return $options;
}

function loadDbRecords(PDO $pdo, App\Config\IntegrationConfig $config): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT row_number, unique_identifier, status, deal_id
        FROM processed_rows
        WHERE spreadsheet_id = :spreadsheet_id AND sheet_name = :sheet_name
        ORDER BY row_number
    SQL);
    $statement->execute([
        'spreadsheet_id' => $config->spreadsheetId,
        'sheet_name' => $config->sheetName,
    ]);

    return $statement->fetchAll();
}

function collectDeals(array $sheetRows, array $dbRecords): array
{
    $deals = [];
    foreach ($dbRecords as $record) {
        $dealId = trim((string) ($record['deal_id'] ?? ''));
        if ($dealId !== '') {
            $deals[$dealId] = 'db fila ' . (string) ($record['row_number'] ?? '');
        }
    }
    foreach ($sheetRows as $row) {
        $values = (array) ($row['values'] ?? []);
        $dealId = trim((string) ($values[IntegrationConfig::CONTROL_DEAL_ID] ?? ''));
        if ($dealId !== '') {
            $deals[$dealId] = 'sheet fila ' . (string) ($row['number'] ?? '');
        }
    }

    return $deals;
}

function collectRowsToClear(array $sheetRows): array
{
    $headers = IntegrationConfig::controlHeaders();
    $rows = [];
    foreach ($sheetRows as $row) {
        $values = (array) ($row['values'] ?? []);
        foreach ($headers as $header) {
            if (trim((string) ($values[$header] ?? '')) !== '') {
                $rows[] = (int) $row['number'];
                break;
            }
        }
    }

    return $rows;
}

final class BitrixResetClient
{
    public function __construct(private readonly string $webhookUrl)
    {
    }

    public function getDeal(string $dealId): array
    {
        $result = $this->call('crm.deal.get', ['id' => $dealId]);

        return is_array($result) ? $result : [];
    }

    public function deleteDeal(string $dealId): void
    {
        $this->call('crm.deal.delete', ['id' => $dealId]);
    }

    public function deleteContact(string $contactId): void
    {
        $this->call('crm.contact.delete', ['id' => $contactId]);
    }

    private function call(string $method, array $parameters): mixed
    {
        $base = rtrim(trim($this->webhookUrl), '/');
        if ($base === '') {
            throw new RuntimeException('Configure BITRIX_WEBHOOK_URL.');
        }

        $handle = curl_init($base . '/' . $method . '.json');
        if ($handle === false) {
            throw new RuntimeException('No fue posible iniciar cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($handle);
        $curlError = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false || $curlError !== '') {
            throw new RuntimeException('Bitrix cURL: ' . $curlError);
        }

        $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        if ($status < 200 || $status >= 300 || isset($decoded['error'])) {
            throw new RuntimeException('Bitrix: ' . (string) ($decoded['error_description'] ?? $decoded['error'] ?? 'Solicitud rechazada'));
        }

        return $decoded['result'] ?? null;
    }
}
