<?php

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $usuario, int $empresaId): void
    {
        self::start();

        session_regenerate_id(true);

        $_SESSION['logado'] = true;
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'] ?? '';
        $_SESSION['usuario_email'] = $usuario['email'] ?? '';
        $_SESSION['empresa_id'] = $empresaId;
    }

    public static function check(): bool
    {
        self::start();

        return !empty($_SESSION['logado'])
            && !empty($_SESSION['usuario_id'])
            && !empty($_SESSION['empresa_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function getUsuarioId(): ?int
    {
        self::start();

        return isset($_SESSION['usuario_id'])
            ? (int) $_SESSION['usuario_id']
            : null;
    }

    public static function getEmpresaId(): ?int
    {
        self::start();

        return isset($_SESSION['empresa_id'])
            ? (int) $_SESSION['empresa_id']
            : null;
    }

    public static function getUsuarioNome(): string
    {
        self::start();

        return $_SESSION['usuario_nome'] ?? '';
    }

    public static function getUsuarioEmail(): string
    {
        self::start();

        return $_SESSION['usuario_email'] ?? '';
    }

    public static function logout(): void
    {
        self::start();

        $_SESSION = [];
        session_destroy();
    }
}