<?php

class EmpresaAccessGuard
{
    public static function check(PDO $conn): array
    {
        if (method_exists('Auth', 'isPlatformRoot') && Auth::isPlatformRoot()) {
            return [
                'ok' => true,
                'status_acesso' => 'liberada',
                'status_assinatura' => 'ativa',
                'motivo' => null,
                'em_tolerancia' => false,
                'bloqueada' => false,
            ];
        }

        $empresaId = null;

        if (class_exists('Tenant') && method_exists('Tenant', 'getEmpresaId')) {
            $empresaId = Tenant::getEmpresaId();
        }

        if (!$empresaId && class_exists('Auth') && method_exists('Auth', 'getEmpresaId')) {
            $empresaId = Auth::getEmpresaId();
        }

        if (!$empresaId) {
            throw new RuntimeException('Empresa da sessão não identificada.');
        }

        $licencaService = new EmpresaLicencaService($conn);
        $status = $licencaService->sincronizarStatus((int) $empresaId);

        if (($status['status_acesso'] ?? 'bloqueada') === 'bloqueada') {
            self::redirectToBlocked($status);
        }

        return [
            'ok' => true,
            'status_acesso' => $status['status_acesso'] ?? 'liberada',
            'status_assinatura' => $status['status_assinatura'] ?? 'ativa',
            'motivo' => $status['motivo'] ?? null,
            'em_tolerancia' => (bool) ($status['em_tolerancia'] ?? false),
            'bloqueada' => (bool) ($status['bloqueada'] ?? false),
            'dias_restantes' => $status['dias_restantes'] ?? null,
            'data_vencimento' => $status['data_vencimento'] ?? null,
            'data_limite_tolerancia' => $status['data_limite_tolerancia'] ?? null,
        ];
    }

    public static function assertPodeOperar(PDO $conn): void
    {
        self::check($conn);
    }

    private static function redirectToBlocked(array $status): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['licenca_bloqueada'] = [
            'motivo' => $status['motivo'] ?? 'Sua licença expirou.',
            'status_assinatura' => $status['status_assinatura'] ?? 'bloqueada',
            'data_vencimento' => $status['data_vencimento'] ?? null,
            'data_limite_tolerancia' => $status['data_limite_tolerancia'] ?? null,
        ];

        header('Location: ' . routeUrl('licenca/bloqueada'));
        exit;
    }
}
