<?php

require_once dirname(__DIR__, 2) . '/models/SyncJob.php';

class MercadoPhoneQueueService
{
    private PDO $conn;
    private SyncJob $syncJobModel;
    private MercadoPhoneService $mercadoPhoneService;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->syncJobModel = new SyncJob($conn);
        $this->mercadoPhoneService = new MercadoPhoneService($conn);
        $this->ensureSyncJobsTipoSupportsMercadoPhone();
    }

    public function enqueueManualSync(int $empresaId, int $integracaoId, bool $forceFull = false): array
    {
        $config = $this->mercadoPhoneService->getConfigById($empresaId, $integracaoId);

        if (!$config || empty($config['ativo'])) {
            throw new Exception('Mercado Phone inativo para esta integracao.');
        }

        if (trim((string) ($config['api_token'] ?? '')) === '') {
            throw new Exception('Mercado Phone sem token configurado para esta integracao.');
        }

        $clienteId = (int) ($config['cliente_id'] ?? 0);
        $contaId = !empty($config['conta_id']) ? (int) $config['conta_id'] : null;

        $jobId = $this->syncJobModel->enqueueIfNotExists([
            'empresa_id' => $empresaId,
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'tipo' => 'mercado_phone',
            'origem' => 'manual',
            'prioridade' => $forceFull ? 2 : 4,
            'force_sync' => 0,
            'parametros_json' => [
                'force_full' => $forceFull,
                'integracao_id' => $integracaoId,
                'cliente_id' => $clienteId,
                'conta_id' => $contaId,
            ],
            'mensagem' => $forceFull
                ? 'Full sync do Mercado Phone solicitada pelo usuario para a integracao selecionada.'
                : 'Sync incremental do Mercado Phone solicitada pelo usuario para a integracao selecionada.',
        ]);

        return [
            'integracao_id' => $integracaoId,
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'job_id' => $jobId,
            'modo' => $forceFull ? 'full' : 'incremental',
            'already_pending' => $jobId === null,
        ];
    }

    public function enqueueActiveIntegrations(?int $empresaId = null, int $limit = 100): array
    {
        $this->mercadoPhoneService->ensureSchema();

        $sql = "
            SELECT id, empresa_id, cliente_id, conta_id
            FROM mercado_phone_integracoes
            WHERE ativo = 1
              AND api_token IS NOT NULL
              AND TRIM(api_token) <> ''
        ";

        $params = [];

        if ($empresaId !== null) {
            $sql .= " AND empresa_id = :empresa_id";
            $params[':empresa_id'] = $empresaId;
        }

        $sql .= " ORDER BY empresa_id ASC, cliente_id ASC LIMIT :limit";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();

        $integracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $enfileirados = 0;
        $ignorados = 0;
        $jobIds = [];

        foreach ($integracoes as $row) {
            $jobId = $this->syncJobModel->enqueueIfNotExists([
                'empresa_id' => (int) $row['empresa_id'],
                'cliente_id' => (int) $row['cliente_id'],
                'conta_id' => !empty($row['conta_id']) ? (int) $row['conta_id'] : null,
                'tipo' => 'mercado_phone',
                'origem' => 'cron',
                'prioridade' => 6,
                'force_sync' => 0,
                'parametros_json' => [
                    'force_full' => false,
                    'integracao_id' => (int) $row['id'],
                    'cliente_id' => (int) $row['cliente_id'],
                    'conta_id' => !empty($row['conta_id']) ? (int) $row['conta_id'] : null,
                ],
                'mensagem' => 'Sync automatica Mercado Phone solicitada pelo cron para a integracao configurada.',
            ]);

            if ($jobId) {
                $enfileirados++;
                $jobIds[] = $jobId;
            } else {
                $ignorados++;
            }
        }

        return [
            'integracoes_lidas' => count($integracoes),
            'jobs_enfileirados' => $enfileirados,
            'jobs_ignorados' => $ignorados,
            'job_ids' => $jobIds,
        ];
    }

    private function ensureSyncJobsTipoSupportsMercadoPhone(): void
    {
        $stmt = $this->conn->query("SHOW COLUMNS FROM sync_jobs LIKE 'tipo'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = (string) ($column['Type'] ?? '');

        if ($type !== '' && str_contains($type, "'mercado_phone'")) {
            return;
        }

        $this->conn->exec("
            ALTER TABLE sync_jobs
            MODIFY COLUMN tipo ENUM(
                'estrutura',
                'insights',
                'reconciliacao',
                'completo',
                'manutencao',
                'mercado_phone'
            ) COLLATE utf8mb4_unicode_ci NOT NULL
        ");
    }
}
