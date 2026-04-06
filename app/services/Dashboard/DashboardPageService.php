<?php

class DashboardPageService
{
    private PDO $conn;
    private int $empresaId;
    private ContaAds $contaModel;
    private Campanha $campanhaModel;
    private MetricsService $metricsService;
    private MercadoPhoneService $mercadoPhoneService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->contaModel = new ContaAds($conn, $empresaId);
        $this->campanhaModel = new Campanha($conn, $empresaId);
        $this->metricsService = new MetricsService($conn);
        $this->mercadoPhoneService = new MercadoPhoneService($conn);
    }

    public function build(array $query): array
    {
        $contaId = isset($query['conta_id']) && $query['conta_id'] !== ''
            ? (int) $query['conta_id']
            : null;

        $campanhaId = isset($query['campanha_id']) && $query['campanha_id'] !== ''
            ? (int) $query['campanha_id']
            : null;

        $campanhaStatus = isset($query['campanha_status']) && $query['campanha_status'] !== ''
            ? strtoupper(trim((string) $query['campanha_status']))
            : '';

        $periodo = isset($query['periodo']) && $query['periodo'] !== ''
            ? (string) $query['periodo']
            : '90';

        $dataInicio = isset($query['data_inicio']) ? trim((string) $query['data_inicio']) : '';
        $dataFim = isset($query['data_fim']) ? trim((string) $query['data_fim']) : '';

        if (empty($contaId) && !empty($campanhaId)) {
            $campanhaSelecionada = $this->campanhaModel->getById($campanhaId);

            if ($campanhaSelecionada) {
                $contaId = (int) ($campanhaSelecionada['conta_id'] ?? 0) ?: null;
            }
        }

        $filters = [
            'empresa_id' => $this->empresaId,
            'conta_id' => $contaId,
            'campanha_id' => $campanhaId,
            'campanha_status' => $campanhaStatus,
            'periodo' => $periodo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];

        if (!empty($contaId) && !empty($campanhaId)) {
            $campanhasDaConta = $this->campanhaModel->getByConta($contaId);
            $campanhaValida = false;

            foreach ($campanhasDaConta as $camp) {
                if ((string) $camp['id'] === (string) $campanhaId) {
                    $campanhaValida = true;
                    break;
                }
            }

            if (!$campanhaValida) {
                $campanhaId = null;
            }
        }

        $filters['conta_id'] = $contaId;
        $filters['campanha_id'] = $campanhaId;

        $dashboard = $this->metricsService->getDashboardData($filters);
        $filtrosComparacao = $filters;

        $resumo = $dashboard['resumo'] ?? [];
        $contexto = $dashboard['contexto'] ?? [];
        $periodoResolvido = $dashboard['periodo'] ?? [
            'data_inicio' => date('Y-m-d'),
            'data_fim' => date('Y-m-d'),
        ];

        $dataInicioAtual = !empty($periodoResolvido['data_inicio']) ? $periodoResolvido['data_inicio'] : date('Y-m-d');
        $dataFimAtual = !empty($periodoResolvido['data_fim']) ? $periodoResolvido['data_fim'] : date('Y-m-d');

        $inicioTs = strtotime($dataInicioAtual);
        $fimTs = strtotime($dataFimAtual);

        $diasPeriodo = 1;
        if ($inicioTs && $fimTs && $fimTs >= $inicioTs) {
            $diasPeriodo = (int) floor(($fimTs - $inicioTs) / 86400) + 1;
        }

        $filtrosComparacao['periodo'] = 'custom';
        $filtrosComparacao['data_fim'] = date('Y-m-d', strtotime($dataInicioAtual . ' -1 day'));
        $filtrosComparacao['data_inicio'] = date('Y-m-d', strtotime($filtrosComparacao['data_fim'] . ' -' . ($diasPeriodo - 1) . ' days'));

        $mercadoPhoneResumo = $this->mercadoPhoneService->getResumoForContext(
            $this->empresaId,
            null,
            $contaId,
            $dataInicioAtual,
            $dataFimAtual,
            'dashboard'
        );

        $mercadoPhoneResumoAnterior = $this->mercadoPhoneService->getResumoForContext(
            $this->empresaId,
            null,
            $contaId,
            $filtrosComparacao['data_inicio'],
            $filtrosComparacao['data_fim'],
            'dashboard'
        );

        $dashboardAnterior = $this->metricsService->getDashboardData($filtrosComparacao);
        $resumoAnterior = $dashboardAnterior['resumo'] ?? [];

        $contas = $this->contaModel->getAll();
        $campanhas = !empty($contaId)
            ? $this->campanhaModel->getByConta($contaId)
            : [];

        $relatoriosQuery = http_build_query(array_filter([
            'conta_id' => $contaId ?: null,
            'campanha_id' => $campanhaId ?: null,
            'campanha_status' => $campanhaStatus !== '' ? $campanhaStatus : null,
            'periodo' => $periodo ?: null,
            'data_inicio' => $dataInicio ?: null,
            'data_fim' => $dataFim ?: null,
        ], static fn($value) => $value !== null && $value !== ''));

        return [
            'contaId' => $contaId,
            'campanhaId' => $campanhaId,
            'campanhaStatus' => $campanhaStatus,
            'periodo' => $periodo,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'filters' => $filters,
            'filtrosComparacao' => $filtrosComparacao,
            'dashboard' => $dashboard,
            'resumo' => $resumo,
            'contexto' => $contexto,
            'periodoResolvido' => $periodoResolvido,
            'dataInicioAtual' => $dataInicioAtual,
            'dataFimAtual' => $dataFimAtual,
            'mercadoPhoneResumo' => $mercadoPhoneResumo,
            'mercadoPhoneResumoAnterior' => $mercadoPhoneResumoAnterior,
            'dashboardAnterior' => $dashboardAnterior,
            'resumoAnterior' => $resumoAnterior,
            'contas' => $contas,
            'campanhas' => $campanhas,
            'relatoriosUrl' => routeUrl('relatorios') . ($relatoriosQuery !== '' ? '?' . $relatoriosQuery : ''),
        ];
    }
}
