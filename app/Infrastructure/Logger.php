<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

final class Logger
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function info(string $event, string $message, array $context = []): void
    {
        $this->write('info', $event, $message, $context);
    }

    public function error(string $event, string $message, array $context = []): void
    {
        $this->write('error', $event, $message, $context);
    }

    public function latest(int $limit = 100): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM logs ORDER BY id DESC LIMIT :limit');
        $statement->bindValue('limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function write(string $level, string $event, string $message, array $context): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO logs (level, event, message, context, created_at)
            VALUES (:level, :event, :message, :context, :created_at)
        SQL);
        $statement->execute([
            'level' => $level,
            'event' => $event,
            'message' => substr($message, 0, 1000),
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'created_at' => gmdate('c'),
        ]);
    }
}
