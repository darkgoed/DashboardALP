<?php

class ContaManagementService
{
    private PDO $conn;
    private int $empresaId;
    private Cliente $clienteModel;
    private ContaAds $contaModel;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->clienteModel = new Cliente($conn, $empresaId);
        $this->contaModel = new ContaAds($conn, $empresaId);
    }

    public function getPageData(string $acao = '', int $id = 0): array
    {
        $clientes = $this->clienteModel->getAll();
        $contaEdicao = null;

        if ($acao === 'editar' && $id > 0) {
            $contaEdicao = $this->contaModel->getById($id);
        }

        $lista = $this->contaModel->getAll();

        $totais = [
            'total_contas' => count($lista),
            'total_vinculadas' => 0,
            'total_com_meta_id' => 0,
            'total_com_status' => 0,
        ];

        foreach ($lista as $item) {
            if (!empty(trim((string) ($item['cliente_nome'] ?? '')))) {
                $totais['total_vinculadas']++;
            }

            if (!empty(trim((string) ($item['meta_account_id'] ?? '')))) {
                $totais['total_com_meta_id']++;
            }

            if (!empty(trim((string) ($item['status'] ?? '')))) {
                $totais['total_com_status']++;
            }
        }

        $syncStatus = [];

        $stmt = $this->conn->query("
            SELECT
                conta_id,
                tipo,
                status,
                mensagem,
                criado_em
            FROM sync_jobs
            WHERE conta_id IS NOT NULL
              AND empresa_id = " . (int) $this->empresaId . "
            ORDER BY id DESC
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['conta_id'] . '_' . $row['tipo'];

            if (!isset($syncStatus[$key])) {
                $syncStatus[$key] = $row;
            }
        }

        return [
            'clientes' => $clientes,
            'conta_edicao' => $contaEdicao,
            'lista' => $lista,
            'sync_status' => $syncStatus,
            'totais' => $totais,
        ];
    }
}
