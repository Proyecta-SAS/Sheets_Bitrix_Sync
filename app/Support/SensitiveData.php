<?php

declare(strict_types=1);

namespace App\Support;

final class SensitiveData
{
    public static function clean(string $message, array $secrets = []): string
    {
        foreach ($secrets as $secret) {
            if (is_string($secret) && strlen($secret) >= 6) {
                $message = str_replace($secret, '[OCULTO]', $message);
            }
        }

        $message = preg_replace('~(/rest/\d+/)[^/\s]+~i', '$1[OCULTO]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~-]+/i', 'Bearer [OCULTO]', $message) ?? $message;

        return substr($message, 0, 1000);
    }
}
