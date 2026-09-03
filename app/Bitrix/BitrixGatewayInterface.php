<?php

declare(strict_types=1);

namespace App\Bitrix;

interface BitrixGatewayInterface
{
    public function testConnection(): array;

    public function dealFields(): array;

    public function findDealByOrigin(string $originatorId, string $originId): ?string;

    public function countDealsByFieldValue(string $field, string $value): int;

    public function createContact(array $fields): string;

    public function createDeal(array $fields): string;

    public function updateDeal(string $dealId, array $fields): void;
}
