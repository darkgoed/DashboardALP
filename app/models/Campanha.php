<?php

class Campanha
{
    private PDO $conn;
    private int $empresaId;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
    }

    private function contaPertenceEmpresa(int $contaId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1
            FROM contas_ads
            WHERE id = :id
              AND empresa_id = :empresa_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $contaId,
            ':empresa_id' => $this->empresaId
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function getAll(): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                cp.*,
                ca.nome AS conta_nome
            FROM campanhas cp
            LEFT JOIN contas_ads ca
                ON ca.id = cp.conta_id
               AND ca.empresa_id = cp.empresa_id
            WHERE cp.empresa_id = :empresa_id
            ORDER BY cp.id DESC
        ");

        $stmt->execute([
            ':empresa_id' => $this->empresaId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM campanhas
            WHERE id = :id
              AND empresa_id = :empresa_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId
        ]);

        $campanha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $campanha ?: null;
    }

    public function getByConta(?int $contaId = null): array
    {
        if (!empty($contaId)) {
            $stmt = $this->conn->prepare("
                SELECT *
                FROM campanhas
                WHERE conta_id = :conta_id
                  AND empresa_id = :empresa_id
                ORDER BY nome ASC
            ");

            $stmt->execute([
                ':conta_id' => $contaId,
                ':empresa_id' => $this->empresaId
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM campanhas
            WHERE empresa_id = :empresa_id
            ORDER BY nome ASC
        ");

        $stmt->execute([
            ':empresa_id' => $this->empresaId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        int $contaId,
        string $nome,
        ?string $objetivo = null,
        ?string $metaCampaignId = null,
        ?string $status = null,
        ?string $effectiveStatus = null
    ): bool {
        if (!$this->contaPertenceEmpresa($contaId)) {
            return false;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO campanhas (
                empresa_id,
                conta_id,
                meta_campaign_id,
                nome,
                status,
                effective_status,
                objetivo
            ) VALUES (
                :empresa_id,
                :conta_id,
                :meta_campaign_id,
                :nome,
                :status,
                :effective_status,
                :objetivo
            )
        ");

        return $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':conta_id' => $contaId,
            ':meta_campaign_id' => $metaCampaignId ?: null,
            ':nome' => $nome,
            ':status' => $status ?: null,
            ':effective_status' => $effectiveStatus ?: null,
            ':objetivo' => $objetivo ?: null
        ]);
    }

    public function update(
        int $id,
        int $contaId,
        string $nome,
        ?string $objetivo = null,
        ?string $metaCampaignId = null,
        ?string $status = null,
        ?string $effectiveStatus = null
    ): bool {
        if (!$this->contaPertenceEmpresa($contaId)) {
            return false;
        }

        $stmt = $this->conn->prepare("
            UPDATE campanhas
            SET conta_id = :conta_id,
                meta_campaign_id = :meta_campaign_id,
                nome = :nome,
                status = :status,
                effective_status = :effective_status,
                objetivo = :objetivo
            WHERE id = :id
              AND empresa_id = :empresa_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId,
            ':conta_id' => $contaId,
            ':meta_campaign_id' => $metaCampaignId ?: null,
            ':nome' => $nome,
            ':status' => $status ?: null,
            ':effective_status' => $effectiveStatus ?: null,
            ':objetivo' => $objetivo ?: null
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare("
            DELETE FROM campanhas
            WHERE id = :id
              AND empresa_id = :empresa_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateStatus(
        int $id,
        ?string $status = null,
        ?string $effectiveStatus = null
    ): bool {
        $stmt = $this->conn->prepare("
            UPDATE campanhas
            SET status = :status,
                effective_status = :effective_status
            WHERE id = :id
              AND empresa_id = :empresa_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId,
            ':status' => $status ?: null,
            ':effective_status' => $effectiveStatus ?: null
        ]);

        return $stmt->rowCount() > 0;
    }
}