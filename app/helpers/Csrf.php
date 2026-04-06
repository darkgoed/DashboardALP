<?php

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function getRequestToken(): string
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        return is_string($token) ? trim($token) : '';
    }

    public static function isValid(?string $token = null): bool
    {
        $sessionToken = self::token();
        $requestToken = $token ?? self::getRequestToken();

        if ($requestToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }
}
