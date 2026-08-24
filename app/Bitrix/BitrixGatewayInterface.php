<?php

declare(strict_types=1);

namespace App\Bitrix;

interface BitrixGatewayInterface
{
    public function testConnection(): array;

    public function dealFields(): array;

    public function findDealByOrigin(string $originatorId, string $originId): ?string;

    public function createContact(array $fields): string;

    public function createDeal(array $fields): string;
}
