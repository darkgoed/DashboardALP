<?php

class EmpresaAssinatura
{
    private PDO $conn;
    private string $table = 'empresas_assinaturas';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);

        $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

        return $assinatura ?: null;
    }

    public function findAtualByEmpresa(int $empresaId): ?array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE empresa_id = :empresa_id
            ORDER BY id DESC
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresaId]);

        $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

        return $assinatura ?: null;
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                empresa_id,
                plano_id,
                tipo_cobranca,
                status_assinatura,
                data_inicio,
                data_vencimento,
                dias_tolerancia,
                data_bloqueio,
                valor_cobrado,
                observacoes_internas,
                bloqueio_manual,
                bloqueio_manual_motivo
            ) VALUES (
                :empresa_id,
                :plano_id,
                :tipo_cobranca,
                :status_assinatura,
                :data_inicio,
                :data_vencimento,
                :dias_tolerancia,
                :data_bloqueio,
                :valor_cobrado,
                :observacoes_internas,
                :bloqueio_manual,
                :bloqueio_manual_motivo
            )
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'empresa_id' => (int) $data['empresa_id'],
            'plano_id' => $data['plano_id'] ?? null,
            'tipo_cobranca' => $data['tipo_cobranca'] ?? 'mensal',
            'status_assinatura' => $data['status_assinatura'] ?? 'ativa',
            'data_inicio' => $data['data_inicio'],
            'data_vencimento' => $data['data_vencimento'] ?? null,
            'dias_tolerancia' => (int) ($data['dias_tolerancia'] ?? 0),
            'data_bloqueio' => $data['data_bloqueio'] ?? null,
            'valor_cobrado' => $data['valor_cobrado'] ?? null,
            'observacoes_internas' => $data['observacoes_internas'] ?? null,
            'bloqueio_manual' => (int) ($data['bloqueio_manual'] ?? 0),
            'bloqueio_manual_motivo' => $data['bloqueio_manual_motivo'] ?? null,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET
                plano_id = :plano_id,
                tipo_cobranca = :tipo_cobranca,
                status_assinatura = :status_assinatura,
                data_inicio = :data_inicio,
                data_vencimento = :data_vencimento,
                dias_tolerancia = :dias_tolerancia,
                data_bloqueio = :data_bloqueio,
                valor_cobrado = :valor_cobrado,
                observacoes_internas = :observacoes_internas,
                bloqueio_manual = :bloqueio_manual,
                bloqueio_manual_motivo = :bloqueio_manual_motivo
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'plano_id' => $data['plano_id'] ?? null,
            'tipo_cobranca' => $data['tipo_cobranca'] ?? 'mensal',
            'status_assinatura' => $data['status_assinatura'] ?? 'ativa',
            'data_inicio' => $data['data_inicio'],
            'data_vencimento' => $data['data_vencimento'] ?? null,
            'dias_tolerancia' => (int) ($data['dias_tolerancia'] ?? 0),
            'data_bloqueio' => $data['data_bloqueio'] ?? null,
            'valor_cobrado' => $data['valor_cobrado'] ?? null,
            'observacoes_internas' => $data['observacoes_internas'] ?? null,
            'bloqueio_manual' => (int) ($data['bloqueio_manual'] ?? 0),
            'bloqueio_manual_motivo' => $data['bloqueio_manual_motivo'] ?? null,
        ]);
    }

    public function updateStatus(int $id, string $statusAssinatura): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET status_assinatura = :status_assinatura
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'status_assinatura' => $statusAssinatura,
        ]);
    }

    public function setBloqueioManual(int $id, bool $bloquear, ?string $motivo = null): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET
                bloqueio_manual = :bloqueio_manual,
                bloqueio_manual_motivo = :bloqueio_manual_motivo
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'bloqueio_manual' => $bloquear ? 1 : 0,
            'bloqueio_manual_motivo' => $bloquear ? $motivo : null,
        ]);
    }
}