<?php

class CampanhaManagementService
{
    private ContaAds $contaModel;
    private Campanha $campanhaModel;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->contaModel = new ContaAds($conn, $empresaId);
        $this->campanhaModel = new Campanha($conn, $empresaId);
    }

    public function getPageData(string $acao = '', int $id = 0): array
    {
        $contas = $this->contaModel->getAll();
        $campanhaEdicao = null;

        if ($acao === 'editar' && $id > 0) {
            $campanhaEdicao = $this->campanhaModel->getById($id);
        }

        $lista = $this->campanhaModel->getAll();

        $totais = [
            'total_campanhas' => count($lista),
            'total_com_objetivo' => 0,
            'total_com_meta_id' => 0,
            'total_com_status' => 0,
        ];

        foreach ($lista as $item) {
            if (!empty(trim((string) ($item['objetivo'] ?? '')))) {
                $totais['total_com_objetivo']++;
            }

            if (!empty(trim((string) ($item['meta_campaign_id'] ?? '')))) {
                $totais['total_com_meta_id']++;
            }

            if (!empty(trim((string) ($item['status'] ?? '')))) {
                $totais['total_com_status']++;
            }
        }

        return [
            'contas' => $contas,
            'campanha_edicao' => $campanhaEdicao,
            'lista' => $lista,
            'totais' => $totais,
        ];
    }
}
