<?php

class RelatorioProgramacao
{
    private PDO $conn;
    private static bool $schemaReady = false;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->ensureTable();
    }

    public function listByEmpresa(int $empresaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM relatorios_programacoes
            WHERE empresa_id = :empresa_id
            ORDER BY ativo DESC, proximo_envio_em ASC, id ASC
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function replaceAllByEmpresa(int $empresaId, array $rows): void
    {
        $this->conn->beginTransaction();

        try {
            $delete = $this->conn->prepare('DELETE FROM relatorios_programacoes WHERE empresa_id = :empresa_id');
            $delete->execute([
                ':empresa_id' => $empresaId,
            ]);

            if ($rows !== []) {
                $insert = $this->conn->prepare("
                    INSERT INTO relatorios_programacoes (
                        empresa_id,
                        cliente_id,
                        conta_id,
                        campanha_id,
                        campanha_status,
                        periodo,
                        data_inicio,
                        data_fim,
                        destino_email,
                        destino_whatsapp,
                        destino_nome,
                        enviar_email,
                        enviar_whatsapp,
                        frequencia_dias,
                        horario_envio,
                        data_inicio_agendamento,
                        proximo_envio_em,
                        ultimo_envio_em,
                        ultimo_status,
                        ultima_mensagem,
                        ativo,
                        created_at,
                        updated_at
                    ) VALUES (
                        :empresa_id,
                        :cliente_id,
                        :conta_id,
                        :campanha_id,
                        :campanha_status,
                        :periodo,
                        :data_inicio,
                        :data_fim,
                        :destino_email,
                        :destino_whatsapp,
                        :destino_nome,
                        :enviar_email,
                        :enviar_whatsapp,
                        :frequencia_dias,
                        :horario_envio,
                        :data_inicio_agendamento,
                        :proximo_envio_em,
                        :ultimo_envio_em,
                        :ultimo_status,
                        :ultima_mensagem,
                        :ativo,
                        NOW(),
                        NOW()
                    )
                ");

                foreach ($rows as $row) {
                    $insert->execute([
                        ':empresa_id' => $empresaId,
                        ':cliente_id' => $row['cliente_id'],
                        ':conta_id' => $row['conta_id'],
                        ':campanha_id' => $row['campanha_id'],
                        ':campanha_status' => $row['campanha_status'],
                        ':periodo' => $row['periodo'],
                        ':data_inicio' => $row['data_inicio'],
                        ':data_fim' => $row['data_fim'],
                        ':destino_email' => $row['destino_email'],
                        ':destino_whatsapp' => $row['destino_whatsapp'],
                        ':destino_nome' => $row['destino_nome'],
                        ':enviar_email' => $row['enviar_email'],
                        ':enviar_whatsapp' => $row['enviar_whatsapp'],
                        ':frequencia_dias' => $row['frequencia_dias'],
                        ':horario_envio' => $row['horario_envio'],
                        ':data_inicio_agendamento' => $row['data_inicio_agendamento'],
                        ':proximo_envio_em' => $row['proximo_envio_em'],
                        ':ultimo_envio_em' => $row['ultimo_envio_em'],
                        ':ultimo_status' => $row['ultimo_status'],
                        ':ultima_mensagem' => $row['ultima_mensagem'],
                        ':ativo' => $row['ativo'],
                    ]);
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

    public function dueForProcessing(DateTimeImmutable $now, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        $stmt = $this->conn->prepare("
            SELECT *
            FROM relatorios_programacoes
            WHERE ativo = 1
              AND proximo_envio_em <= :agora
            ORDER BY proximo_envio_em ASC, id ASC
            LIMIT {$limit}
        ");

        $stmt->execute([
            ':agora' => $now->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateExecution(int $id, int $empresaId, array $data): void
    {
        $stmt = $this->conn->prepare("
            UPDATE relatorios_programacoes
            SET proximo_envio_em = :proximo_envio_em,
                ultimo_envio_em = :ultimo_envio_em,
                ultimo_status = :ultimo_status,
                ultima_mensagem = :ultima_mensagem,
                updated_at = NOW()
            WHERE id = :id
              AND empresa_id = :empresa_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':proximo_envio_em' => $data['proximo_envio_em'],
            ':ultimo_envio_em' => $data['ultimo_envio_em'],
            ':ultimo_status' => $data['ultimo_status'],
            ':ultima_mensagem' => $data['ultima_mensagem'],
        ]);
    }

    public function ensureTable(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS relatorios_programacoes (
                id INT NOT NULL AUTO_INCREMENT,
                empresa_id BIGINT UNSIGNED NOT NULL,
                cliente_id INT DEFAULT NULL,
                conta_id INT DEFAULT NULL,
                campanha_id INT DEFAULT NULL,
                campanha_status VARCHAR(30) DEFAULT NULL,
                periodo VARCHAR(20) NOT NULL DEFAULT '30',
                data_inicio DATE DEFAULT NULL,
                data_fim DATE DEFAULT NULL,
                destino_email VARCHAR(190) DEFAULT NULL,
                destino_whatsapp VARCHAR(30) DEFAULT NULL,
                destino_nome VARCHAR(190) DEFAULT NULL,
                enviar_email TINYINT(1) NOT NULL DEFAULT 1,
                enviar_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
                frequencia_dias SMALLINT UNSIGNED NOT NULL DEFAULT 7,
                horario_envio TIME NOT NULL DEFAULT '07:00:00',
                data_inicio_agendamento DATE DEFAULT NULL,
                proximo_envio_em DATETIME NOT NULL,
                ultimo_envio_em DATETIME DEFAULT NULL,
                ultimo_status VARCHAR(50) DEFAULT NULL,
                ultima_mensagem TEXT DEFAULT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_relatorios_programacoes_empresa (empresa_id),
                KEY idx_relatorios_programacoes_execucao (ativo, proximo_envio_em),
                KEY idx_relatorios_programacoes_cliente (empresa_id, cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumnIfMissing('relatorios_programacoes', 'destino_whatsapp', 'VARCHAR(30) DEFAULT NULL AFTER destino_email');
        $this->addColumnIfMissing('relatorios_programacoes', 'enviar_email', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER destino_nome');
        $this->addColumnIfMissing('relatorios_programacoes', 'enviar_whatsapp', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER enviar_email');
        $this->ensureNullableColumn('relatorios_programacoes', 'destino_email', 'VARCHAR(190) NULL DEFAULT NULL');

        self::$schemaReady = true;
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
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

    private function ensureNullableColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->conn->prepare("
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        $isNullable = $stmt->fetchColumn();
        if ($isNullable === false || strtoupper((string) $isNullable) === 'YES') {
            return;
        }

        $this->conn->exec(sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s %s',
            $table,
            $column,
            $definition
        ));
    }
}
