<?php

class Usuario
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getByEmail(string $email): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function getEmpresaPrincipalByUsuarioId(int $usuarioId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT ue.*
            FROM usuarios_empresas ue
            WHERE ue.usuario_id = :usuario_id
              AND ue.status = 'ativo'
            ORDER BY ue.is_principal DESC, ue.id ASC
            LIMIT 1
        ");

        $stmt->execute([
            ':usuario_id' => $usuarioId
        ]);

        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        return $empresa ?: null;
    }

    public function autenticar(string $email, string $senha): ?array
    {
        $usuario = $this->getByEmail($email);

        if (!$usuario) {
            return null;
        }

        if (($usuario['status'] ?? '') !== 'ativo') {
            return null;
        }

        if (!password_verify($senha, $usuario['senha_hash'])) {
            return null;
        }

        $empresa = $this->getEmpresaPrincipalByUsuarioId((int) $usuario['id']);

        if (!$empresa) {
            return null;
        }

        return [
            'usuario' => $usuario,
            'empresa_id' => (int) $empresa['empresa_id'],
            'perfil' => $empresa['perfil'] ?? null
        ];
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->conn->prepare("
        SELECT *
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ");

        $stmt->execute([
            ':email' => mb_strtolower(trim($email))
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function emailExistsForOtherUser(string $email, int $excludedUserId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM usuarios
            WHERE email = :email
              AND id <> :id
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => mb_strtolower(trim($email)),
            ':id' => $excludedUserId,
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile(int $usuarioId, array $data): bool
    {
        $sql = "
            UPDATE usuarios
            SET
                nome = :nome,
                email = :email,
                telefone = :telefone,
                foto = :foto
        ";

        $params = [
            ':id' => $usuarioId,
            ':nome' => trim((string) ($data['nome'] ?? '')),
            ':email' => mb_strtolower(trim((string) ($data['email'] ?? ''))),
            ':telefone' => (($data['telefone'] ?? '') !== '') ? trim((string) $data['telefone']) : null,
            ':foto' => (($data['foto'] ?? '') !== '') ? trim((string) $data['foto']) : null,
        ];

        if (!empty($data['senha_hash'])) {
            $sql .= ",
                senha_hash = :senha_hash
            ";
            $params[':senha_hash'] = (string) $data['senha_hash'];
        }

        $sql .= "
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute($params);
    }

    public function updatePassword(int $usuarioId, string $senhaHash): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE usuarios
            SET senha_hash = :senha_hash,
                atualizado_em = NOW()
            WHERE id = :id
            LIMIT 1
        ");

        return $stmt->execute([
            ':id' => $usuarioId,
            ':senha_hash' => $senhaHash,
        ]);
    }

    public function create(array $data): int
    {
        $stmt = $this->conn->prepare("
        INSERT INTO usuarios (
            uuid,
            nome,
            email,
            senha_hash,
            status,
            created_at
        ) VALUES (
            :uuid,
            :nome,
            :email,
            :senha_hash,
            :status,
            NOW()
        )
    ");

        $stmt->execute([
            ':uuid' => $data['uuid'],
            ':nome' => trim($data['nome']),
            ':email' => mb_strtolower(trim($data['email'])),
            ':senha_hash' => $data['senha_hash'],
            ':status' => $data['status'] ?? 'ativo',
        ]);

        return (int) $this->conn->lastInsertId();
    }
}
