<?php

class Cliente
{
    private PDO $conn;
    private int $empresaId;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
    }

    public function getAll(): array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM clientes
            WHERE empresa_id = :empresa_id
            ORDER BY id DESC
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
            FROM clientes
            WHERE id = :id
              AND empresa_id = :empresa_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId
        ]);

        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        return $cliente ?: null;
    }

    public function create(
        string $nome,
        ?string $email = null,
        ?string $whatsapp = null,
        ?string $logo = null,
        int $ativo = 1
    ): bool {
        $stmt = $this->conn->prepare("
            INSERT INTO clientes (
                empresa_id,
                nome,
                email,
                whatsapp,
                logo,
                ativo
            ) VALUES (
                :empresa_id,
                :nome,
                :email,
                :whatsapp,
                :logo,
                :ativo
            )
        ");

        return $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':nome' => $nome,
            ':email' => $email ?: null,
            ':whatsapp' => $whatsapp ?: null,
            ':logo' => $logo ?: null,
            ':ativo' => $ativo
        ]);
    }

    public function update(
        int $id,
        string $nome,
        ?string $email = null,
        ?string $whatsapp = null,
        ?string $logo = null,
        ?int $ativo = null
    ): bool {
        $sql = "
            UPDATE clientes
            SET nome = :nome,
                email = :email,
                whatsapp = :whatsapp,
                logo = :logo
        ";

        $params = [
            ':id' => $id,
            ':empresa_id' => $this->empresaId,
            ':nome' => $nome,
            ':email' => $email ?: null,
            ':whatsapp' => $whatsapp ?: null,
            ':logo' => $logo ?: null
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

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare("
            DELETE FROM clientes
            WHERE id = :id
              AND empresa_id = :empresa_id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId
        ]);
    }

    public function updateStatus(int $id, int $ativo): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE clientes
            SET ativo = :ativo
            WHERE id = :id
              AND empresa_id = :empresa_id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $this->empresaId,
            ':ativo' => $ativo
        ]);
    }
}