<?php

class SyncJobActionService
{
    private PDO $conn;
    private int $empresaId;
    private SyncJob $syncJob;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->syncJob = new SyncJob($conn);
    }

    public function requeue(int $jobId): string
    {
        if ($jobId <= 0) {
            throw new RuntimeException('Job inválido para reprocessamento.');
        }

        $job = $this->syncJob->findById($jobId);

        if (!$job) {
            throw new RuntimeException('Job não encontrado.');
        }

        if ((int) $job['empresa_id'] !== $this->empresaId) {
            throw new RuntimeException('Você não tem permissão para reprocessar este job.');
        }

        if (empty($job['tipo'])) {
            throw new RuntimeException('Tipo do job não identificado.');
        }

        $novoJobId = $this->syncJob->enqueueIfNotExists([
            'empresa_id' => (int) $job['empresa_id'],
            'cliente_id' => !empty($job['cliente_id']) ? (int) $job['cliente_id'] : null,
            'conta_id' => !empty($job['conta_id']) ? (int) $job['conta_id'] : null,
            'tipo' => (string) $job['tipo'],
            'origem' => 'manual',
            'prioridade' => 2,
            'force_sync' => 1,
            'janela_inicio' => $job['janela_inicio'] ?? null,
            'janela_fim' => $job['janela_fim'] ?? null,
            'parametros_json' => $job['parametros_json'] ?? null,
            'mensagem' => 'Reprocessado manualmente a partir do job #' . (int) $job['id'],
        ]);

        if ($novoJobId) {
            $this->syncJob->markRequeued((int) $job['id'], (int) $novoJobId);
            return 'Job reenfileirado com sucesso.';
        }

        return 'Já existe um job semelhante pendente ou processando.';
    }
}
