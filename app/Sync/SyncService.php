<?php

declare(strict_types=1);

namespace App\Sync;

use App\Bitrix\BitrixGatewayInterface;
use App\Config\IntegrationConfig;
use App\Google\SheetsGatewayInterface;
use App\Infrastructure\Logger;
use App\Infrastructure\ProcessedRowRepository;
use App\Support\SensitiveData;
use App\Support\Uuid;

final class SyncService
{
    private const ORIGINATOR_ID = 'SHEETS_BITRIX_SYNC';

    public function __construct(
        private readonly SheetsGatewayInterface $sheets,
        private readonly BitrixGatewayInterface $bitrix,
        private readonly ProcessedRowRepository $rows,
        private readonly Logger $logger,
    ) {
    }

    public function run(IntegrationConfig $config, ?int $specificRow = null, bool $force = false): array
    {
        if (!$config->active && !$force) {
            return $this->summary('inactive');
        }

        $errors = $config->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
        if ($specificRow !== null && $specificRow <= $config->headerRow) {
            throw new \InvalidArgumentException('El número de fila debe estar debajo de los encabezados.');
        }

        $this->sheets->ensureControlColumns(
            $config->spreadsheetId,
            $config->sheetName,
            $config->headerRow,
            IntegrationConfig::controlHeaders(),
        );
        $sheetRows = $this->sheets->getRows(
            $config->spreadsheetId,
            $config->sheetName,
            $config->headerRow,
            $specificRow,
        );

        $summary = $this->summary('ok');
        foreach ($sheetRows as $sheetRow) {
            if ($summary['processed'] >= $config->batchSize) {
                break;
            }

            $rowNumber = (int) $sheetRow['number'];
            $values = (array) $sheetRow['values'];
            if ($this->isEmptyBusinessRow($values)) {
                $summary['skipped']++;
                continue;
            }

            $identifier = trim((string) ($values[IntegrationConfig::CONTROL_UNIQUE_ID] ?? ''));
            if ($identifier === '') {
                $identifier = Uuid::forSheetRow($config->spreadsheetId, $config->sheetName, $rowNumber);
                $this->sheets->updateControlValues(
                    $config->spreadsheetId,
                    $config->sheetName,
                    $config->headerRow,
                    $rowNumber,
                    [
                        IntegrationConfig::CONTROL_UNIQUE_ID => $identifier,
                        IntegrationConfig::CONTROL_STATUS => 'PENDIENTE',
                    ],
                );
                $values[IntegrationConfig::CONTROL_UNIQUE_ID] = $identifier;
                $values[IntegrationConfig::CONTROL_STATUS] = 'PENDIENTE';
            }

            $sheetStatus = strtoupper(trim((string) ($values[IntegrationConfig::CONTROL_STATUS] ?? '')));
            $sheetDealId = trim((string) ($values[IntegrationConfig::CONTROL_DEAL_ID] ?? ''));
            $local = $this->rows->find($config->spreadsheetId, $config->sheetName, $rowNumber);

            if ($sheetStatus === 'CREADA' && $sheetDealId !== '') {
                $this->rows->reconcileCreated(
                    $config->spreadsheetId,
                    $config->sheetName,
                    $rowNumber,
                    $identifier,
                    $sheetDealId,
                );
                $summary['already_created']++;
                continue;
            }

            if ($local !== null && (string) ($local['deal_id'] ?? '') !== '') {
                $dealId = (string) $local['deal_id'];
                $this->recoverSheetStatus($config, $rowNumber, $identifier, $dealId);
                $this->rows->markSheetSynced($config->spreadsheetId, $config->sheetName, $rowNumber, $dealId);
                $summary['already_created']++;
                continue;
            }

            $localPending = ($local['status'] ?? '') === 'PENDIENTE';
            if ($specificRow === null && !$localPending && !in_array($sheetStatus, ['', 'PENDIENTE'], true)) {
                $summary['skipped']++;
                continue;
            }

            $result = $this->processRow($config, $rowNumber, $values, $identifier);
            $summary['results'][] = $result;
            if ($result['status'] === 'created') {
                $summary['created']++;
                $summary['processed']++;
            } elseif ($result['status'] === 'error') {
                $summary['errors']++;
                $summary['processed']++;
            } elseif ($result['status'] === 'locked') {
                $summary['locked']++;
            } else {
                $summary['already_created']++;
            }
        }

        return $summary;
    }

