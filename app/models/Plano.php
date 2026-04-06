<?php

class Plano
{
    private PDO $conn;
    private string $table = 'planos';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);

        $plano = $stmt->fetch(PDO::FETCH_ASSOC);

        return $plano ?: null;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);

        $plano = $stmt->fetch(PDO::FETCH_ASSOC);

        return $plano ?: null;
    }

    public function getAll(): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            ORDER BY nome ASC
        ";

        $stmt = $this->conn->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllAtivos(): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE status = 'ativo'
            ORDER BY nome ASC
        ";

        $stmt = $this->conn->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}