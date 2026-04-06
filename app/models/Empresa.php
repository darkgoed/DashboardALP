<?php

class Empresa
{
    private PDO $conn;
    private string $table = 'empresas';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);

        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        return $empresa ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE slug = :slug LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['slug' => $slug]);

        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        return $empresa ?: null;
    }

    public function getAll(): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            ORDER BY is_root DESC, nome_fantasia ASC
        ";

        $stmt = $this->conn->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllWithResumo(): array
    {
        $sql = "
            SELECT
                e.*,
                (
                    SELECT COUNT(*)
                    FROM usuarios_empresas ue
                    WHERE ue.empresa_id = e.id
                      AND ue.status = 'ativo'
                ) AS total_usuarios_ativos,
                (
                    SELECT COUNT(*)
                    FROM contas_ads ca
                    WHERE ca.empresa_id = e.id
                      AND ca.ativo = 1
                ) AS total_contas_ads_ativas
            FROM {$this->table} e
            ORDER BY e.is_root DESC, e.nome_fantasia ASC
        ";

        $stmt = $this->conn->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                uuid,
                nome_fantasia,
                razao_social,
                documento,
                email,
                telefone,
                slug,
                plano,
                status,
                limite_usuarios,
                limite_contas_ads,
                trial_ate,
                assinatura_ate,
                is_root
            ) VALUES (
                :uuid,
                :nome_fantasia,
                :razao_social,
                :documento,
                :email,
                :telefone,
                :slug,
                :plano,
                :status,
                :limite_usuarios,
                :limite_contas_ads,
                :trial_ate,
                :assinatura_ate,
                :is_root
            )
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'uuid' => $data['uuid'],
            'nome_fantasia' => $data['nome_fantasia'],
            'razao_social' => $data['razao_social'] ?? null,
            'documento' => $data['documento'] ?? null,
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'slug' => $data['slug'],
            'plano' => $data['plano'] ?? 'trial',
            'status' => $data['status'] ?? 'ativa',
            'limite_usuarios' => (int) ($data['limite_usuarios'] ?? 1),
            'limite_contas_ads' => (int) ($data['limite_contas_ads'] ?? 1),
            'trial_ate' => $data['trial_ate'] ?? null,
            'assinatura_ate' => $data['assinatura_ate'] ?? null,
            'is_root' => (int) ($data['is_root'] ?? 0),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET
                nome_fantasia = :nome_fantasia,
                razao_social = :razao_social,
                documento = :documento,
                email = :email,
                telefone = :telefone,
                slug = :slug,
                plano = :plano,
                status = :status,
                limite_usuarios = :limite_usuarios,
                limite_contas_ads = :limite_contas_ads,
                trial_ate = :trial_ate,
                assinatura_ate = :assinatura_ate,
                is_root = :is_root
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'nome_fantasia' => $data['nome_fantasia'],
            'razao_social' => $data['razao_social'] ?? null,
            'documento' => $data['documento'] ?? null,
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'slug' => $data['slug'],
            'plano' => $data['plano'] ?? 'trial',
            'status' => $data['status'] ?? 'ativa',
            'limite_usuarios' => (int) ($data['limite_usuarios'] ?? 1),
            'limite_contas_ads' => (int) ($data['limite_contas_ads'] ?? 1),
            'trial_ate' => $data['trial_ate'] ?? null,
            'assinatura_ate' => $data['assinatura_ate'] ?? null,
            'is_root' => (int) ($data['is_root'] ?? 0),
        ]);
    }

    public function updateStatus(int $empresaId, string $status): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET status = :status
            WHERE id = :empresa_id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            'status' => $status,
            'empresa_id' => $empresaId,
        ]);
    }

    public function getResumoConsumo(int $empresaId): array
    {
        $sqlUsuarios = "
            SELECT COUNT(*)
            FROM usuarios_empresas
            WHERE empresa_id = :empresa_id
              AND status = 'ativo'
        ";

        $stmtUsuarios = $this->conn->prepare($sqlUsuarios);
        $stmtUsuarios->execute(['empresa_id' => $empresaId]);
        $usuariosAtivos = (int) $stmtUsuarios->fetchColumn();

        $sqlContas = "
            SELECT COUNT(*)
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND ativo = 1
        ";

        $stmtContas = $this->conn->prepare($sqlContas);
        $stmtContas->execute(['empresa_id' => $empresaId]);
        $contasAtivas = (int) $stmtContas->fetchColumn();

        $empresa = $this->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $limiteUsuarios = (int) ($empresa['limite_usuarios'] ?? 0);
        $limiteContasAds = (int) ($empresa['limite_contas_ads'] ?? 0);

        return [
            'usuarios' => [
                'usados' => $usuariosAtivos,
                'limite' => $limiteUsuarios,
                'disponivel' => max(0, $limiteUsuarios - $usuariosAtivos),
            ],
            'contas_ads' => [
                'usadas' => $contasAtivas,
                'limite' => $limiteContasAds,
                'disponivel' => max(0, $limiteContasAds - $contasAtivas),
            ],
        ];
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= " AND id != :ignore_id";
            $params['ignore_id'] = $ignoreId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}