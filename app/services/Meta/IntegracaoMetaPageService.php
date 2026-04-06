<?php

class IntegracaoMetaPageService
{
    private PDO $conn;
    private int $empresaId;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
    }

    public function getPageData(array $query): array
    {
        $clientes = $this->fetchClientes();
        $clienteId = isset($query['cliente_id'])
            ? (int) $query['cliente_id']
            : (int) ($clientes[0]['id'] ?? 0);

        $clienteSelecionado = null;
        foreach ($clientes as $cliente) {
            if ((int) ($cliente['id'] ?? 0) === $clienteId) {
                $clienteSelecionado = $cliente;
                break;
            }
        }

        $tokenMeta = $clienteId > 0 ? $this->fetchToken($clienteId) : null;

        return [
            'clientes' => $clientes,
            'cliente_id' => $clienteId,
            'cliente_selecionado' => $clienteSelecionado,
            'token_meta' => $tokenMeta,
            'conectado' => !empty($tokenMeta),
        ];
    }

    private function fetchClientes(): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, nome
            FROM clientes
            WHERE empresa_id = :empresa_id
            ORDER BY nome ASC
        ");
        $stmt->execute([
            ':empresa_id' => $this->empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchToken(int $clienteId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, meta_user_id, expires_at
            FROM meta_tokens
            WHERE empresa_id = :empresa_id
              AND cliente_id = :cliente_id
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
