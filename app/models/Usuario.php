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
}