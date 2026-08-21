<?php

declare(strict_types=1);

namespace App;

use App\Bitrix\BitrixClient;
use App\Config\IntegrationConfig;
use App\Google\SheetsGateway;
use App\Infrastructure\Database;
use App\Infrastructure\Logger;
use App\Infrastructure\ProcessedRowRepository;
use App\Infrastructure\RateLimiter;
use App\Infrastructure\SettingsRepository;
use App\Support\Env;
use App\Sync\SyncService;

final class Application
{
    public const VERSION = '1.0.0';

    private IntegrationConfig $config;

    private function __construct(
        public readonly string $root,
        public readonly Database $database,
        public readonly SettingsRepository $settings,
        public readonly ProcessedRowRepository $rows,
        public readonly Logger $logger,
        public readonly RateLimiter $rateLimiter,
        public readonly SheetsGateway $sheets,
        public readonly BitrixClient $bitrix,
        public readonly SyncService $sync,
    ) {
        $this->config = IntegrationConfig::from($settings);
    }

    public static function boot(string $root): self
    {
        $dbPath = self::absolutePath($root, Env::get('DB_PATH', 'storage/app.sqlite'));
        $database = new Database($dbPath);
        $settings = new SettingsRepository($database->pdo());
        $rows = new ProcessedRowRepository($database->pdo());
        $logger = new Logger($database->pdo());
        $rateLimiter = new RateLimiter($database->pdo());
        $credentialsPath = self::absolutePath($root, Env::get('GOOGLE_CREDENTIALS_PATH', 'storage/credentials/google-service-account.json'));
        $sheets = new SheetsGateway($credentialsPath);
        $bitrix = new BitrixClient(Env::get('BITRIX_WEBHOOK_URL'));
        $sync = new SyncService($sheets, $bitrix, $rows, $logger);

        return new self($root, $database, $settings, $rows, $logger, $rateLimiter, $sheets, $bitrix, $sync);
    }

    public function config(bool $refresh = false): IntegrationConfig
    {
        if ($refresh) {
            $this->config = IntegrationConfig::from($this->settings);
        }

        return $this->config;
    }

    private static function absolutePath(string $root, string $path): string
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
            return $path;
        }

        return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
