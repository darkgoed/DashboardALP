<?php

class ClienteManagementService
{
    private Cliente $clienteModel;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->clienteModel = new Cliente($conn, $empresaId);
    }

    public function getPageData(string $acao = '', int $id = 0): array
    {
        $clienteEdicao = null;

        if ($acao === 'editar' && $id > 0) {
            $clienteEdicao = $this->clienteModel->getById($id);
        }

        $lista = $this->clienteModel->getAll();

        $totais = [
            'total_clientes' => count($lista),
            'total_com_email' => 0,
            'total_com_whatsapp' => 0,
        ];

        foreach ($lista as $item) {
            if (!empty(trim((string) ($item['email'] ?? '')))) {
                $totais['total_com_email']++;
            }

            if (!empty(trim((string) ($item['whatsapp'] ?? '')))) {
                $totais['total_com_whatsapp']++;
            }
        }

        return [
            'cliente_edicao' => $clienteEdicao,
            'lista' => $lista,
            'totais' => $totais,
        ];
    }
}
