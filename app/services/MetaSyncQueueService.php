<?php

require_once __DIR__ . '/../models/SyncJob.php';
require_once __DIR__ . '/MetaSyncService.php';

class MetaSyncQueueService
{
    private PDO $conn;
    private SyncJob $syncJobModel;
    private MetaSyncService $metaSyncService;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->syncJobModel = new SyncJob($conn);
        $this->metaSyncService = new MetaSyncService($conn);
    }

    public function getNextJob(): ?array
    {
        return $this->syncJobModel->getNextPending();
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
            $this->syncJobModel->markProcessing($jobId, $workerToken);

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

                default:
                    throw new Exception('Tipo de job inválido: ' . $tipo);
            }

            $this->syncJobModel->markDone(
                (int) $job['id'],
                $workerToken,
                'Processado com sucesso'
            );

            return [
                'job_id' => $jobId,
                'tipo' => $tipo,
                'status' => 'success',
                'resultado' => $resultado,
            ];
        } catch (Throwable $e) {
            $this->syncJobModel->markError(
                (int) $job['id'],
                $workerToken,
                $e->getMessage()
            );
            throw $e;
        }
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
