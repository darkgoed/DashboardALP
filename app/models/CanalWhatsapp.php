<?php

class CanalWhatsapp
{
    private PDO $conn;
    private int $empresaId;
    private static bool $schemaReady = false;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->ensureTable();
    }

    public function get(): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM canais_whatsapp WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindValue(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(array $data): bool
    {
        $current = $this->get();

        if ($current) {
            $sql = "
                UPDATE canais_whatsapp
                SET nome_conexao = :nome_conexao,
                    bridge_url = :bridge_url,
                    session_name = :session_name,
                    auth_token = :auth_token,
                    numero_teste_padrao = :numero_teste_padrao,
                    updated_at = NOW()
                WHERE empresa_id = :empresa_id
            ";
        } else {
            $sql = "
                INSERT INTO canais_whatsapp (
                    empresa_id,
                    nome_conexao,
                    bridge_url,
                    session_name,
                    auth_token,
                    numero_teste_padrao,
                    status_conexao,
                    created_at,
                    updated_at
                ) VALUES (
                    :empresa_id,
                    :nome_conexao,
                    :bridge_url,
                    :session_name,
                    :auth_token,
                    :numero_teste_padrao,
                    'inativo',
                    NOW(),
                    NOW()
                )
            ";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':nome_conexao', $data['nome_conexao']);
        $stmt->bindValue(':bridge_url', $data['bridge_url']);
        $stmt->bindValue(':session_name', $data['session_name']);
        $stmt->bindValue(':auth_token', $data['auth_token']);
        $stmt->bindValue(':numero_teste_padrao', $data['numero_teste_padrao'] ?: null);

        return $stmt->execute();
    }

    public function updateStatus(string $status, ?string $erro = null): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE canais_whatsapp
            SET status_conexao = :status,
                ultimo_teste_em = NOW(),
                observacao_erro = :erro,
                updated_at = NOW()
            WHERE empresa_id = :empresa_id
        ");
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':erro', $erro);
        $stmt->bindValue(':empresa_id', $this->empresaId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    private function ensureTable(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS canais_whatsapp (
                id INT NOT NULL AUTO_INCREMENT,
                empresa_id BIGINT UNSIGNED NOT NULL,
                nome_conexao VARCHAR(150) NOT NULL,
                bridge_url VARCHAR(255) NOT NULL,
                session_name VARCHAR(120) NOT NULL,
                auth_token TEXT DEFAULT NULL,
                numero_teste_padrao VARCHAR(30) DEFAULT NULL,
                status_conexao ENUM('ativo', 'inativo', 'erro') NOT NULL DEFAULT 'inativo',
                ultimo_teste_em DATETIME DEFAULT NULL,
                observacao_erro TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_canais_whatsapp_empresa (empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$schemaReady = true;
    }
}
