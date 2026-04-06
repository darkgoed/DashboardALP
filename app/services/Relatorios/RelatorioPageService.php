<?php

class RelatorioPageService
{
    private int $empresaId;
    private Cliente $clienteModel;
    private ContaAds $contaModel;
    private Campanha $campanhaModel;
    private EnvioRelatorio $envioRelatorioModel;
    private RelatorioProgramacaoService $programacaoService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->empresaId = $empresaId;
        $this->clienteModel = new Cliente($conn, $empresaId);
        $this->contaModel = new ContaAds($conn, $empresaId);
        $this->campanhaModel = new Campanha($conn, $empresaId);
        $this->envioRelatorioModel = new EnvioRelatorio($conn);
        $this->programacaoService = new RelatorioProgramacaoService($conn, $empresaId);
    }

    public function build(array $query, string $destinoPadrao): array
    {
        $clientes = $this->clienteModel->getAll();

        $clienteId = isset($query['cliente_id']) && $query['cliente_id'] !== ''
            ? (int) $query['cliente_id']
            : 0;

        $contaId = isset($query['conta_id']) && $query['conta_id'] !== ''
            ? (int) $query['conta_id']
            : 0;

        $campanhaId = isset($query['campanha_id']) && $query['campanha_id'] !== ''
            ? (int) $query['campanha_id']
            : 0;

        $campanhaStatus = isset($query['campanha_status']) && $query['campanha_status'] !== ''
            ? strtoupper(trim((string) $query['campanha_status']))
            : '';

        $periodo = isset($query['periodo']) && $query['periodo'] !== ''
            ? trim((string) $query['periodo'])
            : '30';

        $dataInicio = isset($query['data_inicio']) && $query['data_inicio'] !== ''
            ? (string) $query['data_inicio']
            : date('Y-m-d', strtotime('-30 days'));

        $dataFim = isset($query['data_fim']) && $query['data_fim'] !== ''
            ? (string) $query['data_fim']
            : date('Y-m-d', strtotime('-1 day'));

        $periodosPermitidos = ['1', '3', '7', '14', '15', '30', '90', '365', 'custom'];
        if (!in_array($periodo, $periodosPermitidos, true)) {
            $periodo = '30';
        }

        if ($periodo !== 'custom') {
            $dias = (int) $periodo;

            if ($dias > 0) {
                $dataFim = date('Y-m-d', strtotime('-1 day'));
                $dataInicio = date('Y-m-d', strtotime($dataFim . ' -' . ($dias - 1) . ' days'));
            }
        }

        if (!$clienteId && $contaId) {
            $contaAtual = $this->contaModel->getById($contaId);
            if ($contaAtual && isset($contaAtual['cliente_id'])) {
                $clienteId = (int) $contaAtual['cliente_id'];
            }
        }

        if (!$contaId && $campanhaId) {
            $campanhaAtual = $this->campanhaModel->getById($campanhaId);
            if ($campanhaAtual && isset($campanhaAtual['conta_id'])) {
                $contaId = (int) $campanhaAtual['conta_id'];
            }
        }

        if (!$clienteId && $contaId) {
            $contaAtual = $this->contaModel->getById($contaId);
            if ($contaAtual && isset($contaAtual['cliente_id'])) {
                $clienteId = (int) $contaAtual['cliente_id'];
            }
        }

        $contas = $clienteId > 0
            ? $this->contaModel->getByCliente($clienteId)
            : [];

        $campanhas = $contaId > 0
            ? $this->campanhaModel->getByConta($contaId)
            : [];

        $contasTodas = $this->contaModel->getAll();
        $campanhasTodas = $this->campanhaModel->getAll();

        if ($campanhaId > 0) {
            $campanhaValida = false;

            foreach ($campanhas as $campanhaItem) {
                if ((int) ($campanhaItem['id'] ?? 0) === $campanhaId) {
                    $campanhaValida = true;
                    break;
                }
            }

            if (!$campanhaValida) {
                $campanhaId = 0;
            }
        }

        $queryData = [
            'cliente_id' => $clienteId ?: '',
            'conta_id' => $contaId ?: '',
            'campanha_id' => $campanhaId ?: '',
            'campanha_status' => $campanhaStatus,
            'periodo' => $periodo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];

        $queryString = http_build_query($queryData);

        return [
            'clientes' => $clientes,
            'contas' => $contas,
            'campanhas' => $campanhas,
            'contas_todas' => $contasTodas,
            'campanhas_todas' => $campanhasTodas,
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'campanha_id' => $campanhaId,
            'campanha_status' => $campanhaStatus,
            'periodo' => $periodo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'preview_url' => routeUrl('relatorio_view') . '?' . $queryString,
            'print_url' => routeUrl('relatorio_view') . '?' . $queryString . '&print=1',
            'envios_recentes' => $this->envioRelatorioModel->latestByEmpresa($this->empresaId, 5),
            'programacoes' => $this->programacaoService->listForPage(),
            'destino_padrao' => $destinoPadrao,
            'query_data' => $queryData,
        ];
    }
}
