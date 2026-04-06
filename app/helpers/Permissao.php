<?php

class Permissao
{
    /**
     * Busca dados base de permissão do usuário na empresa
     */
    private static function getPermissaoData(PDO $conn, int $usuarioId, int $empresaId): ?array
    {
        $sql = "
            SELECT 
                e.is_root,
                ue.perfil,
                ue.status
            FROM usuarios_empresas ue
            INNER JOIN empresas e ON e.id = ue.empresa_id
            WHERE ue.usuario_id = :usuario_id
              AND ue.empresa_id = :empresa_id
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // usuário precisa estar ativo
        if (($row['status'] ?? '') !== 'ativo') {
            return null;
        }

        return $row;
    }

    /**
     * Pode gerenciar usuários
     * root, owner, admin
     */
    public static function podeGerenciarUsuarios(PDO $conn, int $usuarioId, int $empresaId): bool
    {
        $row = self::getPermissaoData($conn, $usuarioId, $empresaId);

        if (!$row) {
            return false;
        }

        // root sempre pode
        if ((int) ($row['is_root'] ?? 0) === 1) {
            return true;
        }

        return in_array($row['perfil'] ?? '', ['owner', 'admin'], true);
    }

    /**
     * Pode gerenciar empresas
     * somente root da plataforma
     */
    public static function podeGerenciarEmpresas(PDO $conn, int $usuarioId, int $empresaId): bool
    {
        $row = self::getPermissaoData($conn, $usuarioId, $empresaId);

        if (!$row) {
            return false;
        }

        // root sempre pode
        if ((int) ($row['is_root'] ?? 0) === 1) {
            return true;
        }

        return false;
    }
}
