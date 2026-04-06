<?php

require_once dirname(__DIR__, 2) . '/models/SyncJob.php';
require_once __DIR__ . '/MetaSyncService.php';

class MetaSyncQueueService
{
    private PDO $conn;
    private SyncJob $syncJobModel;
    private MetaSyncService $metaSyncService;
    private MercadoPhoneService $mercadoPhoneService;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->syncJobModel = new SyncJob($conn);
        $this->metaSyncService = new MetaSyncService($conn);
        $this->mercadoPhoneService = new MercadoPhoneService($conn);
    }

    public function getNextJob(): ?array
    {
        return $this->syncJobModel->getNextPending();
    }

    public function claimNextJob(string $workerToken, ?string $tipo = null): ?array
    {
        return $this->syncJobModel->claimNextPending($workerToken, $tipo);
    }

    public function processJob(array $job, string $workerToken): array
    {
        $jobId      = (int) $job['id'];
        $empresaId  = (int) $job['empresa_id'];
        $contaId    = !empty($job['conta_id']) ? (int) $job['conta_id'] : null;
        $tipo       = (string) ($job['tipo'] ?? '');
        $params     = $this->parseParametros($job['parametros_json'] ?? null);
        $dataInicio = $job['janela_inicio'] ?: ($params['data_inicio'] ?? null);
        $dataFim    = $job['janela_fim'] ?: ($params['data_fim'] ?? null);

        try {
            $jobStatus = (string) ($job['status'] ?? '');
            $jobWorkerToken = (string) ($job['worker_token'] ?? '');

            if ($jobStatus === 'pendente') {
                $this->syncJobModel->markProcessing($jobId, $workerToken);
            } elseif ($jobStatus !== 'processando' || $jobWorkerToken !== $workerToken) {
                throw new Exception('Job nao esta travado por este worker.');
            }

            $context = [
                'sync_job_id' => $jobId,
                'force_sync'  => !empty($job['force_sync']),
                'origem'      => $job['origem'] ?? 'cron',
                'parametros'  => $params,
            ];

            switch ($tipo) {
                case 'estrutura':
                    $resultado = $this->metaSyncService->syncEstrutura($empresaId, $contaId, $context);
                    break;

                case 'insights':
                    $resultado = $this->metaSyncService->syncInsights($empresaId, $contaId, $dataInicio, $dataFim, $context);
                    break;

                case 'completo':
                    $resultado = $this->metaSyncService->syncCompleto($empresaId, $contaId, $dataInicio, $dataFim, $context);
                    break;

                case 'reconciliacao':
                    $resultado = $this->metaSyncService->syncReconciliacao($empresaId, $contaId, $dataInicio, $dataFim, $context);
                    break;

                case 'manutencao':
                    $resultado = $this->metaSyncService->syncManutencao($context);
                    break;

                case 'mercado_phone':
                    $integracaoId = !empty($params['integracao_id']) ? (int) $params['integracao_id'] : 0;

                    if ($integracaoId <= 0) {
                        throw new Exception('Integracao nao informada para job Mercado Phone.');
                    }

                    $resultado = $this->mercadoPhoneService->syncNow(
                        $empresaId,
                        $integracaoId,
                        !empty($params['force_full'])
                    );
                    break;

                default:
                    throw new Exception('Tipo de job inválido: ' . $tipo);
            }

            if ($this->resultadoTemErro($resultado)) {
                $mensagem = 'Job processado com erro interno: ' . json_encode($resultado, JSON_UNESCAPED_UNICODE);

                $this->syncJobModel->markFailure($jobId, $workerToken, $mensagem);

                return [
                    'job_id'    => $jobId,
                    'tipo'      => $tipo,
                    'status'    => 'error',
                    'resultado' => $resultado,
                    'message'   => $mensagem,
                ];
            }

            $mensagem = 'Processado com sucesso: ' . json_encode($resultado, JSON_UNESCAPED_UNICODE);

            $this->syncJobModel->markDone($jobId, $workerToken, $mensagem);

            return [
                'job_id'    => $jobId,
                'tipo'      => $tipo,
                'status'    => 'success',
                'resultado' => $resultado,
            ];
        } catch (Throwable $e) {
            $this->syncJobModel->markFailure($jobId, $workerToken, $e->getMessage());
            throw $e;
        }
    }

    private function resultadoTemErro(mixed $resultado): bool
    {
        if (!is_array($resultado)) {
            return false;
        }

        foreach ($resultado as $item) {
            if (is_array($item) && (($item['status'] ?? null) === 'error')) {
                return true;
            }

            if (is_array($item)) {
                foreach ($item as $subItem) {
                    if (is_array($subItem) && (($subItem['status'] ?? null) === 'error')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function parseParametros(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
