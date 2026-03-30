<?php

class RelatorioService
{
    private PDO $conn;
    private int $empresaId;
    private array $insightsColumns = [];

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->insightsColumns = $this->getTableColumns('insights_diarios');
    }

    private function calcularMetricas(array $r): array
    {
        $gasto = (float) ($r['valor_gasto'] ?? $r['gasto_total'] ?? $r['gasto'] ?? 0);
        $impressoes = (int) ($r['impressoes'] ?? 0);
        $alcance = (int) ($r['alcance'] ?? 0);

        $cliques = (int) ($r['cliques'] ?? 0);
        $cliquesLink = (int) ($r['cliques_link'] ?? $r['cliques'] ?? 0);

        $leads = (int) ($r['leads'] ?? 0);
        $conversoes = (int) ($r['conversoes'] ?? 0);
        $compras = (int) ($r['compras'] ?? 0);
        $conversasWhatsapp = (int) ($r['conversas_whatsapp'] ?? 0);

        $faturamento = (float) ($r['faturamento'] ?? $r['receita'] ?? 0);

        /*
     * REGRA PADRONIZADA:
     * para tráfego/lead, resultados = leads
     * se depois quiser algo dinâmico por objetivo, dá para evoluir
     */
        $resultados = $leads;

        $ctr = $impressoes > 0 ? ($cliquesLink / $impressoes) * 100 : 0;
        $cpc = $cliquesLink > 0 ? $gasto / $cliquesLink : 0;
        $cpm = $impressoes > 0 ? ($gasto / $impressoes) * 1000 : 0;
        $cpl = $resultados > 0 ? $gasto / $resultados : 0;
        $cpa = $compras > 0 ? $gasto / $compras : 0;
        $frequencia = $alcance > 0 ? $impressoes / $alcance : 0;
        $roas = $gasto > 0 ? $faturamento / $gasto : 0;
        $custoPorConversa = $conversasWhatsapp > 0 ? $gasto / $conversasWhatsapp : 0;

        return [
            // gastos
            'valor_gasto' => $gasto,
            'gasto_total' => $gasto,
            'gasto' => $gasto,

            // volume
            'impressoes' => $impressoes,
            'alcance' => $alcance,
            'cliques' => $cliques,
            'cliques_link' => $cliquesLink,

            // resultados
            'leads' => $leads,
            'conversoes' => $conversoes,
            'resultados' => $resultados,
            'compras' => $compras,
            'conversas_whatsapp' => $conversasWhatsapp,

            // receita
            'faturamento' => $faturamento,
            'receita' => $faturamento,

            // métricas calculadas
            'ctr' => $ctr,
            'cpc' => $cpc,
            'cpm' => $cpm,
            'cpl' => $cpl,
            'cpa' => $cpa,
            'roas' => $roas,
            'frequencia' => $frequencia,

            // aliases que seu front já usa
            'custo_resultado' => $cpl,
            'custo_por_resultado' => $cpl,
            'custo_por_compra' => $cpa,
            'custo_por_conversa' => $custoPorConversa,
        ];
    }

    public function getResumoGeral(?int $contaId, ?int $campanhaId, string $inicio, string $fim): array
    {
        $where = $this->buildWhere($contaId, $campanhaId, $inicio, $fim);

        $sql = "
            SELECT
                {$this->sumExpr(['valor_gasto', 'gasto', 'spend'], 'valor_gasto')},
                {$this->sumExpr(['impressoes', 'impressions'], 'impressoes')},
                {$this->sumExpr(['alcance', 'reach'], 'alcance')},
                {$this->sumExpr(['cliques_link', 'cliques', 'clicks', 'link_clicks'], 'cliques_link')},
                {$this->sumExpr(['leads', 'resultados', 'results'], 'leads')},
                {$this->sumExpr(['conversas_whatsapp', 'whatsapp', 'mensagens_whatsapp'], 'conversas_whatsapp')},
                {$this->sumExpr(['compras', 'purchases'], 'compras')},
                {$this->sumExpr(['faturamento', 'valor_conversao', 'purchase_value', 'conversion_value'], 'faturamento')}
            FROM insights_diarios i
            INNER JOIN campanhas c ON c.id = i.campanha_id
            INNER JOIN contas_ads ca ON ca.id = c.conta_id
            WHERE {$where['sql']}
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($where['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $gasto       = (float)($row['valor_gasto'] ?? 0);
        $impressoes  = (int)($row['impressoes'] ?? 0);
        $alcance     = (int)($row['alcance'] ?? 0);
        $cliques     = (int)($row['cliques_link'] ?? 0);
        $leads       = (int)($row['leads'] ?? 0);
        $whatsapp    = (int)($row['conversas_whatsapp'] ?? 0);
        $compras     = (int)($row['compras'] ?? 0);
        $faturamento = (float)($row['faturamento'] ?? 0);

        $ctr  = $impressoes > 0 ? ($cliques / $impressoes) * 100 : 0;
        $cpc  = $cliques > 0 ? $gasto / $cliques : 0;
        $cpm  = $impressoes > 0 ? ($gasto / $impressoes) * 1000 : 0;
        $cpl  = $leads > 0 ? $gasto / $leads : 0;
        $cpa  = $compras > 0 ? $gasto / $compras : 0;
        $roas = $gasto > 0 ? $faturamento / $gasto : 0;

        return $this->calcularMetricas($row);
    }


    public function getSerieTemporal(?int $contaId, ?int $campanhaId, string $inicio, string $fim): array
    {
        $where = $this->buildWhere($contaId, $campanhaId, $inicio, $fim);

        $sql = "
            SELECT
                i.data,
                {$this->sumExpr(['valor_gasto', 'gasto', 'spend'], 'valor_gasto')},
                {$this->sumExpr(['cliques_link', 'cliques', 'clicks', 'link_clicks'], 'cliques_link')},
                {$this->sumExpr(['leads', 'resultados', 'results'], 'leads')},
                {$this->sumExpr(['impressoes', 'impressions'], 'impressoes')}
            FROM insights_diarios i
            INNER JOIN campanhas c ON c.id = i.campanha_id
            INNER JOIN contas_ads ca ON ca.id = c.conta_id
            WHERE {$where['sql']}
            GROUP BY i.data
            ORDER BY i.data ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($where['params']);

        $labels = [];
        $gasto = [];
        $cliques = [];
        $leads = [];
        $ctr = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $imp = (int)($row['impressoes'] ?? 0);
            $clk = (int)($row['cliques_link'] ?? 0);

            $labels[]  = date('d/m', strtotime($row['data']));
            $gasto[]   = round((float)($row['valor_gasto'] ?? 0), 2);
            $cliques[] = $clk;
            $leads[]   = (int)($row['leads'] ?? 0);
            $ctr[]     = round($imp > 0 ? ($clk / $imp) * 100 : 0, 2);
        }

        return [
            'labels' => $labels,
            'gasto' => $gasto,
            'cliques' => $cliques,
            'leads' => $leads,
            'ctr' => $ctr,
        ];
    }

    public function getCampanhasRelatorio(?int $contaId, ?int $campanhaId, string $inicio, string $fim): array
    {
        $sql = "
            SELECT
                c.id,
                c.nome,
                c.status,
                {$this->sumExpr(['valor_gasto', 'gasto', 'spend'], 'valor_gasto', 'i')},
                {$this->sumExpr(['impressoes', 'impressions'], 'impressoes', 'i')},
                {$this->sumExpr(['cliques_link', 'cliques', 'clicks', 'link_clicks'], 'cliques_link', 'i')},
                {$this->sumExpr(['leads', 'resultados', 'results'], 'leads', 'i')},
                {$this->sumExpr(['compras', 'purchases'], 'compras', 'i')},
                {$this->sumExpr(['faturamento', 'valor_conversao', 'purchase_value', 'conversion_value'], 'faturamento', 'i')}
            FROM campanhas c
            INNER JOIN contas_ads ca ON ca.id = c.conta_id
            LEFT JOIN insights_diarios i
                ON i.campanha_id = c.id
                AND i.data BETWEEN :inicio AND :fim
            WHERE ca.empresa_id = :empresa_id
        ";

        $params = [
            ':empresa_id' => $this->empresaId,
            ':inicio' => $inicio,
            ':fim' => $fim,
        ];

        if (!empty($contaId)) {
            $sql .= " AND c.conta_id = :conta_id";
            $params[':conta_id'] = $contaId;
        }

        if (!empty($campanhaId)) {
            $sql .= " AND c.id = :campanha_id";
            $params[':campanha_id'] = $campanhaId;
        }

        $sql .= "
            GROUP BY c.id, c.nome, c.status
            ORDER BY valor_gasto DESC, c.nome ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $items = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $gasto = (float)($row['valor_gasto'] ?? 0);
            $imp = (int)($row['impressoes'] ?? 0);
            $clk = (int)($row['cliques_link'] ?? 0);
            $leads = (int)($row['leads'] ?? 0);
            $compras = (int)($row['compras'] ?? 0);
            $fat = (float)($row['faturamento'] ?? 0);

            $ctr = $imp > 0 ? ($clk / $imp) * 100 : 0;
            $cpc = $clk > 0 ? $gasto / $clk : 0;
            $cpl = $leads > 0 ? $gasto / $leads : 0;
            $cpa = $compras > 0 ? $gasto / $compras : 0;
            $roas = $gasto > 0 ? $fat / $gasto : 0;

            $items[] = [
                'id' => (int)$row['id'],
                'nome' => $row['nome'],
                'status' => $row['status'],
                'valor_gasto' => $gasto,
                'impressoes' => $imp,
                'cliques_link' => $clk,
                'leads' => $leads,
                'compras' => $compras,
                'faturamento' => $fat,
                'ctr' => $ctr,
                'cpc' => $cpc,
                'cpl' => $cpl,
                'cpa' => $cpa,
                'roas' => $roas,
            ];
        }

        return $items;
    }

    public function getInsightsAutomaticos(array $resumo, array $campanhas): array
    {
        $insights = [];

        if ($resumo['ctr'] < 1.0) {
            $insights[] = 'CTR geral abaixo de 1%, indicando baixa atratividade dos anúncios.';
        } elseif ($resumo['ctr'] >= 2.0) {
            $insights[] = 'CTR geral saudável, indicando boa taxa de interesse nos anúncios.';
        }

        if ($resumo['leads'] > 0 && $resumo['cpl'] > 0) {
            if ($resumo['cpl'] <= 15) {
                $insights[] = 'CPL competitivo no período, com boa eficiência na geração de leads.';
            } elseif ($resumo['cpl'] >= 40) {
                $insights[] = 'CPL elevado no período, sugerindo necessidade de revisar criativos, oferta ou segmentação.';
            }
        }

        if ($resumo['roas'] > 0) {
            if ($resumo['roas'] >= 3) {
                $insights[] = 'ROAS positivo, indicando retorno interessante sobre o investimento.';
            } elseif ($resumo['roas'] < 1.5) {
                $insights[] = 'ROAS baixo, exigindo revisão da estratégia para melhorar retorno.';
            }
        }

        if (!empty($campanhas)) {
            $top = $campanhas[0];
            $insights[] = 'Campanha com maior investimento no período: "' . $top['nome'] . '".';
        }

        if (count($insights) === 0) {
            $insights[] = 'Sem sinais críticos automáticos no período analisado.';
        }

        return $insights;
    }

    private function buildWhere(?int $contaId, ?int $campanhaId, string $inicio, string $fim): array
    {
        $sql = "
            ca.empresa_id = :empresa_id
            AND i.data BETWEEN :inicio AND :fim AND i.nivel = 'campaign'
        ";

        $params = [
            ':empresa_id' => $this->empresaId,
            ':inicio' => $inicio,
            ':fim' => $fim,
        ];

        if (!empty($contaId)) {
            $sql .= " AND c.conta_id = :conta_id";
            $params[':conta_id'] = $contaId;
        }

        if (!empty($campanhaId)) {
            $sql .= " AND c.id = :campanha_id";
            $params[':campanha_id'] = $campanhaId;
        }

        
        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    private function getTableColumns(string $table): array
    {
        $stmt = $this->conn->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
        ");
        $stmt->execute([':table' => $table]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function hasColumn(string $column): bool
    {
        return in_array($column, $this->insightsColumns, true);
    }

    private function sumExpr(array $candidates, string $alias, string $tableAlias = 'i'): string
    {
        foreach ($candidates as $column) {
            if ($this->hasColumn($column)) {
                return "COALESCE(SUM({$tableAlias}.{$column}), 0) AS {$alias}";
            }
        }

        return "0 AS {$alias}";
    }
}
