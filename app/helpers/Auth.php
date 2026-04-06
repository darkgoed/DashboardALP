<?php

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $usuario, int $empresaId, ?string $perfil = null): void
    {
        self::start();

        session_regenerate_id(true);

        $_SESSION['logado'] = true;
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['empresa_id'] = $empresaId;
        $_SESSION['usuario_nome'] = $usuario['nome'] ?? null;
        $_SESSION['usuario_email'] = $usuario['email'] ?? null;
        $_SESSION['usuario_foto'] = $usuario['foto'] ?? null;
        $_SESSION['perfil'] = $perfil;
        $_SESSION['is_platform_root'] = !empty($usuario['usuario_is_root']) || !empty($usuario['is_root']);
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
            header('Location: ' . routeUrl('login'));
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

    public static function getUsuarioFoto(): string
    {
        self::start();

        return $_SESSION['usuario_foto'] ?? '';
    }

    public static function getPerfil(): ?string
    {
        self::start();

        return isset($_SESSION['perfil']) && $_SESSION['perfil'] !== ''
            ? (string) $_SESSION['perfil']
            : null;
    }

    public static function isPlatformRoot(): bool
    {
        self::start();
        return !empty($_SESSION['is_platform_root']) && $_SESSION['is_platform_root'] === true;
    }

    public static function isAdmin(): bool
    {
        self::start();
        return ($_SESSION['perfil'] ?? null) === 'admin';
    }

    public static function isOwner(): bool
    {
        self::start();
        return ($_SESSION['perfil'] ?? null) === 'owner';
    }

    public static function requireRoot(): void
    {
        if (!self::isPlatformRoot()) {
            header('Location: ' . routeUrl('dashboard'));
            exit;
        }
    }

    public static function logout(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
