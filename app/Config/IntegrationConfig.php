<?php

declare(strict_types=1);

namespace App\Config;

use App\Infrastructure\SettingsRepository;
use App\Support\Env;

final class IntegrationConfig
{
    public const CONTROL_STATUS = 'Estado sincronización';
    public const CONTROL_DEAL_ID = 'ID negociación Bitrix';
    public const CONTROL_SYNCED_AT = 'Fecha de sincronización';
    public const CONTROL_ERROR = 'Error de sincronización';
    public const CONTROL_UNIQUE_ID = 'Identificador único';

    public function __construct(
        public bool $active,
        public string $spreadsheetId,
        public string $sheetName,
        public int $headerRow,
        public string $categoryId,
        public string $stageId,
        public string $assignedById,
        public int $batchSize,
        public int $lockTtl,
        public array $mapping,
        public array $detectedHeaders,
    ) {
    }

    public static function from(SettingsRepository $settings): self
    {
        $defaultHeaders = ['Nombre', 'Teléfono', 'Correo', 'Ciudad', 'Cargo', 'Estado', 'Etapa'];
        $mapping = $settings->get('mapping', ['Nombre' => 'TITLE', 'Etapa' => 'STAGE_ID']);

        return new self(
            (bool) $settings->get('active', false),
            trim((string) $settings->get('spreadsheet_id', Env::get('GOOGLE_SPREADSHEET_ID'))),
            trim((string) $settings->get('sheet_name', Env::get('GOOGLE_SHEET_NAME'))),
            max(1, (int) $settings->get('header_row', 1)),
            trim((string) $settings->get('category_id', Env::get('BITRIX_CATEGORY_ID', '216'))),
            trim((string) $settings->get('stage_id', Env::get('BITRIX_STAGE_ID', 'C216:UC_Y5905W'))),
            trim((string) $settings->get('assigned_by_id', Env::get('BITRIX_ASSIGNED_BY_ID'))),
            max(1, min(100, (int) $settings->get('batch_size', Env::int('SYNC_BATCH_SIZE', 25)))),
            max(30, (int) $settings->get('lock_ttl', Env::int('SYNC_LOCK_TTL', 300))),
            is_array($mapping) ? $mapping : [],
            (array) $settings->get('detected_headers', $defaultHeaders),
        );
    }

    public static function controlHeaders(): array
    {
        return [
            self::CONTROL_STATUS,
            self::CONTROL_DEAL_ID,
            self::CONTROL_SYNCED_AT,
            self::CONTROL_ERROR,
            self::CONTROL_UNIQUE_ID,
        ];
    }

    public function validate(): array
    {
        $errors = [];
        if ($this->spreadsheetId === '') {
            $errors[] = 'Configure el ID del documento de Google Sheets.';
        }
        if ($this->sheetName === '') {
            $errors[] = 'Configure el nombre de la hoja.';
        }
        if ($this->categoryId === '' || !ctype_digit($this->categoryId)) {
            $errors[] = 'CATEGORY_ID debe ser numérico.';
        }
        if ($this->stageId === '') {
            $errors[] = 'Configure STAGE_ID.';
        }
        if (!in_array('TITLE', $this->mapping, true)) {
            $errors[] = 'Asigne una columna de Google Sheets al campo TITLE.';
        }

        foreach ($this->mapping as $column => $field) {
            if (!is_string($column) || !is_string($field) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $field)) {
                $errors[] = 'El mapeo contiene un código de campo Bitrix inválido.';
                break;
            }
        }

        return $errors;
    }
}
