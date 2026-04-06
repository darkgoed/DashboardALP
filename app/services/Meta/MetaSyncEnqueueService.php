<?php

require_once dirname(__DIR__, 2) . '/models/SyncJob.php';

class MetaSyncEnqueueService
{
    private PDO $conn;
    private SyncJob $syncJobModel;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->syncJobModel = new SyncJob($conn);
    }

    public function enqueueSyncNow(int $empresaId, int $contaId, int $diasInsights = 7): array
    {
        $conta = $this->buscarConta($empresaId, $contaId);

        if (!$conta) {
            throw new Exception('Conta de anúncio não encontrada.');
        }

        if (empty($conta['meta_account_id'])) {
            throw new Exception('A conta não está vinculada à Meta.');
        }

        if ((int) ($conta['ativo'] ?? 0) !== 1) {
            throw new Exception('A conta está inativa.');
        }

        $inicio = date('Y-m-d', strtotime('-' . max(1, $diasInsights) . ' days'));
        $fim    = date('Y-m-d');

        $jobEstrutura = $this->syncJobModel->enqueueIfNotExists([
            'empresa_id' => $empresaId,
            'cliente_id' => (int) $conta['cliente_id'],
            'conta_id'   => $contaId,
            'tipo'       => 'estrutura',
            'origem'     => 'manual',
            'prioridade' => 3,
            'force_sync' => 1,
            'mensagem'   => 'Sync manual solicitada pelo usuário.',
        ]);

        $jobInsights = $this->syncJobModel->enqueueIfNotExists([
            'empresa_id'    => $empresaId,
            'cliente_id'    => (int) $conta['cliente_id'],
            'conta_id'      => $contaId,
            'tipo'          => 'insights',
            'origem'        => 'manual',
            'prioridade'    => 4,
            'force_sync'    => 1,
            'janela_inicio' => $inicio,
            'janela_fim'    => $fim,
            'mensagem'      => 'Insights manuais solicitados pelo usuário.',
        ]);

        return [
            'conta_id'         => $contaId,
            'meta_account_id'  => $conta['meta_account_id'],
            'job_estrutura'    => $jobEstrutura,
            'job_insights'     => $jobInsights,
            'janela_inicio'    => $inicio,
            'janela_fim'       => $fim,
        ];
    }

    public function enqueueReprocessar(int $empresaId, int $contaId, int $dias = 7): array
    {
        $conta = $this->buscarConta($empresaId, $contaId);

        if (!$conta) {
            throw new Exception('Conta de anúncio não encontrada.');
        }

        if (empty($conta['meta_account_id'])) {
            throw new Exception('A conta não está vinculada à Meta.');
        }

        if ((int) ($conta['ativo'] ?? 0) !== 1) {
            throw new Exception('A conta está inativa.');
        }

        $inicio = date('Y-m-d', strtotime('-' . max(1, $dias) . ' days'));
        $fim    = date('Y-m-d');

        $jobRecon = $this->syncJobModel->enqueueIfNotExists([
            'empresa_id'    => $empresaId,
            'cliente_id'    => (int) $conta['cliente_id'],
            'conta_id'      => $contaId,
            'tipo'          => 'reconciliacao',
            'origem'        => 'manual',
            'prioridade'    => 2,
            'force_sync'    => 1,
            'janela_inicio' => $inicio,
            'janela_fim'    => $fim,
            'mensagem'      => 'Reprocessamento manual solicitado pelo usuário.',
        ]);

        return [
            'conta_id'            => $contaId,
            'meta_account_id'     => $conta['meta_account_id'],
            'job_reconciliacao'   => $jobRecon,
            'janela_inicio'       => $inicio,
            'janela_fim'          => $fim,
        ];
    }

    public function enqueueFullSync(int $empresaId, int $contaId, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $conta = $this->buscarConta($empresaId, $contaId);

        if (!$conta) {
            throw new Exception('Conta de anúncio não encontrada.');
        }

        if (empty($conta['meta_account_id'])) {
            throw new Exception('A conta não está vinculada à Meta.');
        }

        if ((int) ($conta['ativo'] ?? 0) !== 1) {
            throw new Exception('A conta está inativa.');
        }

        $fimObj = new DateTimeImmutable($dataFim ?: date('Y-m-d'));
        $inicioLimiteMeta = $fimObj->modify('-37 months +1 day');
        $inicioObj = new DateTimeImmutable($dataInicio ?: $inicioLimiteMeta->format('Y-m-d'));

        if ($inicioObj < $inicioLimiteMeta) {
            $inicioObj = $inicioLimiteMeta;
        }

        if ($inicioObj > $fimObj) {
            $inicioObj = $inicioLimiteMeta;
        }

        $inicio = $inicioObj->format('Y-m-d');
        $fim = $fimObj->format('Y-m-d');

        $jobEstrutura = $this->syncJobModel->enqueueIfNotExists([
            'empresa_id'    => $empresaId,
            'cliente_id'    => (int) $conta['cliente_id'],
            'conta_id'      => $contaId,
            'tipo'          => 'estrutura',
            'origem'        => 'manual',
            'prioridade'    => 1,
            'force_sync'    => 1,
            'mensagem'      => 'Full sync manual solicitada pelo usuario: estrutura.',
        ]);

        $jobsInsights = [];
        $prioridade = 2;

        foreach ($this->buildFullSyncWindows($inicioObj, $fimObj) as [$janelaInicio, $janelaFim]) {
            $jobsInsights[] = $this->syncJobModel->enqueueIfNotExists([
                'empresa_id'    => $empresaId,
                'cliente_id'    => (int) $conta['cliente_id'],
                'conta_id'      => $contaId,
                'tipo'          => 'insights',
                'origem'        => 'manual',
                'prioridade'    => $prioridade++,
                'force_sync'    => 1,
                'janela_inicio' => $janelaInicio,
                'janela_fim'    => $janelaFim,
                'mensagem'      => 'Full sync manual solicitada pelo usuario: insights em lote.',
            ]);
        }

        return [
            'conta_id'        => $contaId,
            'meta_account_id' => $conta['meta_account_id'],
            'job_estrutura'   => $jobEstrutura,
            'jobs_insights'   => array_values(array_filter($jobsInsights)),
            'total_jobs_insights' => count(array_values(array_filter($jobsInsights))),
            'janela_inicio'   => $inicio,
            'janela_fim'      => $fim,
            'janela_limitada_meta' => true,
        ];
    }

    private function buildFullSyncWindows(DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        $windows = [];
        $cursor = $inicio;

        while ($cursor <= $fim) {
            $windowEnd = $cursor->modify('+29 days');

            if ($windowEnd > $fim) {
                $windowEnd = $fim;
            }

            $windows[] = [
                $cursor->format('Y-m-d'),
                $windowEnd->format('Y-m-d'),
            ];

            $cursor = $windowEnd->modify('+1 day');
        }

        return $windows;
    }

    private function buscarConta(int $empresaId, int $contaId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, empresa_id, cliente_id, meta_account_id, ativo
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND id = :conta_id
            LIMIT 1
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
