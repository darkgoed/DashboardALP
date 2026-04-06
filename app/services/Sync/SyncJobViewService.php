<?php

class SyncJobViewService
{
    private PDO $conn;
    private int $empresaId;
    private SyncJob $syncJobModel;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->syncJobModel = new SyncJob($conn);
    }

    public function getPageData(int $jobId): array
    {
        if ($jobId <= 0) {
            throw new RuntimeException('Job invalido.');
        }

        $job = $this->syncJobModel->findById($jobId);

        if (!$job || (int) ($job['empresa_id'] ?? 0) !== $this->empresaId) {
            throw new RuntimeException('Job nao encontrado.');
        }

        return [
            'job' => $job,
            'conta' => $this->fetchConta((int) ($job['conta_id'] ?? 0)),
            'cliente' => $this->fetchCliente((int) ($job['cliente_id'] ?? 0)),
            'logs_relacionados' => $this->fetchLogsRelacionados($jobId),
        ];
    }

    private function fetchConta(int $contaId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT nome, meta_account_id
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND id = :conta_id
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':conta_id' => $contaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function fetchCliente(int $clienteId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, nome
            FROM clientes
            WHERE empresa_id = :empresa_id
              AND id = :cliente_id
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function fetchLogsRelacionados(int $jobId): array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM sync_logs
            WHERE empresa_id = :empresa_id
              AND sync_job_id = :sync_job_id
            ORDER BY id DESC
        ");
        $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':sync_job_id' => $jobId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
