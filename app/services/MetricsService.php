<?php

class MetricsService
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getDashboardData(array $filters = [])
    {
        $periodoResolvido = $this->resolvePeriod($filters);

        $filters['data_inicio'] = $periodoResolvido['data_inicio'];
        $filters['data_fim']    = $periodoResolvido['data_fim'];

        return [
            'periodo'                => $periodoResolvido,
            'resumo'                 => $this->getResumo($filters),
            'serie_gasto_resultado'  => $this->getSerieGastoResultado($filters),
            'serie_custo_resultado'  => $this->getSerieCustoResultado($filters),
            'funil'                  => $this->getFunil($filters),
            'serie_freq_ctr'         => $this->getSerieFrequenciaCtr($filters),
            'contexto'               => $this->getContextoDashboard($filters),
        ];
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

    public function resolvePeriod(array $filters = [])
    {
        $periodo = isset($filters['periodo']) ? $filters['periodo'] : '90';
        $hoje = new DateTime('today');

        if ($periodo === 'custom') {
            $dataInicio = !empty($filters['data_inicio']) ? $filters['data_inicio'] : $hoje->format('Y-m-d');
            $dataFim    = !empty($filters['data_fim']) ? $filters['data_fim'] : $hoje->format('Y-m-d');

            if ($dataInicio > $dataFim) {
                $tmp = $dataInicio;
                $dataInicio = $dataFim;
                $dataFim = $tmp;
            }

            return [
                'periodo'     => 'custom',
                'data_inicio' => $dataInicio,
                'data_fim'    => $dataFim,
                'label'       => 'Período personalizado'
            ];
        }

        $dias = (int) $periodo;
        if ($dias <= 0) {
            $dias = 90;
        }

        $inicio = clone $hoje;
        $inicio->modify('-' . ($dias - 1) . ' days');

        return [
            'periodo'     => (string) $dias,
            'data_inicio' => $inicio->format('Y-m-d'),
            'data_fim'    => $hoje->format('Y-m-d'),
            'label'       => 'Últimos ' . $dias . ' dias'
        ];
    }

    private function buildWhere(array $filters, array &$params)
    {
        $where = [];
        $where[] = "i.nivel = 'campaign'";
        $where[] = "i.data BETWEEN :data_inicio AND :data_fim";
        $params[':data_inicio'] = $filters['data_inicio'];
        $params[':data_fim'] = $filters['data_fim'];

        if (!empty($filters['conta_id'])) {
            $where[] = "i.conta_id = :conta_id";
            $params[':conta_id'] = (int) $filters['conta_id'];
        }

        if (!empty($filters['campanha_id'])) {
            $where[] = "i.campanha_id = :campanha_id";
            $params[':campanha_id'] = (int) $filters['campanha_id'];
        }

        if (!empty($filters['cliente_id'])) {
            $where[] = "i.cliente_id = :cliente_id";
            $params[':cliente_id'] = (int) $filters['cliente_id'];
        }

        if (!empty($filters['empresa_id'])) {
            $where[] = "i.empresa_id = :empresa_id";
            $params[':empresa_id'] = (int) $filters['empresa_id'];
        }

        return ' WHERE ' . implode(' AND ', $where);
    }

    public function getResumo(array $filters = [])
    {
        $params = [];
        $where = $this->buildWhere($filters, $params);

        $sql = "
            SELECT
                COALESCE(SUM(i.gasto), 0) AS gasto_total,
                COALESCE(SUM(i.impressoes), 0) AS impressoes,
                COALESCE(SUM(i.alcance), 0) AS alcance,
                COALESCE(SUM(i.cliques), 0) AS cliques,
                COALESCE(SUM(i.cliques_link), 0) AS cliques_link,
                COALESCE(SUM(i.leads), 0) AS leads,
                COALESCE(SUM(i.conversoes), 0) AS conversoes,
                COALESCE(SUM(i.compras), 0) AS compras,
                COALESCE(SUM(i.conversas_whatsapp), 0) AS conversas_whatsapp,
                COALESCE(SUM(i.receita), 0) AS faturamento
            FROM insights_diarios i
            $where
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $row = [];
        }

        $gasto = (float) (isset($row['gasto_total']) ? $row['gasto_total'] : 0);
        $impressoes = (int) (isset($row['impressoes']) ? $row['impressoes'] : 0);
        $alcance = (int) (isset($row['alcance']) ? $row['alcance'] : 0);
        $cliques = (int) (isset($row['cliques']) ? $row['cliques'] : 0);
        $cliquesLink = (int) (isset($row['cliques_link']) ? $row['cliques_link'] : 0);
        $leads = (int) (isset($row['leads']) ? $row['leads'] : 0);
        $conversoes = (int) (isset($row['conversoes']) ? $row['conversoes'] : 0);

        $resultados = $leads;

        $ctr = $impressoes > 0 ? ($cliquesLink / $impressoes) * 100 : 0;
        $cpc = $cliques > 0 ? $gasto / $cliques : 0;
        $cpm = $impressoes > 0 ? ($gasto / $impressoes) * 1000 : 0;
        $custoResultado = $resultados > 0 ? $gasto / $resultados : 0;
        $frequencia = $alcance > 0 ? $impressoes / $alcance : 0;

        return $this->calcularMetricas($row);
    }

    public function getSerieGastoResultado(array $filters = [])
    {
        $params = [];
        $where = $this->buildWhere($filters, $params);

        $sql = "
            SELECT
                i.data,
                COALESCE(SUM(i.gasto), 0) AS gasto,
                COALESCE(SUM(i.leads), 0) AS leads,
                COALESCE(SUM(i.conversoes), 0) AS conversoes
            FROM insights_diarios i
            $where
            GROUP BY i.data
            ORDER BY i.data ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $labels = [];
        $gastos = [];
        $resultados = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $conv = (int) $row['conversoes'];
            $lead = (int) $row['leads'];
            $resultadoDia = $lead;

            $labels[] = date('d/m', strtotime($row['data']));
            $gastos[] = (float) $row['gasto'];
            $resultados[] = (int) $resultadoDia;
        }

        return [
            'labels' => $labels,
            'gasto' => $gastos,
            'resultados' => $resultados,
        ];
    }

    public function getSerieCustoResultado(array $filters = [])
    {
        $params = [];
        $where = $this->buildWhere($filters, $params);

        $sql = "
            SELECT
                i.data,
                COALESCE(SUM(i.gasto), 0) AS gasto,
                COALESCE(SUM(i.leads), 0) AS leads,
                COALESCE(SUM(i.conversoes), 0) AS conversoes
            FROM insights_diarios i
            $where
            GROUP BY i.data
            ORDER BY i.data ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $labels = [];
        $custos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $conv = (int) $row['conversoes'];
            $lead = (int) $row['leads'];
            $resultadoDia = $lead;

            $custo = $resultadoDia > 0 ? ((float)$row['gasto'] / $resultadoDia) : 0;

            $labels[] = date('d/m', strtotime($row['data']));
            $custos[] = round($custo, 2);
        }

        return [
            'labels' => $labels,
            'custos' => $custos,
        ];
    }

    public function getFunil(array $filters = [])
    {
        $resumo = $this->getResumo($filters);

        return [
            'impressoes'   => (int) $resumo['impressoes'],
            'alcance'      => (int) $resumo['alcance'],
            'cliques'      => (int) $resumo['cliques_link'],
            'resultados'   => (int) $resumo['resultados'],
        ];
    }

    public function getSerieFrequenciaCtr(array $filters = [])
    {
        $params = [];
        $where = $this->buildWhere($filters, $params);

        $sql = "
            SELECT
                i.data,
                COALESCE(SUM(i.impressoes), 0) AS impressoes,
                COALESCE(SUM(i.alcance), 0) AS alcance,
                COALESCE(SUM(i.cliques_link), 0) AS cliques_link
            FROM insights_diarios i
            $where
            GROUP BY i.data
            ORDER BY i.data ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $labels = [];
        $frequencias = [];
        $ctrs = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $impressoes = (int) $row['impressoes'];
            $alcance = (int) $row['alcance'];
            $cliquesLink = (int) $row['cliques_link'];

            $freq = $alcance > 0 ? $impressoes / $alcance : 0;
            $ctr = $impressoes > 0 ? ($cliquesLink / $impressoes) * 100 : 0;

            $labels[] = date('d/m', strtotime($row['data']));
            $frequencias[] = round($freq, 2);
            $ctrs[] = round($ctr, 2);
        }

        return [
            'labels' => $labels,
            'frequencia' => $frequencias,
            'ctr' => $ctrs,
        ];
    }

    public function getContextoDashboard(array $filters = [])
    {
        $contaNome = 'Todas as contas';
        $campanhaNome = 'Todas as campanhas';

        if (!empty($filters['conta_id'])) {
            $stmt = $this->conn->prepare("SELECT nome FROM contas_ads WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$filters['conta_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nome'])) {
                $contaNome = $row['nome'];
            }
        }

        if (!empty($filters['campanha_id'])) {
            $stmt = $this->conn->prepare("SELECT nome FROM campanhas WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$filters['campanha_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nome'])) {
                $campanhaNome = $row['nome'];
            }
        }

        return [
            'conta_nome' => $contaNome,
            'campanha_nome' => $campanhaNome,
        ];
    }
}
