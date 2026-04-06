<?php

class ClienteWriteService
{
    private int $empresaId;
    private Cliente $clienteModel;
    private EntityDeletionService $entityDeletionService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->empresaId = $empresaId;
        $this->clienteModel = new Cliente($conn, $empresaId);
        $this->entityDeletionService = new EntityDeletionService($conn);
    }

    public function create(array $data): string
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $whatsapp = trim((string) ($data['whatsapp'] ?? ''));

        if ($nome === '') {
            throw new RuntimeException('Preencha o nome do cliente.');
        }

        $this->clienteModel->create($nome, $email, $whatsapp);

        return 'Cliente criado com sucesso.';
    }

    public function update(array $data): string
    {
        $id = (int) ($data['id'] ?? 0);
        $nome = trim((string) ($data['nome'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $whatsapp = trim((string) ($data['whatsapp'] ?? ''));

        if ($id <= 0 || $nome === '') {
            throw new RuntimeException('Dados inválidos para atualizar o cliente.');
        }

        $this->clienteModel->update($id, $nome, $email, $whatsapp);

        return 'Cliente atualizado com sucesso.';
    }

    public function delete(array $data): string
    {
        $id = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Cliente inválido para exclusão.');
        }

        $ok = $this->entityDeletionService->deleteCliente($this->empresaId, $id);

        if (!$ok) {
            throw new RuntimeException('Não foi possível excluir o cliente.');
        }

        return 'Cliente excluído com sucesso.';
    }
}
