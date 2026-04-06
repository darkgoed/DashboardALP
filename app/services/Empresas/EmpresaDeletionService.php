<?php

class EmpresaDeletionService
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function deleteEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }

        $this->conn->beginTransaction();

        try {
            $usuarioIds = $this->getUsuarioIdsDaEmpresa($empresaId);

            $this->deleteFromTableIfExists('convites_empresa', 'empresa_id', $empresaId);
            $this->deleteFromTableIfExists('empresas_assinaturas', 'empresa_id', $empresaId);
            $this->deleteFromTableIfExists('meta_tokens', 'empresa_id', $empresaId);
            $this->deleteFromTableIfExists('envios_relatorios', 'empresa_id', $empresaId);
            $this->deleteFromTableIfExists('observacoes', 'empresa_id', $empresaId);

            $stmt = $this->conn->prepare('DELETE FROM empresas WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $empresaId]);

            foreach ($usuarioIds as $usuarioId) {
                if ($this->countEmpresasDoUsuario($usuarioId) === 0) {
                    $this->deleteUsuarioDependencias($usuarioId);

                    $stmt = $this->conn->prepare('DELETE FROM usuarios WHERE id = :id');
                    $stmt->execute([':id' => $usuarioId]);
                }
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    private function getUsuarioIdsDaEmpresa(int $empresaId): array
    {
        if (!$this->tableExists('usuarios_empresas')) {
            return [];
        }

        $stmt = $this->conn->prepare('
            SELECT usuario_id
            FROM usuarios_empresas
            WHERE empresa_id = :empresa_id
        ');
        $stmt->execute([':empresa_id' => $empresaId]);

        return array_map(
            static fn(array $row): int => (int) $row['usuario_id'],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    private function countEmpresasDoUsuario(int $usuarioId): int
    {
        if (!$this->tableExists('usuarios_empresas')) {
            return 0;
        }

        $stmt = $this->conn->prepare('
            SELECT COUNT(*)
            FROM usuarios_empresas
            WHERE usuario_id = :usuario_id
        ');
        $stmt->execute([':usuario_id' => $usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    private function deleteUsuarioDependencias(int $usuarioId): void
    {
        $this->deleteFromTableIfExists('usuarios_recuperacao_senha', 'usuario_id', $usuarioId);
        $this->deleteFromTableIfExists('usuarios_sessoes', 'usuario_id', $usuarioId);
    }

    private function deleteFromTableIfExists(string $table, string $column, int $value): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $stmt = $this->conn->prepare("DELETE FROM {$table} WHERE {$column} = :value");
        $stmt->execute([':value' => $value]);
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $stmt = $this->conn->prepare('
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
        ');
        $stmt->execute([':table_name' => $table]);

        $cache[$table] = ((int) $stmt->fetchColumn()) > 0;

        return $cache[$table];
    }
}
