<?php

declare(strict_types=1);

use App\Bitrix\BitrixGatewayInterface;
use App\Config\IntegrationConfig;
use App\Google\SheetsGateway;
use App\Google\SheetsGatewayInterface;
use App\Infrastructure\Database;
use App\Infrastructure\Logger;
use App\Infrastructure\ProcessedRowRepository;
use App\Sync\SyncService;

require dirname(__DIR__) . '/vendor/autoload.php';

final class FakeSheets implements SheetsGatewayInterface
{
    public array $headers = ['Nombre', 'Teléfono', 'Correo', 'Ciudad', 'Etapa'];
    public array $rows = [];
    public bool $failCreatedUpdate = false;

    public function testConnection(string $spreadsheetId): array
    {
        return ['title' => 'Test', 'sheets' => ['Leads']];
    }

    public function listSheets(string $spreadsheetId): array
    {
        return ['Leads'];
    }

    public function getHeaders(string $spreadsheetId, string $sheetName, int $headerRow): array
    {
        return $this->headers;
    }

    public function ensureControlColumns(string $spreadsheetId, string $sheetName, int $headerRow, array $controlHeaders): array
    {
        foreach ($controlHeaders as $header) {
            if (!in_array($header, $this->headers, true)) {
                $this->headers[] = $header;
            }
        }

        return $this->headers;
    }

    public function getRows(string $spreadsheetId, string $sheetName, int $headerRow, ?int $specificRow = null): array
    {
        $rows = [];
        foreach ($this->rows as $number => $values) {
            if ($specificRow !== null && $specificRow !== $number) {
                continue;
            }
            $rows[] = ['number' => $number, 'values' => $values];
        }

        return $rows;
    }

    public function updateControlValues(string $spreadsheetId, string $sheetName, int $headerRow, int $rowNumber, array $values): void
    {
        if ($this->failCreatedUpdate && ($values[IntegrationConfig::CONTROL_STATUS] ?? '') === 'CREADA') {
            throw new RuntimeException('Fallo simulado de Google.');
        }
        $this->rows[$rowNumber] = array_merge($this->rows[$rowNumber] ?? [], $values);
    }
}

final class FakeBitrix implements BitrixGatewayInterface
{
    public int $created = 0;
    public int $contactsCreated = 0;
    public array $contacts = [];
    public array $deals = [];
    private array $byOrigin = [];

    public function testConnection(): array
    {
        return ['deal_fields' => 2];
    }

    public function dealFields(): array
    {
        return ['TITLE' => [], 'STAGE_ID' => []];
    }

    public function findDealByOrigin(string $originatorId, string $originId): ?string
    {
        return $this->byOrigin[$originatorId . ':' . $originId] ?? null;
    }

    public function createDeal(array $fields): string
    {
        $this->created++;
        $id = (string) (1000 + $this->created);
        $this->byOrigin[$fields['ORIGINATOR_ID'] . ':' . $fields['ORIGIN_ID']] = $id;
        $this->deals[] = $fields;

        return $id;
    }

