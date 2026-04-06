<?php

class Flash
{
    private const SESSION_KEY = 'alp_flash';
    private const LEGACY_KEYS = [
        'success' => ['flash_success', 'sucesso'],
        'error' => ['flash_error', 'erro'],
        'warning' => ['flash_warning', 'aviso'],
        'info' => ['flash_info', 'info'],
    ];

    public static function add(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::SESSION_KEY][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    public static function getAll(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::consumeLegacyMessages();

        $messages = $_SESSION[self::SESSION_KEY] ?? [];

        unset($_SESSION[self::SESSION_KEY]);

        return $messages;
    }

    private static function consumeLegacyMessages(): void
    {
        foreach (self::LEGACY_KEYS as $type => $keys) {
            foreach ($keys as $key) {
                if (empty($_SESSION[$key])) {
                    continue;
                }

                $value = $_SESSION[$key];
                $messages = is_array($value) ? $value : [$value];

                foreach ($messages as $message) {
                    if (!is_string($message) || trim($message) === '') {
                        continue;
                    }

                    $_SESSION[self::SESSION_KEY][] = [
                        'type' => $type,
                        'message' => $message,
                    ];
                }

                unset($_SESSION[$key]);
            }
        }
    }

    public static function renderScript(): void
    {
        $messages = self::getAll();

        if (empty($messages)) {
            return;
        }

        echo '<script>';
        echo 'window.__ALP_FLASH__ = ' . json_encode($messages, JSON_UNESCAPED_UNICODE) . ';';
        echo '</script>';
    }
}
