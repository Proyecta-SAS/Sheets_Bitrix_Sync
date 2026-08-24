<?php

declare(strict_types=1);

namespace App\Bitrix;

use App\Support\SensitiveData;

final class BitrixClient implements BitrixGatewayInterface
{
    public function __construct(private readonly string $webhookUrl)
    {
    }

    public function testConnection(): array
    {
        $fields = $this->dealFields();

        return ['deal_fields' => count($fields)];
    }

    public function dealFields(): array
    {
        $result = $this->call('crm.deal.fields');

        return is_array($result) ? $result : [];
    }

    public function findDealByOrigin(string $originatorId, string $originId): ?string
    {
        $result = $this->call('crm.deal.list', [
            'filter' => [
                '=ORIGINATOR_ID' => $originatorId,
                '=ORIGIN_ID' => $originId,
            ],
            'select' => ['ID'],
            'start' => 0,
        ]);

        if (!is_array($result) || !isset($result[0]['ID'])) {
            return null;
        }

        return (string) $result[0]['ID'];
    }

    public function createDeal(array $fields): string
    {
        $result = $this->call('crm.deal.add', [
            'fields' => $fields,
            'params' => ['REGISTER_SONET_EVENT' => 'N'],
        ]);

        if (!is_int($result) && !is_string($result)) {
            throw new \RuntimeException('Bitrix24 no devolvió el ID de la negociación.');
        }

        return (string) $result;
    }

    public function createContact(array $fields): string
    {
        $result = $this->call('crm.contact.add', [
            'fields' => $fields,
            'params' => ['REGISTER_SONET_EVENT' => 'N'],
        ]);

        if (!is_int($result) && !is_string($result)) {
            throw new \RuntimeException('Bitrix24 no devolviÃ³ el ID del contacto.');
        }

        return (string) $result;
    }

    private function call(string $method, array $parameters = []): mixed
    {
        $base = rtrim(trim($this->webhookUrl), '/');
        if ($base === '') {
            throw new \RuntimeException('Configure BITRIX_WEBHOOK_URL en el archivo .env.');
        }
        if (!str_starts_with(strtolower($base), 'https://')) {
            throw new \RuntimeException('BITRIX_WEBHOOK_URL debe usar HTTPS.');
        }

        $url = $base . '/' . $method . '.json';
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('No fue posible iniciar la conexión con Bitrix24.');
        }

        $payload = json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Sheets-Bitrix-Sync/1.0',
        ]);

        $body = curl_exec($handle);
        $curlError = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false || $curlError !== '') {
            throw new \RuntimeException(SensitiveData::clean('Error de red al llamar a Bitrix24: ' . $curlError, [$this->webhookUrl]));
        }

        try {
            $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('Bitrix24 devolvió una respuesta no válida (HTTP ' . $status . ').');
        }

        if ($status < 200 || $status >= 300 || isset($decoded['error'])) {
            $description = (string) ($decoded['error_description'] ?? $decoded['error'] ?? 'Solicitud rechazada');
            throw new \RuntimeException(SensitiveData::clean('Bitrix24: ' . $description, [$this->webhookUrl]));
        }

        return $decoded['result'] ?? null;
    }
}
