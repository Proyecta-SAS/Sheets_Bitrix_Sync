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

        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO rate_limits (bucket_key, window_started_at, hits)
            VALUES (:bucket_key, :window_started_at, 1)
            ON CONFLICT(bucket_key, window_started_at) DO UPDATE SET hits = hits + 1
            RETURNING hits
        SQL);
        $statement->execute(['bucket_key' => $bucket, 'window_started_at' => $window]);
        $hits = (int) $statement->fetchColumn();

        if (random_int(1, 100) === 1) {
            $cleanup = $this->pdo->prepare('DELETE FROM rate_limits WHERE window_started_at < :cutoff');
            $cleanup->execute(['cutoff' => $now - 86400]);
        }

        return $hits <= $limit;
    }
}
