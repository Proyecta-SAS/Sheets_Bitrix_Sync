<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Support\Uuid;
use PDO;

final class ProcessedRowRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(string $spreadsheetId, string $sheetName, int $rowNumber): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT * FROM processed_rows
            WHERE spreadsheet_id = :spreadsheet_id AND sheet_name = :sheet_name AND row_number = :row_number
        SQL);
        $statement->execute([
            'spreadsheet_id' => $spreadsheetId,
            'sheet_name' => $sheetName,
            'row_number' => $rowNumber,
        ]);

        return $statement->fetch() ?: null;
    }

    public function claim(string $spreadsheetId, string $sheetName, int $rowNumber, string $identifier, int $lockTtl): array
    {
        $token = Uuid::random();
        $now = gmdate('c');
        $staleBefore = gmdate('c', time() - $lockTtl);

        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $existing = $this->find($spreadsheetId, $sheetName, $rowNumber);
            if ($existing !== null && $existing['deal_id'] !== null && $existing['deal_id'] !== '') {
                $this->pdo->exec('COMMIT');

                return ['state' => 'created', 'record' => $existing];
            }

            if ($existing !== null && $existing['status'] === 'PROCESANDO' && ($existing['locked_at'] ?? '') > $staleBefore) {
                $this->pdo->exec('COMMIT');

                return ['state' => 'locked', 'record' => $existing];
            }

            if ($existing === null) {
                $statement = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO processed_rows (
                        spreadsheet_id, sheet_name, row_number, unique_identifier, status,
                        attempts, lock_token, locked_at, created_at, updated_at
                    ) VALUES (
                        :spreadsheet_id, :sheet_name, :row_number, :unique_identifier, 'PROCESANDO',
                        1, :lock_token, :locked_at, :created_at, :updated_at
                    )
                SQL);
                $statement->execute([
                    'spreadsheet_id' => $spreadsheetId,
                    'sheet_name' => $sheetName,
                    'row_number' => $rowNumber,
                    'unique_identifier' => $identifier,
                    'lock_token' => $token,
                    'locked_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $id = (int) $existing['id'];
                $statement = $this->pdo->prepare(<<<'SQL'
                    UPDATE processed_rows
                    SET unique_identifier = :unique_identifier, status = 'PROCESANDO', attempts = attempts + 1,
                        last_error = NULL, lock_token = :lock_token, locked_at = :locked_at, updated_at = :updated_at
                    WHERE id = :id
                SQL);
                $statement->execute([
                    'unique_identifier' => $identifier,
                    'lock_token' => $token,
                    'locked_at' => $now,
                    'updated_at' => $now,
                    'id' => $id,
                ]);
            }

            $this->pdo->exec('COMMIT');

            return ['state' => 'claimed', 'id' => $id, 'token' => $token];
        } catch (\Throwable $exception) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (\Throwable) {
                // La transacción pudo haber terminado antes de que ocurriera la excepción.
            }
            throw $exception;
        }
    }

    public function markCreated(int $id, string $dealId, string $payloadHash): void
    {
        $now = gmdate('c');
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE processed_rows
            SET status = 'CREADA', deal_id = :deal_id, payload_hash = :payload_hash,
                bitrix_synced_at = COALESCE(bitrix_synced_at, :bitrix_synced_at),
                last_error = NULL, lock_token = NULL, locked_at = NULL, updated_at = :updated_at
            WHERE id = :id
        SQL);
        $statement->execute([
            'deal_id' => $dealId,
            'payload_hash' => $payloadHash,
            'bitrix_synced_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);
    }

    public function markDuplicate(int $id, string $message): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE processed_rows
            SET status = 'DUPLICADO', last_error = :last_error, lock_token = NULL,
                locked_at = NULL, updated_at = :updated_at
            WHERE id = :id AND deal_id IS NULL
        SQL);
        $statement->execute([
            'last_error' => substr($message, 0, 1000),
            'updated_at' => gmdate('c'),
            'id' => $id,
        ]);
    }

    public function reconcileCreated(string $spreadsheetId, string $sheetName, int $rowNumber, string $identifier, string $dealId): void
    {
        $now = gmdate('c');
        $values = [
            'spreadsheet_id' => $spreadsheetId,
            'sheet_name' => $sheetName,
            'row_number' => $rowNumber,
            'unique_identifier' => $identifier,
            'deal_id' => $dealId,
            'bitrix_synced_at' => $now,
            'sheet_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare(<<<'SQL'
                UPDATE processed_rows
                SET unique_identifier = :unique_identifier,
                    status = 'CREADA',
                    deal_id = :deal_id,
                    bitrix_synced_at = COALESCE(bitrix_synced_at, :bitrix_synced_at),
                    sheet_synced_at = COALESCE(sheet_synced_at, :sheet_synced_at),
                    last_error = NULL,
                    lock_token = NULL,
                    locked_at = NULL,
                    updated_at = :updated_at
                WHERE spreadsheet_id = :spreadsheet_id AND sheet_name = :sheet_name AND row_number = :row_number
            SQL);
            $update->execute([
                'spreadsheet_id' => $spreadsheetId,
                'sheet_name' => $sheetName,
                'row_number' => $rowNumber,
                'unique_identifier' => $identifier,
                'deal_id' => $dealId,
                'bitrix_synced_at' => $now,
                'sheet_synced_at' => $now,
                'updated_at' => $now,
            ]);

            if ($update->rowCount() === 0) {
                $insert = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO processed_rows (
                        spreadsheet_id, sheet_name, row_number, unique_identifier, status, deal_id,
                        attempts, bitrix_synced_at, sheet_synced_at, created_at, updated_at
                    ) VALUES (
                        :spreadsheet_id, :sheet_name, :row_number, :unique_identifier, 'CREADA', :deal_id,
                        0, :bitrix_synced_at, :sheet_synced_at, :created_at, :updated_at
                    )
                SQL);
                $insert->execute($values);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function markSheetSynced(string $spreadsheetId, string $sheetName, int $rowNumber, string $dealId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE processed_rows
            SET status = 'CREADA', deal_id = :deal_id,
                bitrix_synced_at = COALESCE(bitrix_synced_at, :synced_at),
                sheet_synced_at = :synced_at,
                last_error = NULL, lock_token = NULL, locked_at = NULL, updated_at = :synced_at
            WHERE spreadsheet_id = :spreadsheet_id AND sheet_name = :sheet_name AND row_number = :row_number
        SQL);
        $statement->execute([
            'deal_id' => $dealId,
            'synced_at' => gmdate('c'),
            'spreadsheet_id' => $spreadsheetId,
            'sheet_name' => $sheetName,
            'row_number' => $rowNumber,
        ]);
    }

    public function markError(int $id, string $message): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE processed_rows
            SET status = 'ERROR', last_error = :last_error, lock_token = NULL,
                locked_at = NULL, updated_at = :updated_at
            WHERE id = :id AND deal_id IS NULL
        SQL);
        $statement->execute([
            'last_error' => substr($message, 0, 1000),
            'updated_at' => gmdate('c'),
            'id' => $id,
        ]);
    }

    public function retry(int $id): bool
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE processed_rows
            SET status = 'PENDIENTE', last_error = NULL, lock_token = NULL, locked_at = NULL, updated_at = :updated_at
            WHERE id = :id AND status = 'ERROR' AND deal_id IS NULL
        SQL);
        $statement->execute(['updated_at' => gmdate('c'), 'id' => $id]);

        return $statement->rowCount() === 1;
    }

    public function retryAll(): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE processed_rows
            SET status = 'PENDIENTE', last_error = NULL, lock_token = NULL, locked_at = NULL, updated_at = :updated_at
            WHERE status = 'ERROR' AND deal_id IS NULL
        SQL);
        $statement->execute(['updated_at' => gmdate('c')]);

        return $statement->rowCount();
    }

    public function latest(int $limit = 100): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM processed_rows ORDER BY updated_at DESC, id DESC LIMIT :limit');
        $statement->bindValue('limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function counts(): array
    {
        $rows = $this->pdo->query('SELECT status, COUNT(*) AS total FROM processed_rows GROUP BY status')->fetchAll();
        $counts = ['PENDIENTE' => 0, 'PROCESANDO' => 0, 'CREADA' => 0, 'DUPLICADO' => 0, 'ERROR' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function flowCounts(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'PENDIENTE' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'PROCESANDO' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = 'DUPLICADO' THEN 1 ELSE 0 END) AS duplicates,
                SUM(CASE WHEN status = 'ERROR' THEN 1 ELSE 0 END) AS errors,
                SUM(CASE WHEN deal_id IS NOT NULL AND deal_id <> '' THEN 1 ELSE 0 END) AS bitrix_created,
                SUM(CASE WHEN sheet_synced_at IS NOT NULL AND sheet_synced_at <> '' THEN 1 ELSE 0 END) AS sheet_synced
            FROM processed_rows
        SQL)->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'duplicates' => (int) ($row['duplicates'] ?? 0),
            'errors' => (int) ($row['errors'] ?? 0),
            'bitrix_created' => (int) ($row['bitrix_created'] ?? 0),
            'sheet_synced' => (int) ($row['sheet_synced'] ?? 0),
        ];
    }
}
