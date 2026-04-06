<?php

class InsightsPageService
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

    public function getPageData(array $query): array
    {
        $clientes = $this->clienteModel->getAll();

        $clienteId = isset($query['cliente_id']) && $query['cliente_id'] !== ''
            ? (int) $query['cliente_id']
            : 0;

        $contaId = isset($query['conta_id']) && $query['conta_id'] !== ''
            ? (int) $query['conta_id']
            : 0;

        $filters = [
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'nivel' => trim((string) ($query['nivel'] ?? '')),
            'inicio' => (string) ($query['inicio'] ?? date('Y-m-d', strtotime('-29 days'))),
            'fim' => (string) ($query['fim'] ?? date('Y-m-d')),
        ];

        $contas = $clienteId > 0 ? $this->contaModel->getByCliente($clienteId) : [];
        $lista = $this->fetchInsights($filters);

        $totais = [
            'total_insights' => count($lista),
            'total_gasto' => 0.0,
            'total_leads' => 0.0,
            'total_receita' => 0.0,
        ];

        foreach ($lista as $item) {
            $totais['total_gasto'] += (float) ($item['gasto'] ?? 0);
            $totais['total_leads'] += (float) ($item['leads'] ?? 0);
            $totais['total_receita'] += (float) ($item['receita'] ?? 0);
        }

        return [
            'clientes' => $clientes,
            'contas' => $contas,
            'filters' => $filters,
            'lista' => $lista,
            'totais' => $totais,
        ];
    }

    private function fetchInsights(array $filters): array
    {
        $sql = "
            SELECT
                i.*,
                c.nome AS cliente_nome,
                ca.nome AS conta_nome,
                cp.nome AS campanha_nome,
                cj.nome AS conjunto_nome,
                a.nome AS anuncio_nome
            FROM insights_diarios i
            LEFT JOIN clientes c
                ON c.id = i.cliente_id
               AND c.empresa_id = i.empresa_id
            LEFT JOIN contas_ads ca
                ON ca.id = i.conta_id
               AND ca.empresa_id = i.empresa_id
            LEFT JOIN campanhas cp
                ON cp.id = i.campanha_id
               AND cp.empresa_id = i.empresa_id
            LEFT JOIN conjuntos cj
                ON cj.id = i.conjunto_id
               AND cj.empresa_id = i.empresa_id
            LEFT JOIN anuncios a
                ON a.id = i.anuncio_id
               AND a.empresa_id = i.empresa_id
            WHERE i.empresa_id = :empresa_id
        ";

        $params = [':empresa_id' => $this->empresaId];

        if ((int) ($filters['cliente_id'] ?? 0) > 0) {
            $sql .= " AND i.cliente_id = :cliente_id";
            $params[':cliente_id'] = (int) $filters['cliente_id'];
        }

        if ((int) ($filters['conta_id'] ?? 0) > 0) {
            $sql .= " AND i.conta_id = :conta_id";
            $params[':conta_id'] = (int) $filters['conta_id'];
        }

        if (($filters['nivel'] ?? '') !== '') {
            $sql .= " AND i.nivel = :nivel";
            $params[':nivel'] = (string) $filters['nivel'];
        }

        if (($filters['inicio'] ?? '') !== '' && ($filters['fim'] ?? '') !== '') {
            $sql .= " AND i.data BETWEEN :inicio AND :fim";
            $params[':inicio'] = (string) $filters['inicio'];
            $params[':fim'] = (string) $filters['fim'];
        }

        $sql .= " ORDER BY i.data DESC, i.id DESC LIMIT 300";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