    public function createContact(array $fields): string
    {
        $this->contactsCreated++;
        $this->contacts[] = $fields;

        return (string) (5000 + $this->contactsCreated);
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function service(FakeSheets $sheets, FakeBitrix $bitrix): array
{
    $path = tempnam(sys_get_temp_dir(), 'sbs-test-');
    if ($path === false) {
        throw new RuntimeException('No se pudo crear la base temporal.');
    }
    $database = new Database($path);
    $rows = new ProcessedRowRepository($database->pdo());
    $logger = new Logger($database->pdo());

    return [new SyncService($sheets, $bitrix, $rows, $logger), $rows, $path];
}

function config(?array $mapping = null, ?array $detectedHeaders = null): IntegrationConfig
{
    $mapping ??= ['Nombre' => 'TITLE', 'Etapa' => 'STAGE_ID'];
    $detectedHeaders ??= ['Nombre', 'Etapa'];

    return new IntegrationConfig(
        true,
        'spreadsheet-test',
        'Leads',
        1,
        '216',
        'C216:UC_Y5905W',
        '7',
        25,
        300,
        $mapping,
        $detectedHeaders,
    );
}

$tests = [];
$tests['convierte columnas A1'] = static function (): void {
    expect(SheetsGateway::columnLetter(1) === 'A', 'La columna 1 debe ser A.');
    expect(SheetsGateway::columnLetter(26) === 'Z', 'La columna 26 debe ser Z.');
    expect(SheetsGateway::columnLetter(27) === 'AA', 'La columna 27 debe ser AA.');
    expect(SheetsGateway::columnLetter(703) === 'AAA', 'La columna 703 debe ser AAA.');
};

$tests['crea y no duplica'] = static function (): void {
    $sheets = new FakeSheets();
    $sheets->rows[2] = [
        'Nombre(Name)' => 'Negocio Uno',
        'Teléfono(telefono)' => '3001234567',
        'Email(email)' => 'uno@test.local',
        'Ciudad' => 'Bogota',
        'Etapa' => 'C216:UC_Y5905W',
        'TELÉFONO CLIENTE (DEF)' => '3101234567',
        'CORREO CLIENTE (DEF)' => '',
        'Estado del crédito (al día?)(estado_del_credito_al_dia)' => 'Si',
        'Nombre del banco(nombre_del_banco)' => 'Banco Uno',
        'RESPONSABLE' => 'Daniel Maestre Antequera',
        'REFERENCIADOR' => 'One Credit SAS',
        'OBSERVADOR' => 'One Credit SAS',
    ];
    $bitrix = new FakeBitrix();
    [$sync, , $path] = service($sheets, $bitrix);
    $config = config(
        ['Nombre(Name)' => 'TITLE', 'Etapa' => 'STAGE_ID'],
        ['Nombre(Name)', 'Etapa'],
    );
    $first = $sync->run($config);
    $second = $sync->run($config);
    expect($first['created'] === 1, 'Debe crear una negociación.');
    expect($second['created'] === 0, 'El segundo ciclo no debe crear otra negociación.');
    expect($bitrix->created === 1, 'Bitrix solo debe recibir una creación.');
    expect($bitrix->contactsCreated === 1, 'Debe crear un contacto antes de la negociacion.');
    expect(($bitrix->deals[0]['CONTACT_ID'] ?? '') === '5001', 'La negociacion debe quedar asociada al contacto.');
    expect(($bitrix->contacts[0]['NAME'] ?? '') === 'Negocio Uno', 'El contacto debe usar la columna Nombre.');
    expect(($bitrix->contacts[0]['PHONE'][0]['VALUE'] ?? '') === '3101234567', 'El contacto debe incluir telefono.');
    expect(($bitrix->contacts[0]['EMAIL'][0]['VALUE'] ?? '') === 'uno@test.local', 'El contacto debe incluir correo.');
    expect(($bitrix->contacts[0]['ADDRESS_CITY'] ?? '') === 'Bogota', 'El contacto debe incluir ciudad.');
    expect(($bitrix->deals[0]['COMMENTS'] ?? '') === "Esta el credito al dia: Si\n\nBanco: Banco Uno", 'La negociacion debe incluir el comentario armado.');
    expect(($bitrix->deals[0]['UF_CRM_1584616588730'] ?? '') === '3101234567', 'La negociacion debe incluir telefono cliente.');
    expect(($bitrix->deals[0]['UF_CRM_1584616599364'] ?? '') === 'uno@test.local', 'La negociacion debe incluir el correo real.');
    expect(($bitrix->deals[0]['ASSIGNED_BY_ID'] ?? '') === '2582', 'La negociacion debe asignar responsable por nombre.');
    expect(($bitrix->deals[0]['UF_CRM_1589344028'] ?? '') === '21026', 'La negociacion debe asignar referenciador por nombre.');
    expect(($bitrix->deals[0]['OBSERVER_IDS'][0] ?? '') === '21026', 'La negociacion debe asignar observador por nombre.');
    expect($sheets->rows[2][IntegrationConfig::CONTROL_STATUS] === 'CREADA', 'La fila debe quedar CREADA.');
    @unlink($path);
};

$tests['asigna usuarios por defecto cuando vienen vacios'] = static function (): void {
    $sheets = new FakeSheets();
    $sheets->rows[2] = [
        'Nombre(Name)' => 'Negocio Sin Usuarios',
        'Teléfono(telefono)' => '3001234567',
        'Email(email)' => 'sinusuarios@test.local',
        'RESPONSABLE' => '',
        'REFERENCIADOR' => '',
        'OBSERVADOR' => '',
    ];
    $bitrix = new FakeBitrix();
    [$sync, , $path] = service($sheets, $bitrix);
    $sync->run(config(['Nombre(Name)' => 'TITLE'], ['Nombre(Name)']));
    expect(($bitrix->deals[0]['ASSIGNED_BY_ID'] ?? '') === '2582', 'La negociacion debe asignar responsable por defecto.');
    expect(($bitrix->deals[0]['UF_CRM_1589344028'] ?? '') === '21026', 'La negociacion debe asignar referenciador por defecto.');
    expect(($bitrix->deals[0]['OBSERVER_IDS'][0] ?? '') === '21026', 'La negociacion debe asignar observador por defecto.');
    @unlink($path);
};

$tests['recupera fallo del Sheet después de crear'] = static function (): void {
    $sheets = new FakeSheets();
    $sheets->rows[3] = ['Nombre' => 'Negocio Dos', 'Teléfono' => '3002222222', 'Correo' => 'dos@test.local', 'Ciudad' => 'Cali', 'Etapa' => ''];
    $sheets->failCreatedUpdate = true;
    $bitrix = new FakeBitrix();
    [$sync, $rows, $path] = service($sheets, $bitrix);
    $first = $sync->run(config());
    $pendingRecovery = $rows->latest(1)[0] ?? [];
    expect(($pendingRecovery['deal_id'] ?? '') === '1001', 'Debe guardar el ID de Bitrix antes de reparar Google.');
    expect(($pendingRecovery['sheet_synced_at'] ?? '') === '', 'El cierre del Sheet debe quedar pendiente.');
    expect($first['created'] === 1, 'La negociación debe persistirse aunque falle Google.');
    $sheets->failCreatedUpdate = false;
    $sync->run(config());
    $recovered = $rows->latest(1)[0] ?? [];
    expect(($recovered['sheet_synced_at'] ?? '') !== '', 'Debe marcar el cierre del Sheet al reparar.');
    expect($bitrix->created === 1, 'La recuperación no debe duplicar la negociación.');
    expect($bitrix->contactsCreated === 1, 'La recuperacion no debe duplicar el contacto.');
    expect($sheets->rows[3][IntegrationConfig::CONTROL_STATUS] === 'CREADA', 'El estado debe repararse.');
    @unlink($path);
};

$tests['bloquea procesamiento concurrente'] = static function (): void {
    $sheets = new FakeSheets();
    $bitrix = new FakeBitrix();
    [, $rows, $path] = service($sheets, $bitrix);
    $first = $rows->claim('sheet', 'Leads', 8, 'unique-8', 300);
    $second = $rows->claim('sheet', 'Leads', 8, 'unique-8', 300);
    expect($first['state'] === 'claimed', 'El primer proceso debe obtener el bloqueo.');
    expect($second['state'] === 'locked', 'El segundo proceso debe quedar bloqueado.');
    @unlink($path);
};

$tests['registra error de validación'] = static function (): void {
    $sheets = new FakeSheets();
    $sheets->rows[4] = ['Nombre' => '', 'Etapa' => 'C216:UC_Y5905W', 'Ciudad' => 'Bogotá'];
    $bitrix = new FakeBitrix();
    [$sync, , $path] = service($sheets, $bitrix);
    $result = $sync->run(config());
    expect($result['errors'] === 1, 'La fila sin TITLE debe fallar.');
    expect($bitrix->created === 0, 'No debe llamar a crm.deal.add.');
    expect($bitrix->contactsCreated === 0, 'No debe llamar a crm.contact.add.');
    expect($sheets->rows[4][IntegrationConfig::CONTROL_STATUS] === 'ERROR', 'La fila debe quedar en ERROR.');
    @unlink($path);
};

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[OK] {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "[FALLO] {$name}: {$exception->getMessage()}\n";
    }
}

echo sprintf("%d pruebas, %d fallos.\n", count($tests), $failed);
exit($failed === 0 ? 0 : 1);
