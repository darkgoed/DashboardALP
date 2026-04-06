<?php

class RelatorioViewPageService
{
    private PDO $conn;
    private RelatorioPublicLinkService $publicLinkService;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->publicLinkService = new RelatorioPublicLinkService();
    }

    public function build(array $query, bool $isAuthenticatedView): array
    {
        $publicToken = trim((string) ($query['token'] ?? ''));
        $isPublicView = $publicToken !== '';

        if ($isPublicView) {
            $tokenValidation = $this->publicLinkService->validateToken($publicToken);

            if (empty($tokenValidation['ok'])) {
                throw new RuntimeException('Link do relatório inválido ou expirado.');
            }

            $empresaId = (int) ($tokenValidation['empresa_id'] ?? 0);
            $requestData = (array) ($tokenValidation['query'] ?? []);
        } else {
            if (!$isAuthenticatedView) {
                throw new RuntimeException('Acesso não autorizado ao relatório.');
            }

            $empresaId = (int) Auth::getEmpresaId();
            $requestData = $query;
        }

        $clienteModel = new Cliente($this->conn, $empresaId);
        $contaModel = new ContaAds($this->conn, $empresaId);
        $campanhaModel = new Campanha($this->conn, $empresaId);
        $relatorioService = new RelatorioService($this->conn, $empresaId);
        $mercadoPhoneService = new MercadoPhoneService($this->conn);
        $dashboardMetaSummaryService = new DashboardMetaSummaryService($this->conn);

        $clienteId = isset($requestData['cliente_id']) && $requestData['cliente_id'] !== '' ? (int) $requestData['cliente_id'] : 0;
        $contaId = isset($requestData['conta_id']) && $requestData['conta_id'] !== '' ? (int) $requestData['conta_id'] : 0;
        $campanhaId = isset($requestData['campanha_id']) && $requestData['campanha_id'] !== '' ? (int) $requestData['campanha_id'] : 0;
        $campanhaStatus = isset($requestData['campanha_status']) && $requestData['campanha_status'] !== ''
            ? strtoupper(trim((string) $requestData['campanha_status']))
            : '';
        $periodo = (string) ($requestData['periodo'] ?? '30');
        $dataInicio = (string) ($requestData['data_inicio'] ?? date('Y-m-d', strtotime('-29 days')));
        $dataFim = (string) ($requestData['data_fim'] ?? date('Y-m-d'));
        $printMode = isset($query['print']) && $query['print'] == '1';

        $cliente = $clienteId > 0 ? $clienteModel->getById($clienteId) : null;
        $conta = $contaId > 0 ? $contaModel->getById($contaId) : null;
        $campanha = $campanhaId > 0 ? $campanhaModel->getById($campanhaId) : null;

        $inicioTs = strtotime($dataInicio);
        $fimTs = strtotime($dataFim);

        $diasPeriodo = 1;
        if ($inicioTs && $fimTs && $fimTs >= $inicioTs) {
            $diasPeriodo = (int) floor(($fimTs - $inicioTs) / 86400) + 1;
        }

        $dataFimAnterior = date('Y-m-d', strtotime($dataInicio . ' -1 day'));
        $dataInicioAnterior = date('Y-m-d', strtotime($dataFimAnterior . ' -' . ($diasPeriodo - 1) . ' days'));

        $mercadoPhoneResumo = $mercadoPhoneService->getResumoForContext(
            $empresaId,
            $clienteId ?: null,
            $contaId ?: null,
            $dataInicio,
            $dataFim,
            'relatorios'
        );

        $mercadoPhoneResumoAnterior = $mercadoPhoneService->getResumoForContext(
            $empresaId,
            $clienteId ?: null,
            $contaId ?: null,
            $dataInicioAnterior,
            $dataFimAnterior,
            'relatorios'
        );

        $resumo = $relatorioService->getResumoGeral($contaId ?: null, $campanhaId ?: null, $dataInicio, $dataFim, $campanhaStatus ?: null);
        $resumoAnterior = $relatorioService->getResumoGeral($contaId ?: null, $campanhaId ?: null, $dataInicioAnterior, $dataFimAnterior, $campanhaStatus ?: null);

        $resumoMetaAtual = $dashboardMetaSummaryService->loadMetaPeriodSummary(
            $empresaId,
            $contaId ?: null,
            $campanhaId ?: null,
            $dataInicio,
            $dataFim,
            $campanhaStatus ?: null
        );
        $dashboardMetaSummaryService->applyMetaSummary($resumo, $resumoMetaAtual);

        $resumoMetaAnterior = $dashboardMetaSummaryService->loadMetaPeriodSummary(
            $empresaId,
            $contaId ?: null,
            $campanhaId ?: null,
            $dataInicioAnterior,
            $dataFimAnterior,
            $campanhaStatus ?: null
        );
        $dashboardMetaSummaryService->applyMetaSummary($resumoAnterior, $resumoMetaAnterior);

        return [
            'is_public_view' => $isPublicView,
            'empresa_id' => $empresaId,
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'campanha_id' => $campanhaId,
            'campanha_status' => $campanhaStatus,
            'periodo' => $periodo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'data_inicio_anterior' => $dataInicioAnterior,
            'data_fim_anterior' => $dataFimAnterior,
            'print_mode' => $printMode,
            'cliente' => $cliente,
            'conta' => $conta,
            'campanha' => $campanha,
            'mercado_phone_resumo' => $mercadoPhoneResumo,
            'mercado_phone_resumo_anterior' => $mercadoPhoneResumoAnterior,
            'resumo' => $resumo,
            'resumo_anterior' => $resumoAnterior,
            'serie' => $relatorioService->getSerieTemporal($contaId ?: null, $campanhaId ?: null, $dataInicio, $dataFim, $campanhaStatus ?: null),
            'campanhas' => $relatorioService->getCampanhasRelatorio($contaId ?: null, $campanhaId ?: null, $dataInicio, $dataFim, $campanhaStatus ?: null),
            'config_metricas_json' => $dashboardMetaSummaryService->loadMetricConfig($contaId ?: null),
        ];
    }
}
