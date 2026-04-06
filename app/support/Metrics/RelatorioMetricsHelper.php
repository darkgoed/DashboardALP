<?php

class RelatorioMetricsHelper
{
    public static function isMetricActive(array $configMetricas, string $chave): bool
    {
        if (empty($configMetricas)) {
            return true;
        }

        return !empty($configMetricas[$chave]['ativo']);
    }

    public static function normalizeNumber($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $valor);
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

    public static function resolveLabel(string $chave, array $config = []): string
    {
        if (!empty($config['label'])) {
            return $config['label'];
        }

        $labels = [
            'alcance' => 'Alcance',
            'impressoes' => 'Impressões',
            'cliques' => 'Cliques',
            'cliques_link' => 'Cliques no Link',
            'resultados' => 'Resultados',
            'leads' => 'Leads',
            'conversas_whatsapp' => 'Conversas WhatsApp',
            'conversoes' => 'Conversões',
            'compras' => 'Compras',
        ];

        return $labels[$chave] ?? ucfirst(str_replace('_', ' ', $chave));
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

    public static function calculateVariationPercent($valorAtual, $valorAnterior): ?float
    {
        $valorAtual = self::normalizeNumber($valorAtual);
        $valorAnterior = self::normalizeNumber($valorAnterior);

        if ($valorAnterior == 0.0) {
            return null;
        }

        return (($valorAtual - $valorAnterior) / $valorAnterior) * 100;
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

    public static function formatMetricValue(string $chave, $valor): string
    {
        $moneyKeys = ['valor_gasto', 'cpc', 'cpl', 'cpm', 'custo_por_compra', 'custo_por_conversa', 'custo_resultado'];
        $percentKeys = ['ctr', 'taxa_conversao'];
        $integerKeys = ['impressoes', 'cliques_link', 'leads', 'resultados', 'compras', 'conversas_whatsapp'];

        if (in_array($chave, $moneyKeys, true)) {
            return 'R$ ' . number_format((float) $valor, 2, ',', '.');
        }

        if (in_array($chave, $percentKeys, true)) {
            return number_format((float) $valor, 2, ',', '.') . '%';
        }

        if (in_array($chave, $integerKeys, true)) {
            return number_format((float) $valor, 0, ',', '.');
        }

        if ($chave === 'roas') {
            return number_format((float) $valor, 2, ',', '.') . 'x';
        }

        return number_format((float) $valor, 2, ',', '.');
    }

    public static function buildFunnelMetrics(array $configMetricas, array $resumo, array $resumoAnterior = []): array
    {
        $candidatos = [];

        if (($resumo['alcance'] ?? 0) > 0 && self::isMetricActive($configMetricas, 'alcance')) {
            $candidatos[] = 'alcance';
        }

        if (($resumo['impressoes'] ?? 0) > 0 && self::isMetricActive($configMetricas, 'impressoes')) {
            $candidatos[] = 'impressoes';
        }

        if (($resumo['cliques_link'] ?? 0) > 0 && self::isMetricActive($configMetricas, 'cliques_link')) {
            $candidatos[] = 'cliques_link';
        } elseif (($resumo['cliques'] ?? 0) > 0 && self::isMetricActive($configMetricas, 'cliques')) {
            $candidatos[] = 'cliques';
        }

        if (($resumo['leads'] ?? 0) > 0 && self::isMetricActive($configMetricas, 'leads')) {
            $candidatos[] = 'leads';
        }

        if (($resumo['conversas_whatsapp'] ?? 0) > 0 && self::isMetricActive($configMetricas, 'conversas_whatsapp')) {
            $candidatos[] = 'conversas_whatsapp';
        }

        if (($resumo['conversoes'] ?? 0) > 0 && self::isMetricActive($configMetricas, 'conversoes')) {
            $candidatos[] = 'conversoes';
        }

        if (($resumo['compras'] ?? 0) > 0 && self::isMetricActive($configMetricas, 'compras')) {
            $candidatos[] = 'compras';
        }

        $temFinalEspecifico =
            !empty($resumo['leads']) ||
            !empty($resumo['conversas_whatsapp']) ||
            !empty($resumo['conversoes']) ||
            !empty($resumo['compras']);

        if (
            !$temFinalEspecifico &&
            ($resumo['resultados'] ?? 0) > 0 &&
            self::isMetricActive($configMetricas, 'resultados')
        ) {
            $candidatos[] = 'resultados';
        }

        $candidatos = array_values(array_unique($candidatos));
        $itens = [];

        foreach ($candidatos as $chave) {
            $valorAtual = self::normalizeNumber($resumo[$chave] ?? 0);
            $valorAnterior = self::normalizeNumber($resumoAnterior[$chave] ?? 0);

            if ($valorAtual <= 0) {
                continue;
            }

            $config = $configMetricas[$chave] ?? [];
            $estado = self::getMetricState($config, $valorAtual);

            $itens[] = [
                'chave' => $chave,
                'label' => self::resolveLabel($chave, $config),
                'valor' => $valorAtual,
                'valor_formatado' => self::formatMetricValue($chave, $valorAtual),
                'estado' => $estado,
                'variacao_percentual' => self::calculateVariationPercent($valorAtual, $valorAnterior),
            ];
        }

        if (empty($itens)) {
            return [];
        }

        $valorTopo = self::normalizeNumber($itens[0]['valor']);

        foreach ($itens as $index => &$item) {
            $valorAnteriorEtapa = $index === 0
                ? $item['valor']
                : self::normalizeNumber($itens[$index - 1]['valor']);

            $item['percentual_etapa'] = $index === 0
                ? 100
                : self::calculateSafePercent($item['valor'], $valorAnteriorEtapa);

            $item['percentual_topo'] = $index === 0
                ? 100
                : self::calculateSafePercent($item['valor'], $valorTopo);
        }

        unset($item);

        return $itens;
    }

    public static function formatMercadoPhoneValue(string $chave, $valor): string
    {
        if ($chave === 'faturamento' || $chave === 'ticket_medio') {
            return 'R$ ' . number_format((float) $valor, 2, ',', '.');
        }

        return number_format((float) $valor, 0, ',', '.');
    }

    public static function getMercadoPhoneState($valorAtual, $valorAnterior): string
    {
        $valorAtual = self::normalizeNumber($valorAtual);
        $valorAnterior = self::normalizeNumber($valorAnterior);

        if ($valorAnterior == 0.0) {
            return 'neutral';
        }

        if ($valorAtual > $valorAnterior) {
            return 'good';
        }

        if ($valorAtual < $valorAnterior) {
            return 'bad';
        }

        return 'neutral';
    }

    public static function getMercadoPhoneTrendClass($valorAtual, $valorAnterior): string
    {
        $valorAtual = self::normalizeNumber($valorAtual);
        $valorAnterior = self::normalizeNumber($valorAnterior);

        if ($valorAnterior == 0.0 || $valorAtual == $valorAnterior) {
            return 'metric-trend-neutral';
        }

        return $valorAtual > $valorAnterior
            ? 'metric-trend-up-good'
            : 'metric-trend-down-bad';
    }
}
