<?php

class MenuSidebarService
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getViewData(array $input = []): array
    {
        $usuarioId = Auth::getUsuarioId();
        $empresaId = Auth::getEmpresaId();

        $requestPath = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

        $contas = $input['contas'] ?? [];
        $campanhas = $input['campanhas'] ?? [];
        $contaId = $input['contaId'] ?? ($_GET['conta_id'] ?? '');
        $campanhaId = $input['campanhaId'] ?? ($_GET['campanha_id'] ?? '');
        $campanhaStatus = $input['campanhaStatus'] ?? ($_GET['campanha_status'] ?? '');
        $dataInicio = $input['dataInicio'] ?? ($_GET['data_inicio'] ?? '');
        $dataFim = $input['dataFim'] ?? ($_GET['data_fim'] ?? '');
        $periodo = $input['periodo'] ?? ($_GET['periodo'] ?? '30');

        $relatoriosQuery = http_build_query(array_filter([
            'conta_id' => $contaId !== '' ? $contaId : null,
            'campanha_id' => $campanhaId !== '' ? $campanhaId : null,
            'campanha_status' => $campanhaStatus !== '' ? $campanhaStatus : null,
            'periodo' => $periodo !== '' ? $periodo : null,
            'data_inicio' => $dataInicio !== '' ? $dataInicio : null,
            'data_fim' => $dataFim !== '' ? $dataFim : null,
        ], static fn($value) => $value !== null && $value !== ''));

        return [
            'podeGerenciarUsuarios' => Permissao::podeGerenciarUsuarios($this->conn, $usuarioId, $empresaId),
            'podeGerenciarEmpresas' => method_exists('Permissao', 'podeGerenciarEmpresas')
                ? Permissao::podeGerenciarEmpresas($this->conn, $usuarioId, $empresaId)
                : false,
            'mostrarFiltros' => isset($input['mostrarFiltrosSidebar']) && $input['mostrarFiltrosSidebar'] === true,
            'contas' => $contas,
            'campanhas' => $campanhas,
            'contaId' => $contaId,
            'campanhaId' => $campanhaId,
            'campanhaStatus' => $campanhaStatus,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'periodo' => $periodo,
            'requestPath' => $requestPath,
            'relatoriosHref' => routeUrl('relatorios') . ($relatoriosQuery !== '' ? '?' . $relatoriosQuery : ''),
            'isConfiguracoesGroup' => alp_nav_active([
                'clientes',
                'contas',
                'integracoes_meta',
                'api',
                'conexoes',
                'personalizar',
                'usuarios',
                'empresas',
            ], $requestPath) === 'active',
            'isMonitoramentoGroup' => alp_nav_active([
                'sync_logs',
                'sync_dashboard',
                'sync_job_view',
            ], $requestPath) === 'active',
            'paginaLimparFiltros' => routeUrl('dashboard'),
            'usuarioNome' => trim(Auth::getUsuarioNome()),
            'usuarioEmail' => trim(Auth::getUsuarioEmail()),
            'usuarioFoto' => trim(Auth::getUsuarioFoto()),
        ];
    }
}
