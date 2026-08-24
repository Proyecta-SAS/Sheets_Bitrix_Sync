<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

final class RateLimiter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        $now = time();
        $window = $now - ($now % $windowSeconds);
        $bucket = hash('sha256', $key);

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT OR IGNORE INTO rate_limits (bucket_key, window_started_at, hits)
                VALUES (:bucket_key, :window_started_at, 0)
            SQL);
            $insert->execute(['bucket_key' => $bucket, 'window_started_at' => $window]);

            $update = $this->pdo->prepare(<<<'SQL'
                UPDATE rate_limits
                SET hits = hits + 1
                WHERE bucket_key = :bucket_key AND window_started_at = :window_started_at
            SQL);
            $update->execute(['bucket_key' => $bucket, 'window_started_at' => $window]);

            $statement = $this->pdo->prepare(<<<'SQL'
                SELECT hits
                FROM rate_limits
                WHERE bucket_key = :bucket_key AND window_started_at = :window_started_at
            SQL);
            $statement->execute(['bucket_key' => $bucket, 'window_started_at' => $window]);
            $hits = (int) $statement->fetchColumn();
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        if (random_int(1, 100) === 1) {
            $cleanup = $this->pdo->prepare('DELETE FROM rate_limits WHERE window_started_at < :cutoff');
            $cleanup->execute(['cutoff' => $now - 86400]);
        }

        return $hits <= $limit;
    }
}
