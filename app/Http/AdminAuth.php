<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Env;

final class AdminAuth
{
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        session_name('sheets_bitrix_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public function isConfigured(): bool
    {
        return Env::get('ADMIN_USER') !== '' && Env::get('ADMIN_PASSWORD_HASH') !== '';
    }

    public function check(): bool
    {
        return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
    }

    public function attempt(string $user, string $password): bool
    {
        $validUser = hash_equals(Env::get('ADMIN_USER'), $user);
        $validPassword = password_verify($password, Env::get('ADMIN_PASSWORD_HASH'));

        if (!$validUser || !$validPassword) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_user'] = $user;

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
