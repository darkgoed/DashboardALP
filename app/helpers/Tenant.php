<?php

class Tenant
{
    public static function requireEmpresa(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['empresa_id'])) {
            throw new RuntimeException('Empresa não definida na sessão.');
        }
    }

    public static function getEmpresaId(): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['empresa_id'])) {
            throw new RuntimeException('Empresa não definida na sessão.');
        }

        return (int) $_SESSION['empresa_id'];
    }
}
