<?php

declare(strict_types=1);

namespace App\Google;

interface SheetsGatewayInterface
{
    public function testConnection(string $spreadsheetId): array;

    public function listSheets(string $spreadsheetId): array;

    public function getHeaders(string $spreadsheetId, string $sheetName, int $headerRow): array;

    public function ensureControlColumns(string $spreadsheetId, string $sheetName, int $headerRow, array $controlHeaders): array;

    public function getRows(string $spreadsheetId, string $sheetName, int $headerRow, ?int $specificRow = null): array;

    public function updateControlValues(string $spreadsheetId, string $sheetName, int $headerRow, int $rowNumber, array $values): void;
}
