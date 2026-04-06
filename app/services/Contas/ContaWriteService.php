<?php

class ContaWriteService
{
    private PDO $conn;
    private int $empresaId;
    private ContaAds $contaModel;
    private EntityDeletionService $entityDeletionService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->contaModel = new ContaAds($conn, $empresaId);
        $this->entityDeletionService = new EntityDeletionService($conn);
    }

    public function create(array $data): string
    {
        $clienteId = (int) ($data['cliente_id'] ?? 0);
        $nome = trim((string) ($data['nome'] ?? ''));

        if ($clienteId <= 0 || $nome === '') {
            throw new RuntimeException('Dados inválidos para criar a conta.');
        }

        $limiteService = new EmpresaLimiteService($this->conn);
        $limiteService->validarNovaContaAds($this->empresaId);

        $this->contaModel->create($clienteId, $nome);

        return 'Conta criada com sucesso.';
    }

    public function update(array $data): string
    {
        $id = (int) ($data['id'] ?? 0);
        $clienteId = (int) ($data['cliente_id'] ?? 0);
        $nome = trim((string) ($data['nome'] ?? ''));

        if ($id <= 0 || $clienteId <= 0 || $nome === '') {
            throw new RuntimeException('Dados inválidos para atualizar a conta.');
        }

        $this->contaModel->update($id, $clienteId, $nome);

        return 'Conta atualizada com sucesso.';
    }

    public function delete(array $data): string
    {
        $id = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Conta inválida para exclusão.');
        }

        $ok = $this->entityDeletionService->deleteContaAds($this->empresaId, $id);

        if (!$ok) {
            throw new RuntimeException('Não foi possível excluir a conta.');
        }

        return 'Conta excluída com sucesso.';
    }
}