    private function processRow(IntegrationConfig $config, int $rowNumber, array $values, string $identifier): array
    {
        $claim = $this->rows->claim(
            $config->spreadsheetId,
            $config->sheetName,
            $rowNumber,
            $identifier,
            $config->lockTtl,
        );

        if ($claim['state'] === 'created') {
            $dealId = (string) $claim['record']['deal_id'];
            $this->recoverSheetStatus($config, $rowNumber, $identifier, $dealId);
            $this->rows->markSheetSynced($config->spreadsheetId, $config->sheetName, $rowNumber, $dealId);

            return ['row' => $rowNumber, 'status' => 'already_created', 'deal_id' => $dealId];
        }
        if ($claim['state'] === 'locked') {
            return ['row' => $rowNumber, 'status' => 'locked'];
        }

        $recordId = (int) $claim['id'];
        try {
            $this->sheets->updateControlValues(
                $config->spreadsheetId,
                $config->sheetName,
                $config->headerRow,
                $rowNumber,
                [
                    IntegrationConfig::CONTROL_STATUS => 'PROCESANDO',
                    IntegrationConfig::CONTROL_ERROR => '',
                    IntegrationConfig::CONTROL_UNIQUE_ID => $identifier,
                ],
            );

            $fields = $this->buildDealFields($config, $values, $identifier);
            $payloadHash = hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $dealId = $this->bitrix->findDealByOrigin(self::ORIGINATOR_ID, $identifier);
            if ($dealId === null) {
                $dealId = $this->bitrix->createDeal($fields);
            }

            // Se persiste antes de tocar nuevamente Google Sheets: es la barrera principal contra duplicados.
            $this->rows->markCreated($recordId, $dealId, $payloadHash);
            $this->logger->info('deal.created', 'Negociación sincronizada.', [
                'row' => $rowNumber,
                'deal_id' => $dealId,
                'identifier' => $identifier,
            ]);
        } catch (\Throwable $exception) {
            $message = SensitiveData::clean($exception->getMessage());
            $this->rows->markError($recordId, $message);
            $this->logger->error('row.failed', $message, ['row' => $rowNumber, 'identifier' => $identifier]);
            try {
                $this->sheets->updateControlValues(
                    $config->spreadsheetId,
                    $config->sheetName,
                    $config->headerRow,
                    $rowNumber,
                    [
                        IntegrationConfig::CONTROL_STATUS => 'ERROR',
                        IntegrationConfig::CONTROL_ERROR => $message,
                        IntegrationConfig::CONTROL_UNIQUE_ID => $identifier,
                    ],
                );
            } catch (\Throwable $sheetException) {
                $this->logger->error('sheet.error_status_failed', SensitiveData::clean($sheetException->getMessage()), ['row' => $rowNumber]);
            }

            return ['row' => $rowNumber, 'status' => 'error', 'error' => $message];
        }

        try {
            $this->recoverSheetStatus($config, $rowNumber, $identifier, $dealId);
            $this->rows->markSheetSynced($config->spreadsheetId, $config->sheetName, $rowNumber, $dealId);
        } catch (\Throwable $exception) {
            // La negociación ya está persistida. El próximo intento solo reparará el estado del Sheet.
            $this->logger->error('sheet.recovery_pending', SensitiveData::clean($exception->getMessage()), [
                'row' => $rowNumber,
                'deal_id' => $dealId,
            ]);
        }

        return ['row' => $rowNumber, 'status' => 'created', 'deal_id' => $dealId];
    }

    private function buildDealFields(IntegrationConfig $config, array $values, string $identifier): array
    {
        $fields = [];
        foreach ($config->mapping as $sheetColumn => $bitrixField) {
            $value = trim((string) ($values[$sheetColumn] ?? ''));
            if ($value !== '') {
                $fields[$bitrixField] = $value;
            }
        }

        if (trim((string) ($fields['TITLE'] ?? '')) === '') {
            throw new \InvalidArgumentException('La fila no tiene un valor para TITLE.');
        }

        $fields['CATEGORY_ID'] = $config->categoryId;
        if (trim((string) ($fields['STAGE_ID'] ?? '')) === '') {
            $fields['STAGE_ID'] = $config->stageId;
        }
        if ($config->assignedById !== '') {
            $fields['ASSIGNED_BY_ID'] = $config->assignedById;
        }
        $fields['ORIGINATOR_ID'] = self::ORIGINATOR_ID;
        $fields['ORIGIN_ID'] = $identifier;

        ksort($fields);

        return $fields;
    }

    private function recoverSheetStatus(IntegrationConfig $config, int $rowNumber, string $identifier, string $dealId): void
    {
        $this->sheets->updateControlValues(
            $config->spreadsheetId,
            $config->sheetName,
            $config->headerRow,
            $rowNumber,
            [
                IntegrationConfig::CONTROL_STATUS => 'CREADA',
                IntegrationConfig::CONTROL_DEAL_ID => $dealId,
                IntegrationConfig::CONTROL_SYNCED_AT => date('Y-m-d H:i:s'),
                IntegrationConfig::CONTROL_ERROR => '',
                IntegrationConfig::CONTROL_UNIQUE_ID => $identifier,
            ],
        );
    }

    private function isEmptyBusinessRow(array $values): bool
    {
        foreach ($values as $header => $value) {
            if (!in_array($header, IntegrationConfig::controlHeaders(), true) && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function summary(string $status): array
    {
        return [
            'status' => $status,
            'processed' => 0,
            'created' => 0,
            'errors' => 0,
            'locked' => 0,
            'skipped' => 0,
            'already_created' => 0,
            'results' => [],
        ];
    }
}
