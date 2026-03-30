<?php

class CanalEmail
{
    private PDO $conn;
    private int $empresaId;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
    }

    public function get(): ?array
    {
        $sql = "SELECT * FROM canais_email WHERE empresa_id = :empresa_id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): bool
    {
        $exists = $this->get();

        if ($exists) {
            $sql = "UPDATE canais_email SET
                        nome_remetente = :nome_remetente,
                        email_remetente = :email_remetente,
                        email_reply_to = :email_reply_to,
                        smtp_host = :smtp_host,
                        smtp_port = :smtp_port,
                        smtp_secure = :smtp_secure,
                        smtp_user = :smtp_user,
                        smtp_pass = :smtp_pass,
                        updated_at = NOW()
                    WHERE empresa_id = :empresa_id";
        } else {
            $sql = "INSERT INTO canais_email (
                        empresa_id,
                        nome_remetente,
                        email_remetente,
                        email_reply_to,
                        smtp_host,
                        smtp_port,
                        smtp_secure,
                        smtp_user,
                        smtp_pass,
                        status_conexao,
                        created_at,
                        updated_at
                    ) VALUES (
                        :empresa_id,
                        :nome_remetente,
                        :email_remetente,
                        :email_reply_to,
                        :smtp_host,
                        :smtp_port,
                        :smtp_secure,
                        :smtp_user,
                        :smtp_pass,
                        'inativo',
                        NOW(),
                        NOW()
                    )";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':nome_remetente', $data['nome_remetente']);
        $stmt->bindValue(':email_remetente', $data['email_remetente']);
        $stmt->bindValue(':email_reply_to', $data['email_reply_to'] ?? null);
        $stmt->bindValue(':smtp_host', $data['smtp_host']);
        $stmt->bindValue(':smtp_port', (int) $data['smtp_port'], PDO::PARAM_INT);
        $stmt->bindValue(':smtp_secure', $data['smtp_secure']);
        $stmt->bindValue(':smtp_user', $data['smtp_user']);
        $stmt->bindValue(':smtp_pass', $data['smtp_pass']);

        return $stmt->execute();
    }

    public function updateStatus(string $status, ?string $erro = null): bool
    {
        $sql = "UPDATE canais_email SET
                    status_conexao = :status,
                    ultimo_teste_em = NOW(),
                    observacao_erro = :erro,
                    updated_at = NOW()
                WHERE empresa_id = :empresa_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':erro', $erro);
        $stmt->bindValue(':empresa_id', $this->empresaId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}