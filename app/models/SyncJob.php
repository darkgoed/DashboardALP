<?php

class SyncJob
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function create(array $data): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO sync_jobs (
                empresa_id,
                cliente_id,
                conta_id,
                tipo,
                origem,
                prioridade,
                status,
                force_sync,
                janela_inicio,
                janela_fim,
                tentativas,
                max_tentativas,
                parametros_json,
                mensagem
            ) VALUES (
                :empresa_id,
                :cliente_id,
                :conta_id,
                :tipo,
                :origem,
                :prioridade,
                :status,
                :force_sync,
                :janela_inicio,
                :janela_fim,
                :tentativas,
                :max_tentativas,
                :parametros_json,
                :mensagem
            )
        ");

        $stmt->execute([
            ':empresa_id' => $data['empresa_id'],
            ':cliente_id' => $data['cliente_id'] ?? null,
            ':conta_id' => $data['conta_id'] ?? null,
            ':tipo' => $data['tipo'],
            ':origem' => $data['origem'] ?? 'cron',
            ':prioridade' => $data['prioridade'] ?? 5,
            ':status' => $data['status'] ?? 'pendente',
            ':force_sync' => !empty($data['force_sync']) ? 1 : 0,
            ':janela_inicio' => $data['janela_inicio'] ?? null,
            ':janela_fim' => $data['janela_fim'] ?? null,
            ':tentativas' => $data['tentativas'] ?? 0,
            ':max_tentativas' => $data['max_tentativas'] ?? 3,
            ':parametros_json' => isset($data['parametros_json'])
                ? (is_string($data['parametros_json']) ? $data['parametros_json'] : json_encode($data['parametros_json'], JSON_UNESCAPED_UNICODE))
                : null,
            ':mensagem' => $data['mensagem'] ?? null,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM sync_jobs
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['parametros'] = $this->decodeJson($row['parametros_json'] ?? null);

        return $row;
    }

    public function getNextPending(?string $tipo = null): ?array
    {
        $sql = "
            SELECT *
            FROM sync_jobs
            WHERE status = 'pendente'
              AND tentativas < max_tentativas
        ";

        $params = [];

        if ($tipo !== null) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        $sql .= "
            ORDER BY prioridade ASC, criado_em ASC, id ASC
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['parametros'] = $this->decodeJson($row['parametros_json'] ?? null);

        return $row;
    }

    public function claimNextPending(string $workerToken, ?string $tipo = null): ?array
    {
        $this->conn->beginTransaction();

        try {
            $sql = "
            SELECT id
            FROM sync_jobs
            WHERE status = 'pendente'
              AND tentativas < max_tentativas
        ";

            $params = [];

            if ($tipo !== null) {
                $sql .= " AND tipo = :tipo";
                $params[':tipo'] = $tipo;
            }

            $sql .= " ORDER BY prioridade ASC, criado_em ASC, id ASC LIMIT 1 FOR UPDATE";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            $jobId = $stmt->fetchColumn();

            if (!$jobId) {
                $this->conn->commit();
                return null;
            }

            $this->markProcessing((int) $jobId, $workerToken);

            $job = $this->findById((int) $jobId);

            $this->conn->commit();

            return $job;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function markProcessing(int $id, string $workerToken): void
    {
        $stmt = $this->conn->prepare("
            UPDATE sync_jobs
            SET status = 'processando',
                worker_token = :worker_token,
                locked_at = NOW(),
                iniciado_em = NOW(),
                tentativas = tentativas + 1
            WHERE id = :id
              AND status = 'pendente'
        ");

        $stmt->execute([
            ':id' => $id,
            ':worker_token' => $workerToken
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Job não pôde ser marcado como processando.');
        }
    }

    public function markDone(int $id, string $workerToken, ?string $mensagem = null): void
    {
        $stmt = $this->conn->prepare("
        UPDATE sync_jobs
        SET status = 'concluido',
            mensagem = :mensagem,
            finalizado_em = NOW(),
            worker_token = NULL,
            locked_at = NULL
        WHERE id = :id
          AND status = 'processando'
          AND worker_token = :worker_token
    ");

        $stmt->execute([
            ':id' => $id,
            ':worker_token' => $workerToken,
            ':mensagem' => $mensagem
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Job não pôde ser finalizado por este worker.');
        }
    }

    public function markFailure(int $id, string $workerToken, string $mensagem): void
    {
        $stmt = $this->conn->prepare("
        UPDATE sync_jobs
        SET status = CASE
                WHEN tentativas >= max_tentativas THEN 'erro'
                ELSE 'pendente'
            END,
            mensagem = :mensagem,
            finalizado_em = CASE
                WHEN tentativas >= max_tentativas THEN NOW()
                ELSE NULL
            END,
            worker_token = NULL,
            locked_at = NULL
        WHERE id = :id
          AND status = 'processando'
          AND worker_token = :worker_token
    ");

        $stmt->execute([
            ':id' => $id,
            ':worker_token' => $workerToken,
            ':mensagem' => $mensagem
        ]);
    }

    public function markError(int $id, string $workerToken, string $mensagem): void
    {
        $stmt = $this->conn->prepare("
        UPDATE sync_jobs
        SET status = 'erro',
            mensagem = :mensagem,
            finalizado_em = NOW(),
            worker_token = NULL,
            locked_at = NULL
        WHERE id = :id
          AND status = 'processando'
          AND worker_token = :worker_token
    ");

        $stmt->execute([
            ':id' => $id,
            ':worker_token' => $workerToken,
            ':mensagem' => $mensagem
        ]);
    }

    public function requeueStuckJobs(int $minutes = 30): int
    {
        $stmt = $this->conn->prepare("
            UPDATE sync_jobs
            SET status = 'pendente',
                worker_token = NULL,
                locked_at = NULL,
                iniciado_em = NULL,
                mensagem = CONCAT(COALESCE(mensagem, ''), ' | Job reencaminhado automaticamente por travamento.')
            WHERE status = 'processando'
              AND locked_at IS NOT NULL
              AND locked_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
              AND tentativas < max_tentativas
        ");

        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function existsPendingSimilar(
        int $empresaId,
        ?int $clienteId,
        ?int $contaId,
        string $tipo,
        ?string $janelaInicio = null,
        ?string $janelaFim = null,
        ?array $parametros = null
    ): bool {
        $sql = "
        SELECT id
        FROM sync_jobs
        WHERE empresa_id = :empresa_id
          AND (
                (:cliente_id IS NULL AND cliente_id IS NULL)
                OR cliente_id = :cliente_id
          )
          AND tipo = :tipo
          AND status IN ('pendente', 'processando')
          AND (
                (:conta_id IS NULL AND conta_id IS NULL)
                OR conta_id = :conta_id
          )
          AND (
                (:janela_inicio IS NULL AND janela_inicio IS NULL)
                OR janela_inicio = :janela_inicio
          )
          AND (
                (:janela_fim IS NULL AND janela_fim IS NULL)
                OR janela_fim = :janela_fim
          )
    ";

        $params = [
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':conta_id' => $contaId,
            ':tipo' => $tipo,
            ':janela_inicio' => $janelaInicio,
            ':janela_fim' => $janelaFim,
        ];

        if (is_array($parametros) && array_key_exists('integracao_id', $parametros)) {
            $integracaoId = (int) ($parametros['integracao_id'] ?? 0);
            $sql .= "
          AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(parametros_json, '{}'), '$.integracao_id')) = :integracao_id
            ";
            $params[':integracao_id'] = (string) $integracaoId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function enqueueIfNotExists(array $data): ?int
    {
        $empresaId = (int) $data['empresa_id'];
        $clienteId = isset($data['cliente_id']) && $data['cliente_id'] !== null ? (int) $data['cliente_id'] : null;
        $contaId = isset($data['conta_id']) ? (int) $data['conta_id'] : null;
        $tipo = (string) $data['tipo'];
        $janelaInicio = $data['janela_inicio'] ?? null;
        $janelaFim = $data['janela_fim'] ?? null;
        $forceSync = !empty($data['force_sync']);
        $parametros = null;

        if (isset($data['parametros_json'])) {
            $parametros = is_array($data['parametros_json'])
                ? $data['parametros_json']
                : json_decode((string) $data['parametros_json'], true);
        }

        if (!$forceSync && $this->existsPendingSimilar($empresaId, $clienteId, $contaId, $tipo, $janelaInicio, $janelaFim, is_array($parametros) ? $parametros : null)) {
            return null;
        }

        return $this->create([
            'empresa_id' => $empresaId,
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'tipo' => $tipo,
            'origem' => $data['origem'] ?? 'cron',
            'prioridade' => $data['prioridade'] ?? 5,
            'status' => 'pendente',
            'force_sync' => $forceSync ? 1 : 0,
            'janela_inicio' => $janelaInicio,
            'janela_fim' => $janelaFim,
            'parametros_json' => $data['parametros_json'] ?? null,
            'mensagem' => $data['mensagem'] ?? null,
        ]);
    }

    public function cancel(int $id, ?string $mensagem = null): void
    {
        $stmt = $this->conn->prepare("
        UPDATE sync_jobs
        SET status = 'cancelado',
            mensagem = :mensagem,
            finalizado_em = NOW(),
            worker_token = NULL,
            locked_at = NULL
        WHERE id = :id
          AND status IN ('pendente', 'processando')
    ");

        $stmt->execute([
            ':id' => $id,
            ':mensagem' => $mensagem
        ]);
    }

    public function markRequeued(int $id, int $novoJobId): void
    {
        $stmt = $this->conn->prepare("
        UPDATE sync_jobs
        SET mensagem = CONCAT(
                COALESCE(mensagem, ''),
                CASE
                    WHEN COALESCE(mensagem, '') = '' THEN ''
                    ELSE ' | '
                END,
                'Reenfileirado manualmente no job #',
                :novo_job_id
            )
        WHERE id = :id
          AND status IN ('erro', 'cancelado')
          AND COALESCE(mensagem, '') NOT LIKE :marcador
    ");

        $stmt->execute([
            ':id' => $id,
            ':novo_job_id' => $novoJobId,
            ':marcador' => '%Reenfileirado manualmente no job #%',
        ]);
    }

    private function decodeJson(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
