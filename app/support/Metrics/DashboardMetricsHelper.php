<?php

class DashboardMetricsHelper
{
    public static function buildMetricsSummary(array $resumo): array
    {
        return [
            'gasto' => $resumo['gasto_total'] ?? 0,
            'valor_gasto' => $resumo['gasto_total'] ?? 0,
            'impressoes' => $resumo['impressoes'] ?? 0,
            'alcance' => $resumo['alcance'] ?? 0,
            'frequencia' => $resumo['frequencia'] ?? 0,
            'cliques' => (int) ($resumo['cliques_link'] ?? 0),
            'cliques_link' => $resumo['cliques_link'] ?? 0,
            'ctr' => $resumo['ctr'] ?? 0,
            'cpc' => $resumo['cpc'] ?? 0,
            'cpl' => $resumo['cpl'] ?? 0,
            'cpa' => $resumo['cpa'] ?? 0,
            'cpm' => $resumo['cpm'] ?? 0,
            'resultados' => $resumo['resultados'] ?? 0,
            'custo_resultado' => $resumo['custo_resultado'] ?? 0,
            'leads' => $resumo['leads'] ?? 0,
            'conversas_whatsapp' => $resumo['conversas_whatsapp'] ?? 0,
            'custo_por_conversa' => $resumo['custo_por_conversa'] ?? 0,
            'conversoes' => $resumo['conversoes'] ?? 0,
            'compras' => $resumo['compras'] ?? 0,
            'custo_por_compra' => $resumo['custo_por_compra'] ?? 0,
            'taxa_conversao' => $resumo['taxa_conversao'] ?? 0,
            'receita' => $resumo['receita'] ?? 0,
            'roas' => $resumo['roas'] ?? 0,
        ];
    }

