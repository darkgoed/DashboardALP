<?php

class ConviteEmpresa
{
    private PDO $conn;
    private string $table = 'convites_empresa';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                uuid,
                empresa_id,
                nome,
                email,
                token,
                perfil,
                status,
                expires_at
            ) VALUES (
                :uuid,
                :empresa_id,
                :nome,
                :email,
                :token,
                :perfil,
                :status,
                :expires_at
            )
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':uuid' => $data['uuid'],
            ':empresa_id' => (int) $data['empresa_id'],
            ':nome' => trim((string) $data['nome']),
            ':email' => mb_strtolower(trim((string) $data['email'])),
            ':token' => trim((string) $data['token']),
            ':perfil' => $data['perfil'] ?? 'owner',
            ':status' => $data['status'] ?? 'pendente',
            ':expires_at' => $data['expires_at'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                c.*,
                e.nome_fantasia,
                e.razao_social,
                e.slug AS empresa_slug
            FROM {$this->table} c
            INNER JOIN empresas e ON e.id = c.empresa_id
            WHERE c.id = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $sql = "
            SELECT
                c.*,
                e.nome_fantasia,
                e.razao_social,
                e.slug AS empresa_slug
            FROM {$this->table} c
            INNER JOIN empresas e ON e.id = c.empresa_id
            WHERE c.token = :token
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':token' => trim($token),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPendingByEmpresaId(int $empresaId): ?array
    {
        $sql = "
            SELECT
                c.*,
                e.nome_fantasia,
                e.razao_social,
                e.slug AS empresa_slug
            FROM {$this->table} c
            INNER JOIN empresas e ON e.id = c.empresa_id
            WHERE c.empresa_id = :empresa_id
              AND c.status = 'pendente'
            ORDER BY c.id DESC
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listByEmpresaId(int $empresaId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $sql = "
            SELECT
                c.*,
                e.nome_fantasia,
                e.razao_social,
                e.slug AS empresa_slug
            FROM {$this->table} c
            INNER JOIN empresas e ON e.id = c.empresa_id
            WHERE c.empresa_id = :empresa_id
            ORDER BY c.id DESC
            LIMIT {$limit}
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markAsAccepted(int $id): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET
                status = 'aceito',
                accepted_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
              AND status = 'pendente'
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markAsExpiredById(int $id): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET
                status = 'expirado',
                updated_at = NOW()
            WHERE id = :id
              AND status = 'pendente'
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function cancelById(int $id): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET
                status = 'cancelado',
                updated_at = NOW()
            WHERE id = :id
              AND status = 'pendente'
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function expirePendingsByEmpresaId(int $empresaId): int
    {
        $sql = "
            UPDATE {$this->table}
            SET
                status = 'expirado',
                updated_at = NOW()
            WHERE empresa_id = :empresa_id
              AND status = 'pendente'
              AND expires_at < NOW()
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->rowCount();
    }

    public function cancelPendingByEmpresaId(int $empresaId): int
    {
        $sql = "
            UPDATE {$this->table}
            SET
                status = 'cancelado',
                updated_at = NOW()
            WHERE empresa_id = :empresa_id
              AND status = 'pendente'
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->rowCount();
    }

    public function hasPendingForEmailInEmpresa(int $empresaId, string $email): bool
    {
        $sql = "
            SELECT id
            FROM {$this->table}
            WHERE empresa_id = :empresa_id
              AND email = :email
              AND status = 'pendente'
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':email' => mb_strtolower(trim($email)),
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}