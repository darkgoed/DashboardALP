<?php

class MercadoPhoneService
{
    private PDO $conn;
    private bool $schemaReady = false;
    private string $baseUrl = 'https://app.mercadophone.tech/api.php';
    private int $defaultLimit = 300;
    private int $maxPages = 200;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS mercado_phone_integracoes (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                empresa_id BIGINT(20) UNSIGNED NOT NULL,
                cliente_id INT(11) NOT NULL,
                conta_id INT(11) DEFAULT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 0,
                exibir_dashboard TINYINT(1) NOT NULL DEFAULT 1,
                exibir_relatorios TINYINT(1) NOT NULL DEFAULT 1,
                api_token TEXT DEFAULT NULL,
                ultima_sync_produtos_em DATETIME DEFAULT NULL,
                ultima_sync_clientes_em DATETIME DEFAULT NULL,
                ultima_sync_vendas_em DATETIME DEFAULT NULL,
                ultimo_erro_sync TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_mp_integracao_empresa (empresa_id),
                KEY idx_mp_integracao_empresa_cliente (empresa_id, cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumnIfMissing('mercado_phone_integracoes', 'conta_id', 'INT(11) DEFAULT NULL AFTER cliente_id');
        $this->addColumnIfMissing('mercado_phone_integracoes', 'exibir_dashboard', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER ativo');
        $this->addColumnIfMissing('mercado_phone_integracoes', 'exibir_relatorios', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER exibir_dashboard');
        $this->addColumnIfMissing('mercado_phone_integracoes', 'ultima_sync_produtos_em', 'DATETIME DEFAULT NULL');
        $this->addColumnIfMissing('mercado_phone_integracoes', 'ultima_sync_clientes_em', 'DATETIME DEFAULT NULL');
        $this->addColumnIfMissing('mercado_phone_integracoes', 'ultima_sync_vendas_em', 'DATETIME DEFAULT NULL');
        $this->addColumnIfMissing('mercado_phone_integracoes', 'ultimo_erro_sync', 'TEXT DEFAULT NULL');
        $this->dropIndexIfExists('mercado_phone_integracoes', 'uq_mp_integracao_empresa_cliente');
        $this->dropIndexIfExists('mercado_phone_integracoes', 'uq_mp_integracao_empresa_conta');
        $this->ensureIndex(
            'mercado_phone_integracoes',
            'idx_mp_integracao_empresa_conta',
            'KEY idx_mp_integracao_empresa_conta (empresa_id, conta_id)'
        );

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS mercado_phone_metricas_diarias (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                empresa_id BIGINT(20) UNSIGNED NOT NULL,
                cliente_id INT(11) NOT NULL,
                conta_id INT(11) DEFAULT NULL,
                integracao_id INT(11) DEFAULT NULL,
                data DATE NOT NULL,
                pedidos INT(11) NOT NULL DEFAULT 0,
                faturamento DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                itens_vendidos INT(11) NOT NULL DEFAULT 0,
                cancelados INT(11) NOT NULL DEFAULT 0,
                devolucoes INT(11) NOT NULL DEFAULT 0,
                ticket_medio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addColumnIfMissing('mercado_phone_metricas_diarias', 'conta_id', 'INT(11) DEFAULT NULL AFTER cliente_id');
        $this->addColumnIfMissing('mercado_phone_metricas_diarias', 'integracao_id', 'INT(11) DEFAULT NULL AFTER conta_id');
        $this->dropIndexIfExists('mercado_phone_metricas_diarias', 'uq_mp_metricas_empresa_cliente_data');
        $this->dropIndexIfExists('mercado_phone_metricas_diarias', 'idx_mp_metricas_empresa_cliente_data');
        $this->ensureIndex(
            'mercado_phone_metricas_diarias',
            'uq_mp_metricas_empresa_integracao_data',
            'UNIQUE KEY uq_mp_metricas_empresa_integracao_data (empresa_id, integracao_id, data)'
        );
        $this->ensureIndex(
            'mercado_phone_metricas_diarias',
            'idx_mp_metricas_empresa_conta_data',
            'KEY idx_mp_metricas_empresa_conta_data (empresa_id, conta_id, data)'
        );

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS mercado_phone_produtos (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                empresa_id BIGINT(20) UNSIGNED NOT NULL,
                cliente_id INT(11) NOT NULL,
                conta_id INT(11) DEFAULT NULL,
                integracao_id INT(11) DEFAULT NULL,
                mp_id VARCHAR(50) NOT NULL,
                imei VARCHAR(80) DEFAULT NULL,
                codigo_produto VARCHAR(120) DEFAULT NULL,
                nome VARCHAR(255) DEFAULT NULL,
                quantidade INT(11) NOT NULL DEFAULT 0,
                valor_custo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                valor_venda DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                data_entrada DATETIME DEFAULT NULL,
                data_modificacao DATETIME DEFAULT NULL,
                raw_json LONGTEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addColumnIfMissing('mercado_phone_produtos', 'conta_id', 'INT(11) DEFAULT NULL AFTER cliente_id');
        $this->addColumnIfMissing('mercado_phone_produtos', 'integracao_id', 'INT(11) DEFAULT NULL AFTER conta_id');
        $this->dropIndexIfExists('mercado_phone_produtos', 'uq_mp_produtos_empresa_cliente_mp_id');
        $this->dropIndexIfExists('mercado_phone_produtos', 'idx_mp_produtos_empresa_cliente');
        $this->dropIndexIfExists('mercado_phone_produtos', 'idx_mp_produtos_data_modificacao');
        $this->ensureIndex(
            'mercado_phone_produtos',
            'uq_mp_produtos_empresa_integracao_mp_id',
            'UNIQUE KEY uq_mp_produtos_empresa_integracao_mp_id (empresa_id, integracao_id, mp_id)'
        );
        $this->ensureIndex(
            'mercado_phone_produtos',
            'idx_mp_produtos_empresa_conta',
            'KEY idx_mp_produtos_empresa_conta (empresa_id, conta_id)'
        );
        $this->ensureIndex(
            'mercado_phone_produtos',
            'idx_mp_produtos_data_modificacao',
            'KEY idx_mp_produtos_data_modificacao (empresa_id, integracao_id, data_modificacao)'
        );

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS mercado_phone_clientes (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                empresa_id BIGINT(20) UNSIGNED NOT NULL,
                cliente_id INT(11) NOT NULL,
                conta_id INT(11) DEFAULT NULL,
                integracao_id INT(11) DEFAULT NULL,
                mp_id VARCHAR(50) NOT NULL,
                nome VARCHAR(255) DEFAULT NULL,
                documento VARCHAR(50) DEFAULT NULL,
                email VARCHAR(190) DEFAULT NULL,
                telefone VARCHAR(50) DEFAULT NULL,
                data_modificacao DATETIME DEFAULT NULL,
                raw_json LONGTEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addColumnIfMissing('mercado_phone_clientes', 'conta_id', 'INT(11) DEFAULT NULL AFTER cliente_id');
        $this->addColumnIfMissing('mercado_phone_clientes', 'integracao_id', 'INT(11) DEFAULT NULL AFTER conta_id');
        $this->dropIndexIfExists('mercado_phone_clientes', 'uq_mp_clientes_empresa_cliente_mp_id');
        $this->dropIndexIfExists('mercado_phone_clientes', 'idx_mp_clientes_empresa_cliente');
        $this->dropIndexIfExists('mercado_phone_clientes', 'idx_mp_clientes_data_modificacao');
        $this->ensureIndex(
            'mercado_phone_clientes',
            'uq_mp_clientes_empresa_integracao_mp_id',
            'UNIQUE KEY uq_mp_clientes_empresa_integracao_mp_id (empresa_id, integracao_id, mp_id)'
        );
        $this->ensureIndex(
            'mercado_phone_clientes',
            'idx_mp_clientes_empresa_conta',
            'KEY idx_mp_clientes_empresa_conta (empresa_id, conta_id)'
        );
        $this->ensureIndex(
            'mercado_phone_clientes',
            'idx_mp_clientes_data_modificacao',
            'KEY idx_mp_clientes_data_modificacao (empresa_id, integracao_id, data_modificacao)'
        );

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS mercado_phone_vendas (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                empresa_id BIGINT(20) UNSIGNED NOT NULL,
                cliente_id INT(11) NOT NULL,
                conta_id INT(11) DEFAULT NULL,
                integracao_id INT(11) DEFAULT NULL,
                mp_id VARCHAR(50) NOT NULL,
                numero VARCHAR(80) DEFAULT NULL,
                status VARCHAR(80) DEFAULT NULL,
                valor_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                itens_total INT(11) NOT NULL DEFAULT 0,
                cancelado TINYINT(1) NOT NULL DEFAULT 0,
                devolvido TINYINT(1) NOT NULL DEFAULT 0,
                data_venda DATETIME DEFAULT NULL,
                data_modificacao DATETIME DEFAULT NULL,
                raw_json LONGTEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addColumnIfMissing('mercado_phone_vendas', 'conta_id', 'INT(11) DEFAULT NULL AFTER cliente_id');
        $this->addColumnIfMissing('mercado_phone_vendas', 'integracao_id', 'INT(11) DEFAULT NULL AFTER conta_id');
        $this->dropIndexIfExists('mercado_phone_vendas', 'uq_mp_vendas_empresa_cliente_mp_id');
        $this->dropIndexIfExists('mercado_phone_vendas', 'idx_mp_vendas_empresa_cliente');
        $this->dropIndexIfExists('mercado_phone_vendas', 'idx_mp_vendas_data_venda');
        $this->dropIndexIfExists('mercado_phone_vendas', 'idx_mp_vendas_data_modificacao');
        $this->ensureIndex(
            'mercado_phone_vendas',
            'uq_mp_vendas_empresa_integracao_mp_id',
            'UNIQUE KEY uq_mp_vendas_empresa_integracao_mp_id (empresa_id, integracao_id, mp_id)'
        );
        $this->ensureIndex(
            'mercado_phone_vendas',
            'idx_mp_vendas_empresa_conta',
            'KEY idx_mp_vendas_empresa_conta (empresa_id, conta_id)'
        );
        $this->ensureIndex(
            'mercado_phone_vendas',
            'idx_mp_vendas_data_venda',
            'KEY idx_mp_vendas_data_venda (empresa_id, integracao_id, data_venda)'
        );
        $this->ensureIndex(
            'mercado_phone_vendas',
            'idx_mp_vendas_data_modificacao',
            'KEY idx_mp_vendas_data_modificacao (empresa_id, integracao_id, data_modificacao)'
        );

        $this->backfillDataTables();
        $this->schemaReady = true;
    }

    public function listConfigs(int $empresaId, ?int $clienteId = null): array
    {
        $this->ensureSchema();

        $sql = "
            SELECT
                mpi.*,
                c.nome AS cliente_nome,
                ca.nome AS conta_nome,
                ca.meta_account_id
            FROM mercado_phone_integracoes mpi
            LEFT JOIN clientes c
                ON c.id = mpi.cliente_id
               AND c.empresa_id = mpi.empresa_id
            LEFT JOIN contas_ads ca
                ON ca.id = mpi.conta_id
               AND ca.empresa_id = mpi.empresa_id
            WHERE mpi.empresa_id = :empresa_id
        ";

        $params = [':empresa_id' => $empresaId];

        if ($clienteId !== null && $clienteId > 0) {
            $sql .= " AND mpi.cliente_id = :cliente_id";
            $params[':cliente_id'] = $clienteId;
        }

        $sql .= " ORDER BY c.nome ASC, ca.nome ASC, mpi.id ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $this->normalizeConfigRow($row);
        }

        return $rows;
    }

    public function getConfigById(int $empresaId, int $integracaoId): ?array
    {
        $this->ensureSchema();

        if ($integracaoId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM mercado_phone_integracoes
            WHERE empresa_id = :empresa_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $integracaoId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $this->normalizeConfigRow($row);

        return $row;
    }

    public function saveConfigs(int $empresaId, array $configs): array
    {
        $this->ensureSchema();

        $salvos = 0;
        $ignorados = 0;
        $ids = [];

        $this->conn->beginTransaction();

        try {
            foreach ($configs as $config) {
                $clienteId = (int) ($config['cliente_id'] ?? 0);
                $contaId = (int) ($config['conta_id'] ?? 0);
                $ativo = !empty($config['ativo']);
                $exibirDashboard = !empty($config['exibir_dashboard']);
                $exibirRelatorios = !empty($config['exibir_relatorios']);
                $apiToken = trim((string) ($config['api_token'] ?? ''));

                if ($clienteId <= 0 && $contaId <= 0 && $apiToken === '') {
                    $ignorados++;
                    continue;
                }

                if ($clienteId <= 0) {
                    throw new InvalidArgumentException('Selecione um cliente valido para cada API do Mercado Phone.');
                }

                if ($contaId <= 0) {
                    throw new InvalidArgumentException('Selecione uma conta de anuncio valida para cada API do Mercado Phone.');
                }

                $conta = $this->getConta($empresaId, $contaId);

                if (!$conta) {
                    throw new InvalidArgumentException('Conta de anuncio invalida para o Mercado Phone.');
                }

                if ((int) ($conta['cliente_id'] ?? 0) !== $clienteId) {
                    throw new InvalidArgumentException('A conta selecionada nao pertence ao cliente informado.');
                }

                $integracaoId = $this->saveConfigEntry(
                    $empresaId,
                    (int) ($config['id'] ?? 0),
                    $clienteId,
                    $contaId,
                    $ativo,
                    $exibirDashboard,
                    $exibirRelatorios,
                    $apiToken
                );

                $ids[] = $integracaoId;
                $salvos++;
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }

        return [
            'salvos' => $salvos,
            'ignorados' => $ignorados,
            'ids' => $ids,
        ];
    }

    public function isEnabledForContext(int $empresaId, ?int $clienteId = null, ?int $contaId = null): bool
    {
        return !empty($this->getVisibleIntegrationsForContext($empresaId, $clienteId, $contaId, 'dashboard'));
    }

    public function getResumoForContext(
        int $empresaId,
        ?int $clienteId,
        ?int $contaId,
        string $dataInicio,
        string $dataFim,
        string $scope = 'dashboard'
    ): ?array {
        $this->ensureSchema();

        $integracoes = $this->getVisibleIntegrationsForContext($empresaId, $clienteId, $contaId, $scope);

        if (empty($integracoes)) {
            return null;
        }

        $integrationIds = array_values(array_unique(array_map(
            static fn(array $item): int => (int) $item['id'],
            $integracoes
        )));

        $placeholders = implode(',', array_fill(0, count($integrationIds), '?'));

        $sql = "
            SELECT
                COALESCE(SUM(pedidos), 0) AS pedidos,
                COALESCE(SUM(faturamento), 0) AS faturamento,
                COALESCE(SUM(itens_vendidos), 0) AS itens_vendidos,
                COALESCE(SUM(cancelados), 0) AS cancelados,
                COALESCE(SUM(devolucoes), 0) AS devolucoes,
                COUNT(*) AS dias_com_dados
            FROM mercado_phone_metricas_diarias
            WHERE empresa_id = ?
              AND integracao_id IN ($placeholders)
              AND data BETWEEN ? AND ?
        ";

        $params = array_merge([$empresaId], $integrationIds, [$dataInicio, $dataFim]);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $pedidos = (int) ($row['pedidos'] ?? 0);
        $faturamento = (float) ($row['faturamento'] ?? 0);

        return [
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'integracoes' => count($integrationIds),
            'pedidos' => $pedidos,
            'faturamento' => $faturamento,
            'itens_vendidos' => (int) ($row['itens_vendidos'] ?? 0),
            'cancelados' => (int) ($row['cancelados'] ?? 0),
            'devolucoes' => (int) ($row['devolucoes'] ?? 0),
            'ticket_medio' => $pedidos > 0 ? $faturamento / $pedidos : 0.0,
            'has_data' => !empty($row['dias_com_dados']),
            'integration_active' => true,
        ];
    }

    public function syncNow(int $empresaId, int $integracaoId, bool $forceFull = false): array
    {
        $this->ensureSchema();

        $config = $this->getConfigById($empresaId, $integracaoId);

        if (!$this->isConfigEnabled($config)) {
            throw new Exception('Mercado Phone inativo ou sem token configurado para esta integracao.');
        }

        $token = (string) ($config['api_token'] ?? '');
        $clienteId = (int) ($config['cliente_id'] ?? 0);
        $contaId = !empty($config['conta_id']) ? (int) $config['conta_id'] : null;

        $resultado = [
            'integracao_id' => $integracaoId,
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'produtos' => $this->syncProdutos(
                $empresaId,
                $integracaoId,
                $clienteId,
                $contaId,
                $token,
                $forceFull ? null : ($config['ultima_sync_produtos_em'] ?? null)
            ),
            'clientes' => $this->syncClientes(
                $empresaId,
                $integracaoId,
                $clienteId,
                $contaId,
                $token,
                $forceFull ? null : ($config['ultima_sync_clientes_em'] ?? null)
            ),
            'vendas' => $this->syncVendas(
                $empresaId,
                $integracaoId,
                $clienteId,
                $contaId,
                $token,
                $forceFull ? null : ($config['ultima_sync_vendas_em'] ?? null)
            ),
        ];

        $erroResumo = $this->buildErrorSummary($resultado);

        $stmt = $this->conn->prepare("
            UPDATE mercado_phone_integracoes
            SET ultima_sync_produtos_em = CASE WHEN :produtos_ok = 1 THEN NOW() ELSE ultima_sync_produtos_em END,
                ultima_sync_clientes_em = CASE WHEN :clientes_ok = 1 THEN NOW() ELSE ultima_sync_clientes_em END,
                ultima_sync_vendas_em = CASE WHEN :vendas_ok = 1 THEN NOW() ELSE ultima_sync_vendas_em END,
                ultimo_erro_sync = :ultimo_erro_sync,
                updated_at = NOW()
            WHERE empresa_id = :empresa_id
              AND id = :integracao_id
        ");
        $stmt->execute([
            ':produtos_ok' => (($resultado['produtos']['status'] ?? '') === 'success') ? 1 : 0,
            ':clientes_ok' => (($resultado['clientes']['status'] ?? '') === 'success') ? 1 : 0,
            ':vendas_ok' => (($resultado['vendas']['status'] ?? '') === 'success') ? 1 : 0,
            ':ultimo_erro_sync' => $erroResumo,
            ':empresa_id' => $empresaId,
            ':integracao_id' => $integracaoId,
        ]);

        return $resultado;
    }

    private function saveConfigEntry(
        int $empresaId,
        int $integracaoId,
        int $clienteId,
        int $contaId,
        bool $ativo,
        bool $exibirDashboard,
        bool $exibirRelatorios,
        string $apiToken
    ): int {
        $apiToken = trim($apiToken);
        $existente = $integracaoId > 0 ? $this->getConfigById($empresaId, $integracaoId) : null;

        if ($existente) {
            $stmt = $this->conn->prepare("
                UPDATE mercado_phone_integracoes
                SET cliente_id = :cliente_id,
                    conta_id = :conta_id,
                    ativo = :ativo,
                    exibir_dashboard = :exibir_dashboard,
                    exibir_relatorios = :exibir_relatorios,
                    api_token = :api_token,
                    updated_at = NOW()
                WHERE empresa_id = :empresa_id
                  AND id = :id
            ");

            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':conta_id' => $contaId,
                ':ativo' => $ativo ? 1 : 0,
                ':exibir_dashboard' => $exibirDashboard ? 1 : 0,
                ':exibir_relatorios' => $exibirRelatorios ? 1 : 0,
                ':api_token' => $apiToken !== '' ? $apiToken : null,
                ':empresa_id' => $empresaId,
                ':id' => $integracaoId,
            ]);

            $this->syncIntegrationBindings($empresaId, $integracaoId, $clienteId, $contaId);

            return $integracaoId;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO mercado_phone_integracoes (
                empresa_id,
                cliente_id,
                conta_id,
                ativo,
                exibir_dashboard,
                exibir_relatorios,
                api_token,
                created_at,
                updated_at
            ) VALUES (
                :empresa_id,
                :cliente_id,
                :conta_id,
                :ativo,
                :exibir_dashboard,
                :exibir_relatorios,
                :api_token,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':conta_id' => $contaId,
            ':ativo' => $ativo ? 1 : 0,
            ':exibir_dashboard' => $exibirDashboard ? 1 : 0,
            ':exibir_relatorios' => $exibirRelatorios ? 1 : 0,
            ':api_token' => $apiToken !== '' ? $apiToken : null,
        ]);

        $novoId = (int) $this->conn->lastInsertId();

        return $novoId;
    }

    private function getVisibleIntegrationsForContext(
        int $empresaId,
        ?int $clienteId,
        ?int $contaId,
        string $scope
    ): array {
        $this->ensureSchema();

        $campoVisibilidade = $scope === 'relatorios' ? 'exibir_relatorios' : 'exibir_dashboard';
        $sql = "
            SELECT *
            FROM mercado_phone_integracoes
            WHERE empresa_id = :empresa_id
              AND ativo = 1
              AND api_token IS NOT NULL
              AND TRIM(api_token) <> ''
              AND {$campoVisibilidade} = 1
        ";
        $params = [':empresa_id' => $empresaId];

        if ($contaId !== null && $contaId > 0) {
            $clienteResolvido = $this->resolveClienteId($empresaId, $clienteId, $contaId);
            $sql .= " AND (conta_id = :conta_id";
            $params[':conta_id'] = $contaId;

            if ($clienteResolvido) {
                $sql .= " OR (conta_id IS NULL AND cliente_id = :cliente_id_legacy)";
                $params[':cliente_id_legacy'] = $clienteResolvido;
            }

            $sql .= ")";
        } elseif ($clienteId !== null && $clienteId > 0) {
            $sql .= " AND cliente_id = :cliente_id";
            $params[':cliente_id'] = $clienteId;
        }

        $sql .= " ORDER BY id ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $this->normalizeConfigRow($row);
        }

        return $rows;
    }

    public function resolveClienteId(int $empresaId, ?int $clienteId = null, ?int $contaId = null): ?int
    {
        $this->ensureSchema();

        if (!empty($clienteId)) {
            return (int) $clienteId;
        }

        if (empty($contaId)) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT cliente_id
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND id = :conta_id
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);

        $clienteIdResolvido = $stmt->fetchColumn();

        return $clienteIdResolvido ? (int) $clienteIdResolvido : null;
    }

    private function syncProdutos(
        int $empresaId,
        int $integracaoId,
        int $clienteId,
        ?int $contaId,
        string $token,
        ?string $ultimaSync
    ): array {
        try {
            $itens = $this->fetchAllPages($token, 'EstoqueApiController', 'index');
            $itens = $this->filterByLastSync($itens, $ultimaSync, ['dataModificacao', 'dataEntrada']);

            $salvos = 0;
            foreach ($itens as $item) {
                $this->upsertProduto($empresaId, $integracaoId, $clienteId, $contaId, $item);
                $salvos++;
            }

            return [
                'status' => 'success',
                'controller' => 'EstoqueApiController',
                'recebidos' => count($itens),
                'salvos' => $salvos,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'controller' => 'EstoqueApiController',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function syncClientes(
        int $empresaId,
        int $integracaoId,
        int $clienteId,
        ?int $contaId,
        string $token,
        ?string $ultimaSync
    ): array {
        $controllers = ['ClienteApiController', 'PessoaApiController'];

        foreach ($controllers as $controller) {
            try {
                $itens = $this->fetchAllPages($token, $controller, 'index');
                $itens = $this->filterByLastSync($itens, $ultimaSync, ['dataModificacao', 'data_ultima_compra', 'updated_at', 'created_at']);

                $salvos = 0;
                foreach ($itens as $item) {
                    $this->upsertClienteExterno($empresaId, $integracaoId, $clienteId, $contaId, $item);
                    $salvos++;
                }

                return [
                    'status' => 'success',
                    'controller' => $controller,
                    'recebidos' => count($itens),
                    'salvos' => $salvos,
                ];
            } catch (Throwable $e) {
                $ultimaMensagem = $e->getMessage();
            }
        }

        return [
            'status' => 'error',
            'message' => $ultimaMensagem ?? 'Nenhum controller de clientes respondeu com sucesso.',
        ];
    }

    private function syncVendas(
        int $empresaId,
        int $integracaoId,
        int $clienteId,
        ?int $contaId,
        string $token,
        ?string $ultimaSync
    ): array {
        $controllers = ['VendaApiController', 'OrdemServicoApiController'];

        foreach ($controllers as $controller) {
            try {
                $itens = $this->fetchAllPages($token, $controller, 'index');
                $itens = $this->filterByLastSync($itens, $ultimaSync, ['dataModificacao', 'dataVenda', 'dataFinalizacao', 'dataCriacao', 'dataEntrada']);

                $salvos = 0;
                foreach ($itens as $item) {
                    $this->upsertVenda($empresaId, $integracaoId, $clienteId, $contaId, $item);
                    $salvos++;
                }

                $this->rebuildMetricasDiarias($empresaId, $integracaoId, $clienteId, $contaId);

                return [
                    'status' => 'success',
                    'controller' => $controller,
                    'recebidos' => count($itens),
                    'salvos' => $salvos,
                ];
            } catch (Throwable $e) {
                $ultimaMensagem = $e->getMessage();
            }
        }

        return [
            'status' => 'error',
            'message' => $ultimaMensagem ?? 'Nenhum controller de vendas respondeu com sucesso.',
        ];
    }

    private function fetchAllPages(string $token, string $controller, string $method = 'index'): array
    {
        $allItems = [];

        for ($page = 1; $page <= $this->maxPages; $page++) {
            $payload = [
                'page' => $page,
                'limit' => $this->defaultLimit,
                'order' => 'id',
                'direction' => 'desc',
                'filters' => new stdClass(),
            ];

            $response = $this->request($token, $controller, $method, $payload);
            $items = $this->extractItems($response);

            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $allItems[] = $item;
                }
            }

            if (count($items) < $this->defaultLimit) {
                break;
            }
        }

        return $allItems;
    }

    private function request(string $token, string $controller, string $method, array $payload): array
    {
        $url = $this->baseUrl . '?' . http_build_query([
            'class' => $controller,
            'method' => $method,
        ]);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: ' . $token,
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            curl_close($ch);
            throw new Exception('Erro cURL Mercado Phone: ' . $curlError);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new Exception('Resposta invalida do Mercado Phone em ' . $controller . '::' . $method . '.');
        }

        if ($httpCode >= 400) {
            throw new Exception('Mercado Phone HTTP ' . $httpCode . ' em ' . $controller . '::' . $method . '.');
        }

        if (($decoded['status'] ?? null) === 'error') {
            $message = $decoded['message'] ?? $decoded['error'] ?? 'Erro desconhecido no Mercado Phone.';
            throw new Exception((string) $message);
        }

        return $decoded;
    }

    private function extractItems(array $response): array
    {
        if (
            isset($response['data']) &&
            is_array($response['data']) &&
            isset($response['data']['itens']) &&
            is_array($response['data']['itens'])
        ) {
            return $response['data']['itens'];
        }

        $keys = ['data', 'items', 'rows', 'records', 'result', 'results'];

        foreach ($keys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_is_list($response[$key]) ? $response[$key] : [];
            }
        }

        if (array_is_list($response)) {
            return $response;
        }

        return [];
    }

    private function filterByLastSync(array $items, ?string $ultimaSync, array $candidateFields): array
    {
        if (!$ultimaSync) {
            return $items;
        }

        $syncTs = strtotime($ultimaSync);

        if (!$syncTs) {
            return $items;
        }

        $filtered = [];

        foreach ($items as $item) {
            $maisRecente = null;

            foreach ($candidateFields as $field) {
                $value = $item[$field] ?? null;
                $ts = $value ? strtotime((string) $value) : false;

                if ($ts && ($maisRecente === null || $ts > $maisRecente)) {
                    $maisRecente = $ts;
                }
            }

            if ($maisRecente === null || $maisRecente > $syncTs) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    private function upsertProduto(int $empresaId, int $integracaoId, int $clienteId, ?int $contaId, array $item): void
    {
        $mpId = trim((string) ($item['id'] ?? ''));

        if ($mpId === '') {
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO mercado_phone_produtos (
                empresa_id, cliente_id, conta_id, integracao_id, mp_id, imei, codigo_produto, nome, quantidade,
                valor_custo, valor_venda, data_entrada, data_modificacao, raw_json,
                created_at, updated_at
            ) VALUES (
                :empresa_id, :cliente_id, :conta_id, :integracao_id, :mp_id, :imei, :codigo_produto, :nome, :quantidade,
                :valor_custo, :valor_venda, :data_entrada, :data_modificacao, :raw_json,
                NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                cliente_id = VALUES(cliente_id),
                conta_id = VALUES(conta_id),
                imei = VALUES(imei),
                codigo_produto = VALUES(codigo_produto),
                nome = VALUES(nome),
                quantidade = VALUES(quantidade),
                valor_custo = VALUES(valor_custo),
                valor_venda = VALUES(valor_venda),
                data_entrada = VALUES(data_entrada),
                data_modificacao = VALUES(data_modificacao),
                raw_json = VALUES(raw_json),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':conta_id' => $contaId,
            ':integracao_id' => $integracaoId,
            ':mp_id' => $mpId,
            ':imei' => $this->nullableString($item['imei'] ?? null),
            ':codigo_produto' => $this->nullableString($item['codigoProduto'] ?? $item['codigo'] ?? null),
            ':nome' => $this->nullableString($item['nome'] ?? $item['descricao'] ?? null),
            ':quantidade' => (int) ($item['quantidade'] ?? 0),
            ':valor_custo' => $this->decimal($item['valorCusto'] ?? 0),
            ':valor_venda' => $this->decimal($item['valorVenda'] ?? 0),
            ':data_entrada' => $this->normalizeDateTime($item['dataEntrada'] ?? null),
            ':data_modificacao' => $this->normalizeDateTime($item['dataModificacao'] ?? null),
            ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function upsertClienteExterno(int $empresaId, int $integracaoId, int $clienteId, ?int $contaId, array $item): void
    {
        $mpId = trim((string) ($item['id'] ?? ''));

        if ($mpId === '') {
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO mercado_phone_clientes (
                empresa_id, cliente_id, conta_id, integracao_id, mp_id, nome, documento, email, telefone,
                data_modificacao, raw_json, created_at, updated_at
            ) VALUES (
                :empresa_id, :cliente_id, :conta_id, :integracao_id, :mp_id, :nome, :documento, :email, :telefone,
                :data_modificacao, :raw_json, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                cliente_id = VALUES(cliente_id),
                conta_id = VALUES(conta_id),
                nome = VALUES(nome),
                documento = VALUES(documento),
                email = VALUES(email),
                telefone = VALUES(telefone),
                data_modificacao = VALUES(data_modificacao),
                raw_json = VALUES(raw_json),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':conta_id' => $contaId,
            ':integracao_id' => $integracaoId,
            ':mp_id' => $mpId,
            ':nome' => $this->nullableString($item['nome'] ?? $item['razaoSocial'] ?? $item['fantasia'] ?? null),
            ':documento' => $this->nullableString($item['cpfCnpj'] ?? $item['documento'] ?? null),
            ':email' => $this->nullableString($item['email'] ?? null),
            ':telefone' => $this->nullableString($item['telefone'] ?? $item['celular'] ?? null),
            ':data_modificacao' => $this->normalizeDateTime($item['dataModificacao'] ?? $item['data_ultima_compra'] ?? $item['updated_at'] ?? null),
            ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function upsertVenda(int $empresaId, int $integracaoId, int $clienteId, ?int $contaId, array $item): void
    {
        $mpId = trim((string) ($item['id'] ?? ''));

        if ($mpId === '') {
            return;
        }

        $status = strtolower(trim((string) ($item['status'] ?? $item['situacaoDescricao'] ?? $item['situacao'] ?? '')));
        $itens = isset($item['itens']) && is_array($item['itens']) ? $item['itens'] : [];
        $itensTotal = 0;

        foreach ($itens as $itemVenda) {
            $itensTotal += (int) ($itemVenda['quantidade'] ?? 0);
        }

        $dataVenda = $item['dataVenda'] ?? $item['dataFinalizacao'] ?? $item['dataCriacao'] ?? $item['data'] ?? $item['dataEntrada'] ?? null;
        $valorTotal = $item['totalVenda'] ?? $item['valorTotal'] ?? $item['total'] ?? $item['valor'] ?? 0;

        $stmt = $this->conn->prepare("
            INSERT INTO mercado_phone_vendas (
                empresa_id, cliente_id, conta_id, integracao_id, mp_id, numero, status, valor_total, itens_total,
                cancelado, devolvido, data_venda, data_modificacao, raw_json,
                created_at, updated_at
            ) VALUES (
                :empresa_id, :cliente_id, :conta_id, :integracao_id, :mp_id, :numero, :status, :valor_total, :itens_total,
                :cancelado, :devolvido, :data_venda, :data_modificacao, :raw_json,
                NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                cliente_id = VALUES(cliente_id),
                conta_id = VALUES(conta_id),
                numero = VALUES(numero),
                status = VALUES(status),
                valor_total = VALUES(valor_total),
                itens_total = VALUES(itens_total),
                cancelado = VALUES(cancelado),
                devolvido = VALUES(devolvido),
                data_venda = VALUES(data_venda),
                data_modificacao = VALUES(data_modificacao),
                raw_json = VALUES(raw_json),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':conta_id' => $contaId,
            ':integracao_id' => $integracaoId,
            ':mp_id' => $mpId,
            ':numero' => $this->nullableString($item['numero'] ?? $item['codigo'] ?? null),
            ':status' => $this->nullableString($status),
            ':valor_total' => $this->decimal($valorTotal),
            ':itens_total' => $itensTotal > 0 ? $itensTotal : (int) ($item['quantidadeItens'] ?? $item['itensTotal'] ?? $item['quantidade'] ?? 0),
            ':cancelado' => $this->isCancelledStatus($status) ? 1 : 0,
            ':devolvido' => $this->isReturnedStatus($status) ? 1 : 0,
            ':data_venda' => $this->normalizeDateTime($dataVenda),
            ':data_modificacao' => $this->normalizeDateTime($item['dataModificacao'] ?? $dataVenda),
            ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function rebuildMetricasDiarias(int $empresaId, int $integracaoId, int $clienteId, ?int $contaId): void
    {
        $stmtDelete = $this->conn->prepare("
            DELETE FROM mercado_phone_metricas_diarias
            WHERE empresa_id = :empresa_id
              AND integracao_id = :integracao_id
        ");
        $stmtDelete->execute([
            ':empresa_id' => $empresaId,
            ':integracao_id' => $integracaoId,
        ]);

        $stmt = $this->conn->prepare("
            INSERT INTO mercado_phone_metricas_diarias (
                empresa_id, cliente_id, conta_id, integracao_id, data, pedidos, faturamento, itens_vendidos,
                cancelados, devolucoes, ticket_medio, created_at, updated_at
            )
            SELECT
                empresa_id,
                :cliente_id AS cliente_id,
                :conta_id AS conta_id,
                :integracao_id AS integracao_id,
                DATE(COALESCE(data_venda, data_modificacao, created_at)) AS data_ref,
                COUNT(*) AS pedidos,
                COALESCE(SUM(valor_total), 0) AS faturamento,
                COALESCE(SUM(itens_total), 0) AS itens_vendidos,
                COALESCE(SUM(cancelado), 0) AS cancelados,
                COALESCE(SUM(devolvido), 0) AS devolucoes,
                CASE WHEN COUNT(*) > 0 THEN COALESCE(SUM(valor_total), 0) / COUNT(*) ELSE 0 END AS ticket_medio,
                NOW(),
                NOW()
            FROM mercado_phone_vendas
            WHERE empresa_id = :empresa_id
              AND integracao_id = :integracao_id
              AND COALESCE(data_venda, data_modificacao, created_at) IS NOT NULL
            GROUP BY empresa_id, DATE(COALESCE(data_venda, data_modificacao, created_at))
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':conta_id' => $contaId,
            ':integracao_id' => $integracaoId,
        ]);
    }

    private function buildErrorSummary(array $resultado): ?string
    {
        $messages = [];

        foreach ($resultado as $key => $item) {
            if (is_array($item) && ($item['status'] ?? '') === 'error') {
                $messages[] = $key . ': ' . ($item['message'] ?? 'erro desconhecido');
            }
        }

        if (empty($messages)) {
            return null;
        }

        return implode(' | ', $messages);
    }

    private function normalizeConfigRow(array &$row): void
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['cliente_id'] = (int) ($row['cliente_id'] ?? 0);
        $row['conta_id'] = !empty($row['conta_id']) ? (int) $row['conta_id'] : null;
        $row['ativo'] = !empty($row['ativo']);
        $row['exibir_dashboard'] = !empty($row['exibir_dashboard']);
        $row['exibir_relatorios'] = !empty($row['exibir_relatorios']);
        $row['api_token'] = trim((string) ($row['api_token'] ?? ''));
    }

    private function syncIntegrationBindings(int $empresaId, int $integracaoId, int $clienteId, int $contaId): void
    {
        foreach ([
            'mercado_phone_produtos',
            'mercado_phone_clientes',
            'mercado_phone_vendas',
            'mercado_phone_metricas_diarias',
        ] as $table) {
            $stmt = $this->conn->prepare("
                UPDATE {$table}
                SET cliente_id = :cliente_id,
                    conta_id = :conta_id,
                    integracao_id = :integracao_id
                WHERE empresa_id = :empresa_id
                  AND integracao_id = :integracao_id
            ");
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':conta_id' => $contaId,
                ':integracao_id' => $integracaoId,
                ':empresa_id' => $empresaId,
            ]);
        }
    }

    private function backfillDataTables(): void
    {
        foreach ([
            'mercado_phone_produtos',
            'mercado_phone_clientes',
            'mercado_phone_vendas',
            'mercado_phone_metricas_diarias',
        ] as $table) {
            $stmt = $this->conn->prepare("
                UPDATE {$table} t
                INNER JOIN mercado_phone_integracoes mpi
                    ON mpi.empresa_id = t.empresa_id
                   AND mpi.cliente_id = t.cliente_id
                INNER JOIN (
                    SELECT empresa_id, cliente_id
                    FROM mercado_phone_integracoes
                    GROUP BY empresa_id, cliente_id
                    HAVING COUNT(*) = 1
                ) unico
                    ON unico.empresa_id = mpi.empresa_id
                   AND unico.cliente_id = mpi.cliente_id
                SET t.integracao_id = mpi.id,
                    t.conta_id = COALESCE(t.conta_id, mpi.conta_id)
                WHERE t.integracao_id IS NULL
            ");
            $stmt->execute();
        }
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $this->conn->exec(sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            $table,
            $column,
            $definition
        ));
    }

    private function ensureIndex(string $table, string $indexName, string $definition): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $this->conn->exec(sprintf(
            'ALTER TABLE %s ADD %s',
            $table,
            $definition
        ));
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        $this->conn->exec(sprintf(
            'ALTER TABLE %s DROP INDEX %s',
            $table,
            $indexName
        ));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS total
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':index_name' => $indexName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function getConta(int $empresaId, int $contaId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, cliente_id, nome, meta_account_id
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $contaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function isConfigEnabled(?array $config): bool
    {
        if (!$config) {
            return false;
        }

        return !empty($config['ativo']) && trim((string) ($config['api_token'] ?? '')) !== '';
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function decimal($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function normalizeDateTime($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function isCancelledStatus(string $status): bool
    {
        foreach (['cancel', 'cancelado', 'canceled', 'cancelled'] as $keyword) {
            if ($status !== '' && str_contains($status, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isReturnedStatus(string $status): bool
    {
        foreach (['devol', 'return', 'estorno'] as $keyword) {
            if ($status !== '' && str_contains($status, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
