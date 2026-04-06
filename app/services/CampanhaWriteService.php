<?php

class CampanhaWriteService
{
    private Campanha $campanhaModel;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->campanhaModel = new Campanha($conn, $empresaId);
    }

    public function create(array $data): string
    {
        $contaId = (int) ($data['conta_id'] ?? 0);
        $nome = trim((string) ($data['nome'] ?? ''));
        $objetivo = trim((string) ($data['objetivo'] ?? ''));

        if ($contaId <= 0 || $nome === '') {
            throw new RuntimeException('Preencha os dados obrigatórios para criar a campanha.');
        }

        $ok = $this->campanhaModel->create($contaId, $nome, $objetivo);

        if (!$ok) {
            throw new RuntimeException('Não foi possível criar a campanha.');
        }

        return 'Campanha criada com sucesso.';
    }

    public function update(array $data): string
    {
        $id = (int) ($data['id'] ?? 0);
        $contaId = (int) ($data['conta_id'] ?? 0);
        $nome = trim((string) ($data['nome'] ?? ''));
        $objetivo = trim((string) ($data['objetivo'] ?? ''));

        if ($id <= 0 || $contaId <= 0 || $nome === '') {
            throw new RuntimeException('Preencha os dados obrigatórios para atualizar a campanha.');
        }

        $ok = $this->campanhaModel->update($id, $contaId, $nome, $objetivo);

        if (!$ok) {
            throw new RuntimeException('Não foi possível atualizar a campanha.');
        }

        return 'Campanha atualizada com sucesso.';
    }

    public function delete(array $data): string
    {
        $id = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Campanha inválida para exclusão.');
        }

        $ok = $this->campanhaModel->delete($id);

        if (!$ok) {
            throw new RuntimeException('Não foi possível excluir a campanha.');
        }

        return 'Campanha excluída com sucesso.';
    }
}