    public static function normalizeNumber($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $valor);
    }

    public static function classifyMetricValue(array $config, $valor): string
    {
        $valor = self::normalizeNumber($valor);

        $tipo = trim((string) ($config['tipo_leitura'] ?? ''));

        $criticoMin = self::normalizeNumber($config['critico_min'] ?? null);
        $alertaMin = self::normalizeNumber($config['alerta_min'] ?? null);
        $idealMin = self::normalizeNumber($config['ideal_min'] ?? null);
        $idealMax = self::normalizeNumber($config['ideal_max'] ?? null);
        $alertaMax = self::normalizeNumber($config['alerta_max'] ?? null);
        $criticoMax = self::normalizeNumber($config['critico_max'] ?? null);

        $temFaixaMin = ($criticoMin > 0 || $alertaMin > 0 || $idealMin > 0);
        $temFaixaMax = ($idealMax > 0 || $alertaMax > 0 || $criticoMax > 0);
        $temAlgumaFaixa = $temFaixaMin || $temFaixaMax;

        if ($tipo === '' || !$temAlgumaFaixa) {
            return 'metric-value-neutral';
        }

        if ($tipo === 'menor_melhor') {
            if ($idealMax > 0 && $valor <= $idealMax) {
                return 'metric-value-good';
            }

            if ($alertaMax > 0 && $valor <= $alertaMax) {
                return 'metric-value-warning';
            }

            if ($criticoMax > 0 && $valor >= $criticoMax) {
                return 'metric-value-bad';
            }

            return 'metric-value-neutral';
        }

        if ($tipo === 'maior_melhor') {
            if ($idealMin > 0 && $valor >= $idealMin) {
                return 'metric-value-good';
            }

            if ($alertaMin > 0 && $valor >= $alertaMin) {
                return 'metric-value-warning';
            }

            if ($criticoMin > 0 && $valor <= $criticoMin) {
                return 'metric-value-bad';
            }

            return 'metric-value-neutral';
        }

        if ($tipo === 'faixa_ideal') {
            if ($idealMin > 0 && $idealMax > 0 && $valor >= $idealMin && $valor <= $idealMax) {
                return 'metric-value-good';
            }

            $warningInferior = ($alertaMin > 0 && $idealMin > 0 && $valor >= $alertaMin && $valor < $idealMin);
            $warningSuperior = ($idealMax > 0 && $alertaMax > 0 && $valor > $idealMax && $valor <= $alertaMax);

            if ($warningInferior || $warningSuperior) {
                return 'metric-value-warning';
            }

            $badInferior = ($criticoMin > 0 && $valor <= $criticoMin);
            $badSuperior = ($criticoMax > 0 && $valor >= $criticoMax);

            if ($badInferior || $badSuperior) {
                return 'metric-value-bad';
            }

            return 'metric-value-neutral';
        }

        return 'metric-value-neutral';
    }

    public static function getMetricState(array $config, $valor): string
    {
        $classe = self::classifyMetricValue($config, $valor);

        if ($classe === 'metric-value-good') {
            return 'good';
        }
        if ($classe === 'metric-value-warning') {
            return 'warning';
        }
        if ($classe === 'metric-value-bad') {
            return 'bad';
        }

        return 'neutral';
    }

    public static function getColorsByState(string $estado): array
    {
        switch ($estado) {
            case 'good':
                return ['solid' => '#22c55e', 'soft' => 'rgba(34, 197, 94, 0.18)'];
            case 'warning':
                return ['solid' => '#f59e0b', 'soft' => 'rgba(245, 158, 11, 0.18)'];
            case 'bad':
                return ['solid' => '#ef4444', 'soft' => 'rgba(239, 68, 68, 0.18)'];
            default:
                return ['solid' => '#60a5fa', 'soft' => 'rgba(96, 165, 250, 0.18)'];
        }
    }

    public static function calculateSafePercent($atual, $base): float
    {
        $atual = self::normalizeNumber($atual);
        $base = self::normalizeNumber($base);

        if ($base <= 0) {
            return 0;
        }

        return ($atual / $base) * 100;
    }

    public static function resolveFunnelKey(array $configMetricas, array $candidatas, string $fallback): string
    {
        foreach ($candidatas as $chave) {
            if (isset($configMetricas[$chave])) {
                return $chave;
            }
        }

        return $fallback;
    }

    public static function buildFunnelMetrics(
        array $configMetricas,
        array $resumoMetricas,
        array $resumoMetricasAnterior,
        callable $labelResolver,
        callable $valueFormatter
    ): array {
        $chaveCliques = self::resolveFunnelKey(
            $configMetricas,
            ['cliques_link', 'cliques'],
            'cliques'
        );

        $chaveResultados = self::resolveFunnelKey(
            $configMetricas,
            ['resultados', 'leads', 'conversoes', 'compras', 'conversas_whatsapp'],
            'resultados'
        );

        $estrutura = [
            ['chave' => 'impressoes', 'label' => $labelResolver('impressoes', $configMetricas['impressoes'] ?? [])],
            ['chave' => 'alcance', 'label' => $labelResolver('alcance', $configMetricas['alcance'] ?? [])],
            ['chave' => $chaveCliques, 'label' => $labelResolver($chaveCliques, $configMetricas[$chaveCliques] ?? [])],
            ['chave' => $chaveResultados, 'label' => $labelResolver($chaveResultados, $configMetricas[$chaveResultados] ?? [])],
        ];

        $itens = [];
        $valorTopo = 0.0;

        foreach ($estrutura as $index => $item) {
            $chave = $item['chave'];
            $config = $configMetricas[$chave] ?? [];
            $valorAtual = $resumoMetricas[$chave] ?? 0;
            $valorAnterior = $resumoMetricasAnterior[$chave] ?? 0;

            if ($index === 0) {
                $valorTopo = self::normalizeNumber($valorAtual);
            }

            $estado = self::getMetricState($config, $valorAtual);
            $cores = self::getColorsByState($estado);

            $valorEtapaAnterior = $index > 0
                ? self::normalizeNumber($itens[$index - 1]['valor'])
                : self::normalizeNumber($valorAtual);

            $itens[] = [
                'chave' => $chave,
                'label' => $item['label'],
                'valor' => $valorAtual,
                'valor_formatado' => $valueFormatter($chave, $valorAtual, $config),
                'estado' => $estado,
                'cor' => $cores['solid'],
                'cor_fundo' => $cores['soft'],
                'percentual_etapa' => $index === 0 ? 100 : self::calculateSafePercent($valorAtual, $valorEtapaAnterior),
                'percentual_topo' => $index === 0 ? 100 : self::calculateSafePercent($valorAtual, $valorTopo),
                'variacao_percentual' => self::calculateVariationPercent($valorAtual, $valorAnterior),
            ];
        }

        return $itens;
    }

    public static function classifyTrend(array $config, $valorAtual, $valorAnterior): string
    {
        $valorAtual = self::normalizeNumber($valorAtual);
        $valorAnterior = self::normalizeNumber($valorAnterior);

        if ($valorAnterior == 0.0 || $valorAtual == $valorAnterior) {
            return 'metric-trend-neutral';
        }

        $tipo = $config['tipo_leitura'] ?? 'faixa_ideal';

        if ($tipo === 'menor_melhor') {
            return $valorAtual < $valorAnterior
                ? 'metric-trend-down-good'
                : 'metric-trend-up-bad';
        }

        if ($tipo === 'maior_melhor') {
            return $valorAtual > $valorAnterior
                ? 'metric-trend-up-good'
                : 'metric-trend-down-bad';
        }

        $classeAtual = self::classifyMetricValue($config, $valorAtual);
        $classeAnterior = self::classifyMetricValue($config, $valorAnterior);

        if ($classeAtual === $classeAnterior) {
            return 'metric-trend-neutral';
        }

        if (
            $classeAtual === 'metric-value-good' ||
            ($classeAtual === 'metric-value-warning' && $classeAnterior === 'metric-value-bad')
        ) {
            return $valorAtual >= $valorAnterior
                ? 'metric-trend-up-good'
                : 'metric-trend-down-good';
        }

        return $valorAtual >= $valorAnterior
            ? 'metric-trend-up-bad'
            : 'metric-trend-down-bad';
    }

    public static function calculateVariationPercent($valorAtual, $valorAnterior): ?float
    {
        $valorAtual = self::normalizeNumber($valorAtual);
        $valorAnterior = self::normalizeNumber($valorAnterior);

        if ($valorAnterior == 0.0) {
            return null;
        }

        return (($valorAtual - $valorAnterior) / $valorAnterior) * 100;
    }
}
