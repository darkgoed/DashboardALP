<?php

class EntityDeletionService
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function deleteContaAds(int $empresaId, int $contaId): bool
    {
        if ($empresaId <= 0 || $contaId <= 0) {
            throw new InvalidArgumentException('Conta invalida.');
        }

        $this->conn->beginTransaction();

        try {
            $conta = $this->findContaAds($empresaId, $contaId);

            if (!$conta) {
                $this->conn->rollBack();
                return false;
            }

            $this->deleteContaAdsData($empresaId, $contaId);

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function deleteCliente(int $empresaId, int $clienteId): bool
    {
        if ($empresaId <= 0 || $clienteId <= 0) {
            throw new InvalidArgumentException('Cliente invalido.');
        }

        $this->conn->beginTransaction();

        try {
            if (!$this->findCliente($empresaId, $clienteId)) {
                $this->conn->rollBack();
                return false;
            }

            foreach ($this->getContaIdsByCliente($empresaId, $clienteId) as $contaId) {
                $this->deleteContaAdsData($empresaId, $contaId);
            }

            $this->deleteByEmpresaAndClienteIfExists('mercado_phone_integracoes', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('mercado_phone_produtos', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('mercado_phone_clientes', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('mercado_phone_vendas', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('mercado_phone_metricas_diarias', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('meta_tokens', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('metricas_config', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('observacoes', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('envios_relatorios', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('relatorios_agendados', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('relatorios_programacoes', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('insights_diarios', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('sync_logs', $empresaId, $clienteId);
            $this->deleteByEmpresaAndClienteIfExists('sync_jobs', $empresaId, $clienteId);

            $stmt = $this->conn->prepare('
                DELETE FROM clientes
                WHERE id = :cliente_id
                  AND empresa_id = :empresa_id
                LIMIT 1
            ');
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':empresa_id' => $empresaId,
            ]);

            $this->conn->commit();
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    private function deleteContaAdsData(int $empresaId, int $contaId): void
    {
        $campanhaIds = $this->getCampanhaIdsByConta($empresaId, $contaId);
        $conjuntoIds = $this->getConjuntoIdsByCampanhas($empresaId, $campanhaIds);

        $this->deleteByEmpresaAndContaIfExists('insights_diarios', $empresaId, $contaId);
        $this->deleteByEmpresaAndContaIfExists('sync_logs', $empresaId, $contaId);
        $this->deleteByEmpresaAndContaIfExists('sync_jobs', $empresaId, $contaId);

        if ($conjuntoIds !== []) {
            $this->deleteByEmpresaAndIds('anuncios', 'conjunto_id', $empresaId, $conjuntoIds);
        }

        if ($campanhaIds !== []) {
            $this->deleteByEmpresaAndIds('conjuntos', 'campanha_id', $empresaId, $campanhaIds);
        }

        $this->deleteByEmpresaAndContaIfExists('campanhas', $empresaId, $contaId);

        $stmt = $this->conn->prepare('
            DELETE FROM contas_ads
            WHERE id = :conta_id
              AND empresa_id = :empresa_id
            LIMIT 1
        ');
        $stmt->execute([
            ':conta_id' => $contaId,
            ':empresa_id' => $empresaId,
        ]);
    }

    private function findCliente(int $empresaId, int $clienteId): ?array
    {
        $stmt = $this->conn->prepare('
            SELECT id
            FROM clientes
            WHERE id = :cliente_id
              AND empresa_id = :empresa_id
            LIMIT 1
        ');
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function findContaAds(int $empresaId, int $contaId): ?array
    {
        $stmt = $this->conn->prepare('
            SELECT id
            FROM contas_ads
            WHERE id = :conta_id
              AND empresa_id = :empresa_id
            LIMIT 1
        ');
        $stmt->execute([
            ':conta_id' => $contaId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getContaIdsByCliente(int $empresaId, int $clienteId): array
    {
        if (!$this->tableExists('contas_ads')) {
            return [];
        }

        $stmt = $this->conn->prepare('
            SELECT id
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND cliente_id = :cliente_id
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
        ]);

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    private function getCampanhaIdsByConta(int $empresaId, int $contaId): array
    {
        if (!$this->tableExists('campanhas')) {
            return [];
        }

        $stmt = $this->conn->prepare('
            SELECT id
            FROM campanhas
            WHERE empresa_id = :empresa_id
              AND conta_id = :conta_id
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    private function getConjuntoIdsByCampanhas(int $empresaId, array $campanhaIds): array
    {
        if ($campanhaIds === [] || !$this->tableExists('conjuntos')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($campanhaIds), '?'));
        $sql = "
            SELECT id
            FROM conjuntos
            WHERE empresa_id = ?
              AND campanha_id IN ({$placeholders})
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute(array_merge([$empresaId], $campanhaIds));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    private function deleteByEmpresaAndClienteIfExists(string $table, int $empresaId, int $clienteId): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $stmt = $this->conn->prepare("
            DELETE FROM {$table}
            WHERE empresa_id = :empresa_id
              AND cliente_id = :cliente_id
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
        ]);
    }

    private function deleteByEmpresaAndContaIfExists(string $table, int $empresaId, int $contaId): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $stmt = $this->conn->prepare("
            DELETE FROM {$table}
            WHERE empresa_id = :empresa_id
              AND conta_id = :conta_id
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);
    }

    private function deleteByEmpresaAndIds(string $table, string $column, int $empresaId, array $ids): void
    {
        if ($ids === [] || !$this->tableExists($table)) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $sql = "
            DELETE FROM {$table}
            WHERE empresa_id = ?
              AND {$column} IN ({$placeholders})
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute(array_merge([$empresaId], $ids));
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
