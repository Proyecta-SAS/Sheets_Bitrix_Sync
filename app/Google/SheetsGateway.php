<?php

declare(strict_types=1);

namespace App\Google;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Google\Service\Sheets\ValueRange;

final class SheetsGateway implements SheetsGatewayInterface
{
    private ?Sheets $service = null;
    private array $headersCache = [];

    public function __construct(private readonly string $credentialsPath)
    {
    }

    public function testConnection(string $spreadsheetId): array
    {
        $spreadsheet = $this->service()->spreadsheets->get($spreadsheetId, [
            'fields' => 'spreadsheetId,properties.title,sheets.properties.title',
        ]);

        return [
            'title' => (string) $spreadsheet->getProperties()?->getTitle(),
            'sheets' => array_values(array_filter(array_map(
                static fn ($sheet): string => (string) $sheet->getProperties()?->getTitle(),
                $spreadsheet->getSheets() ?? [],
            ))),
        ];
    }

    public function listSheets(string $spreadsheetId): array
    {
        return $this->testConnection($spreadsheetId)['sheets'];
    }

    public function getHeaders(string $spreadsheetId, string $sheetName, int $headerRow): array
    {
        $cacheKey = $spreadsheetId . '|' . $sheetName . '|' . $headerRow;
        if (isset($this->headersCache[$cacheKey])) {
            return $this->headersCache[$cacheKey];
        }

        $range = $this->quoteSheet($sheetName) . '!' . $headerRow . ':' . $headerRow;
        $response = $this->service()->spreadsheets_values->get($spreadsheetId, $range, [
            'valueRenderOption' => 'FORMATTED_VALUE',
        ]);
        $values = $response->getValues();

        return $this->headersCache[$cacheKey] = array_map(
            static fn ($value): string => trim((string) $value),
            $values[0] ?? [],
        );
    }

    public function ensureControlColumns(string $spreadsheetId, string $sheetName, int $headerRow, array $controlHeaders): array
    {
        $headers = $this->getHeaders($spreadsheetId, $sheetName, $headerRow);
        $changed = false;
        foreach ($controlHeaders as $header) {
            if (!in_array($header, $headers, true)) {
                $headers[] = $header;
                $changed = true;
            }
        }

        if ($changed) {
            $range = sprintf(
                '%s!A%d:%s%d',
                $this->quoteSheet($sheetName),
                $headerRow,
                self::columnLetter(count($headers)),
                $headerRow,
            );
            $body = new ValueRange(['values' => [$headers]]);
            $this->service()->spreadsheets_values->update($spreadsheetId, $range, $body, [
                'valueInputOption' => 'RAW',
            ]);
            $this->headersCache[$spreadsheetId . '|' . $sheetName . '|' . $headerRow] = $headers;
        }

        return $headers;
    }

    public function getRows(string $spreadsheetId, string $sheetName, int $headerRow, ?int $specificRow = null): array
    {
        $headers = $this->getHeaders($spreadsheetId, $sheetName, $headerRow);
        if ($headers === []) {
            throw new \RuntimeException('La fila de encabezados está vacía.');
        }

        $start = $specificRow ?? ($headerRow + 1);
        $end = $specificRow !== null ? (string) $specificRow : '';
        $lastColumn = self::columnLetter(count($headers));
        $range = sprintf('%s!A%d:%s%s', $this->quoteSheet($sheetName), $start, $lastColumn, $end);
        $response = $this->service()->spreadsheets_values->get($spreadsheetId, $range, [
            'valueRenderOption' => 'FORMATTED_VALUE',
            'dateTimeRenderOption' => 'FORMATTED_STRING',
        ]);

        $rows = [];
        foreach ($response->getValues() ?? [] as $offset => $values) {
            $number = $specificRow ?? ($start + $offset);
            $values = array_pad($values, count($headers), '');
            $rows[] = [
                'number' => $number,
                'values' => array_combine($headers, array_slice($values, 0, count($headers))) ?: [],
            ];
        }

        return $rows;
    }

    public function updateControlValues(string $spreadsheetId, string $sheetName, int $headerRow, int $rowNumber, array $values): void
    {
        $headers = $this->getHeaders($spreadsheetId, $sheetName, $headerRow);
        $data = [];
        foreach ($values as $header => $value) {
            $index = array_search($header, $headers, true);
            if ($index === false) {
                throw new \RuntimeException(sprintf('No existe la columna de control "%s".', $header));
            }
            $cell = self::columnLetter($index + 1) . $rowNumber;
            $data[] = new ValueRange([
                'range' => $this->quoteSheet($sheetName) . '!' . $cell,
                'values' => [[(string) $value]],
            ]);
        }

        if ($data === []) {
            return;
        }

        $body = new BatchUpdateValuesRequest([
            'valueInputOption' => 'RAW',
            'data' => $data,
        ]);
        $this->service()->spreadsheets_values->batchUpdate($spreadsheetId, $body);
    }

    public static function columnLetter(int $index): string
    {
        if ($index < 1) {
            throw new \InvalidArgumentException('El índice de columna debe ser mayor que cero.');
        }

        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private function service(): Sheets
    {
        if ($this->service !== null) {
            return $this->service;
        }
        if (!is_file($this->credentialsPath) || !is_readable($this->credentialsPath)) {
            throw new \RuntimeException('No se encuentra el JSON de la cuenta de servicio en GOOGLE_CREDENTIALS_PATH.');
        }

        $client = new Client();
        $client->setApplicationName('Sheets Bitrix Sync');
        $client->setAuthConfig($this->credentialsPath);
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');

        return $this->service = new Sheets($client);
    }

    private function quoteSheet(string $sheetName): string
    {
        return "'" . str_replace("'", "''", $sheetName) . "'";
    }
}
