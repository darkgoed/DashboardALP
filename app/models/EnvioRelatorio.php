<?php

class EnvioRelatorio
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function create(array $data): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO envios_relatorios (
                empresa_id,
                cliente_id,
                tipo,
                status,
                mensagem,
                created_at
            ) VALUES (
                :empresa_id,
                :cliente_id,
                :tipo,
                :status,
                :mensagem,
                NOW()
            )
        ");

        $stmt->execute([
            ':empresa_id' => (int) $data['empresa_id'],
            ':cliente_id' => !empty($data['cliente_id']) ? (int) $data['cliente_id'] : null,
            ':tipo' => (string) ($data['tipo'] ?? 'manual'),
            ':status' => (string) ($data['status'] ?? 'pendente'),
            ':mensagem' => (string) ($data['mensagem'] ?? ''),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function latestByEmpresa(int $empresaId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));

        $stmt = $this->conn->prepare("
            SELECT *
            FROM envios_relatorios
            WHERE empresa_id = :empresa_id
            ORDER BY id DESC
            LIMIT {$limit}
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
