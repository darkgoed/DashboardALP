<?php

class UsuarioManagementService
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getIndexData(int $empresaId): array
    {
        $usuarios = $this->fetchUsuarios($empresaId);

        return [
            'usuarios' => $usuarios,
            'stats' => [
                'total_usuarios' => count($usuarios),
                'total_ativos' => $this->countByStatus($usuarios, 'ativo'),
                'total_admins' => $this->countByPerfis($usuarios, ['owner', 'admin']),
                'total_principais' => $this->countPrincipais($usuarios),
            ],
        ];
    }

    private function fetchUsuarios(int $empresaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                u.id,
                u.nome,
                u.email,
                u.telefone,
                u.status,
                ue.perfil,
                ue.is_principal,
                u.criado_em
            FROM usuarios u
            INNER JOIN usuarios_empresas ue
                ON ue.usuario_id = u.id
               AND ue.empresa_id = :empresa_id
            ORDER BY ue.is_principal DESC, u.id DESC
        ");

        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function countByStatus(array $usuarios, string $status): int
    {
        $total = 0;

        foreach ($usuarios as $usuario) {
            if (($usuario['status'] ?? '') === $status) {
                $total++;
            }
        }

        return $total;
    }

    private function countByPerfis(array $usuarios, array $perfis): int
    {
        $total = 0;

        foreach ($usuarios as $usuario) {
            if (in_array($usuario['perfil'] ?? '', $perfis, true)) {
                $total++;
            }
        }

        return $total;
    }

    private function countPrincipais(array $usuarios): int
    {
        $total = 0;

        foreach ($usuarios as $usuario) {
            if ((int) ($usuario['is_principal'] ?? 0) === 1) {
                $total++;
            }
        }

        return $total;
    }
}
