<?php

class ContaAds
{
    private PDO $conn;
    private int $empresaId;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
    }

    private function clientePertenceEmpresa(int $clienteId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1
            FROM clientes
            WHERE id = :id
              AND empresa_id = :empresa_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $clienteId,
            ':empresa_id' => $this->empresaId
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function getAll(): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                ca.*,
                c.nome AS cliente_nome
            FROM contas_ads ca
            LEFT JOIN clientes c
                ON c.id = ca.cliente_id
               AND c.empresa_id = ca.empresa_id
            WHERE ca.empresa_id = :empresa_id
            ORDER BY ca.id DESC
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
            FROM contas_ads
            WHERE id = :id
              AND empresa_id = :empresa_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId
        ]);

        $conta = $stmt->fetch(PDO::FETCH_ASSOC);

        return $conta ?: null;
    }

    public function getByCliente(int $clienteId): array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM contas_ads
            WHERE cliente_id = :cliente_id
              AND empresa_id = :empresa_id
            ORDER BY nome ASC
        ");

        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':empresa_id' => $this->empresaId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        int $clienteId,
        string $nome,
        ?string $metaAccountId = null,
        ?string $businessName = null,
        ?string $moeda = null,
        ?string $timezoneName = null,
        ?string $status = null,
        ?string $descricao = null,
        int $ativo = 1
    ): bool {
        if (!$this->clientePertenceEmpresa($clienteId)) {
            return false;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO contas_ads (
                empresa_id,
                cliente_id,
                meta_account_id,
                nome,
                business_name,
                moeda,
                timezone_name,
                status,
                descricao,
                ativo
            ) VALUES (
                :empresa_id,
                :cliente_id,
                :meta_account_id,
                :nome,
                :business_name,
                :moeda,
                :timezone_name,
                :status,
                :descricao,
                :ativo
            )
        ");

        return $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
            ':meta_account_id' => $metaAccountId ?: null,
            ':nome' => $nome,
            ':business_name' => $businessName ?: null,
            ':moeda' => $moeda ?: null,
            ':timezone_name' => $timezoneName ?: null,
            ':status' => $status ?: null,
            ':descricao' => $descricao ?: null,
            ':ativo' => $ativo
        ]);
    }

    public function update(
        int $id,
        int $clienteId,
        string $nome,
        ?string $metaAccountId = null,
        ?string $businessName = null,
        ?string $moeda = null,
        ?string $timezoneName = null,
        ?string $status = null,
        ?string $descricao = null,
        ?int $ativo = null
    ): bool {
        if (!$this->clientePertenceEmpresa($clienteId)) {
            return false;
        }

        $sql = "
            UPDATE contas_ads
            SET cliente_id = :cliente_id,
                meta_account_id = :meta_account_id,
                nome = :nome,
                business_name = :business_name,
                moeda = :moeda,
                timezone_name = :timezone_name,
                status = :status,
                descricao = :descricao
        ";

        $params = [
            ':id' => $id,
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
            ':meta_account_id' => $metaAccountId ?: null,
            ':nome' => $nome,
            ':business_name' => $businessName ?: null,
            ':moeda' => $moeda ?: null,
            ':timezone_name' => $timezoneName ?: null,
            ':status' => $status ?: null,
            ':descricao' => $descricao ?: null
        ];

        if ($ativo !== null) {
            $sql .= ", ativo = :ativo";
            $params[':ativo'] = $ativo;
        }

        $sql .= "
            WHERE id = :id
              AND empresa_id = :empresa_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare("
            DELETE FROM contas_ads
            WHERE id = :id
              AND empresa_id = :empresa_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, int $ativo): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE contas_ads
            SET ativo = :ativo
            WHERE id = :id
              AND empresa_id = :empresa_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId,
            ':ativo' => $ativo
        ]);

        return $stmt->rowCount() > 0;
    }
}