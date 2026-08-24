<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

final class SettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $statement = $this->pdo->prepare('SELECT value FROM settings WHERE key = :key');
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();

        if ($value === false) {
            return $default;
        }

        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $values = [
            'key' => $key,
            'value' => $encoded,
            'updated_at' => gmdate('c'),
        ];

        $update = $this->pdo->prepare(<<<'SQL'
            UPDATE settings
            SET value = :value, updated_at = :updated_at
            WHERE key = :key
        SQL);
        $update->execute($values);

        if ($update->rowCount() > 0) {
            return;
        }

        $insert = $this->pdo->prepare(<<<'SQL'
            INSERT INTO settings (key, value, updated_at)
            VALUES (:key, :value, :updated_at)
        SQL);
        $insert->execute($values);
    }

    public function setMany(array $values): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $this->set((string) $key, $value);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
