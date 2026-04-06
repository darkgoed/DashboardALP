<?php

class LicencaBloqueadaPageService
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getPageData(): array
    {
        $empresaId = null;

        if (class_exists('Tenant') && method_exists('Tenant', 'getEmpresaId')) {
            $empresaId = Tenant::getEmpresaId();
        }

        if (!$empresaId && method_exists('Auth', 'getEmpresaId')) {
            $empresaId = Auth::getEmpresaId();
        }

        $empresaModel = new Empresa($this->conn);
        $empresa = $empresaId ? $empresaModel->findById((int) $empresaId) : null;

        $dadosBloqueio = $_SESSION['licenca_bloqueada'] ?? [];

        return [
            'empresa' => $empresa,
            'motivo' => $dadosBloqueio['motivo'] ?? 'Sua licenca expirou.',
            'status_assinatura' => $dadosBloqueio['status_assinatura'] ?? 'bloqueada',
            'data_vencimento' => $dadosBloqueio['data_vencimento'] ?? null,
            'data_limite_tolerancia' => $dadosBloqueio['data_limite_tolerancia'] ?? null,
        ];
    }

    public static function formatDate(?string $data): string
    {
        if (empty($data)) {
            return '-';
        }

        try {
            return (new DateTime($data))->format('d/m/Y H:i');
        } catch (Throwable $e) {
            return '-';
        }
    }
}
