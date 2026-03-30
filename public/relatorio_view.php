<?php

require_once __DIR__ . '/../app/config/bootstrap.php';
require_once __DIR__ . '/../app/models/Cliente.php';
require_once __DIR__ . '/../app/models/ContaAds.php';
require_once __DIR__ . '/../app/models/Campanha.php';
require_once __DIR__ . '/../app/services/RelatorioService.php';

Auth::requireLogin();

$empresaId = Tenant::getEmpresaId();

$db = new Database();
$conn = $db->connect();

$clienteModel  = new Cliente($conn, $empresaId);
$contaModel    = new ContaAds($conn, $empresaId);
$campanhaModel = new Campanha($conn, $empresaId);
$relatorioService = new RelatorioService($conn, $empresaId);

$clienteId   = isset($_GET['cliente_id']) && $_GET['cliente_id'] !== '' ? (int) $_GET['cliente_id'] : 0;
$contaId     = isset($_GET['conta_id']) && $_GET['conta_id'] !== '' ? (int) $_GET['conta_id'] : 0;
$campanhaId  = isset($_GET['campanha_id']) && $_GET['campanha_id'] !== '' ? (int) $_GET['campanha_id'] : 0;
$periodo     = $_GET['periodo'] ?? '30';
$dataInicio  = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-29 days'));
$dataFim     = $_GET['data_fim'] ?? date('Y-m-d');
$printMode   = isset($_GET['print']) && $_GET['print'] == '1';

$cliente = $clienteId > 0 ? $clienteModel->getById($clienteId) : null;
$conta   = $contaId > 0 ? $contaModel->getById($contaId) : null;
$campanha = $campanhaId > 0 ? $campanhaModel->getById($campanhaId) : null;

/* =========================
   PERÍODO ATUAL E ANTERIOR
========================= */
$inicioTs = strtotime($dataInicio);
$fimTs = strtotime($dataFim);

$diasPeriodo = 1;
if ($inicioTs && $fimTs && $fimTs >= $inicioTs) {
    $diasPeriodo = (int) floor(($fimTs - $inicioTs) / 86400) + 1;
}

$dataFimAnterior = date('Y-m-d', strtotime($dataInicio . ' -1 day'));
$dataInicioAnterior = date('Y-m-d', strtotime($dataFimAnterior . ' -' . ($diasPeriodo - 1) . ' days'));

/* =========================
   DADOS
========================= */

$resumo = $relatorioService->getResumoGeral($contaId ?: null, $campanhaId ?: null, $dataInicio, $dataFim);
$resumoAnterior = $relatorioService->getResumoGeral($contaId ?: null, $campanhaId ?: null, $dataInicioAnterior, $dataFimAnterior);

$serie = $relatorioService->getSerieTemporal($contaId ?: null, $campanhaId ?: null, $dataInicio, $dataFim);
$campanhas = $relatorioService->getCampanhasRelatorio($contaId ?: null, $campanhaId ?: null, $dataInicio, $dataFim);
$insights = $relatorioService->getInsightsAutomaticos($resumo, $campanhas);



/* =========================
   HELPERS
========================= */
function moneyBr(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function numberBr($v, int $dec = 0): string
{
    return number_format((float) $v, $dec, ',', '.');
}

function formatPercentBr($v, int $dec = 1): string
{
    return number_format((float) $v, $dec, ',', '.') . '%';
}

function metricaAtivaRelatorio(array $configMetricas, string $chave): bool
{
    if (empty($configMetricas)) {
        return true;
    }

    return !empty($configMetricas[$chave]['ativo']);
}

function calcularPercentualSeguroRelatorio($atual, $base): float
{
    $atual = normalizarNumeroRelatorio($atual);
    $base = normalizarNumeroRelatorio($base);

    if ($base <= 0) {
        return 0;
    }

    return ($atual / $base) * 100;
}

function obterLabelRelatorio(string $chave, array $config = []): string
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

function montarFunilMetricasRelatorio(array $configMetricas, array $resumo, array $resumoAnterior = []): array
{
    $candidatos = [];

    if (($resumo['alcance'] ?? 0) > 0 && metricaAtivaRelatorio($configMetricas, 'alcance')) {
        $candidatos[] = 'alcance';
    }

    if (($resumo['impressoes'] ?? 0) > 0 && metricaAtivaRelatorio($configMetricas, 'impressoes')) {
        $candidatos[] = 'impressoes';
    }

    if (($resumo['cliques_link'] ?? 0) > 0 && metricaAtivaRelatorio($configMetricas, 'cliques_link')) {
        $candidatos[] = 'cliques_link';
    } elseif (($resumo['cliques'] ?? 0) > 0 && metricaAtivaRelatorio($configMetricas, 'cliques')) {
        $candidatos[] = 'cliques';
    }

    if (($resumo['leads'] ?? 0) > 0 && metricaAtivaRelatorio($configMetricas, 'leads')) {
        $candidatos[] = 'leads';
    }

    if (($resumo['conversas_whatsapp'] ?? 0) > 0 && metricaAtivaRelatorio($configMetricas, 'conversas_whatsapp')) {
        $candidatos[] = 'conversas_whatsapp';
    }

    if (($resumo['conversoes'] ?? 0) > 0 && metricaAtivaRelatorio($configMetricas, 'conversoes')) {
        $candidatos[] = 'conversoes';
    }

    if (($resumo['compras'] ?? 0) > 0 && metricaAtivaRelatorio($configMetricas, 'compras')) {
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
        metricaAtivaRelatorio($configMetricas, 'resultados')
    ) {
        $candidatos[] = 'resultados';
    }

    $candidatos = array_values(array_unique($candidatos));

    $itens = [];

    foreach ($candidatos as $chave) {
        $valorAtual = normalizarNumeroRelatorio($resumo[$chave] ?? 0);
        $valorAnterior = normalizarNumeroRelatorio($resumoAnterior[$chave] ?? 0);

        if ($valorAtual <= 0) {
            continue;
        }

        $config = $configMetricas[$chave] ?? [];
        $estado = getEstadoMetricaRelatorio($config, $valorAtual);

        $itens[] = [
            'chave' => $chave,
            'label' => obterLabelRelatorio($chave, $config),
            'valor' => $valorAtual,
            'valor_formatado' => formatarValorRelatorio($chave, $valorAtual),
            'estado' => $estado,
            'variacao_percentual' => calcularVariacaoPercentualRelatorio($valorAtual, $valorAnterior),
        ];
    }

    if (empty($itens)) {
        return [];
    }

    $valorTopo = normalizarNumeroRelatorio($itens[0]['valor']);

    foreach ($itens as $index => &$item) {
        $valorAnteriorEtapa = $index === 0
            ? $item['valor']
            : normalizarNumeroRelatorio($itens[$index - 1]['valor']);

        $item['percentual_etapa'] = $index === 0
            ? 100
            : calcularPercentualSeguroRelatorio($item['valor'], $valorAnteriorEtapa);

        $item['percentual_topo'] = $index === 0
            ? 100
            : calcularPercentualSeguroRelatorio($item['valor'], $valorTopo);
    }

    unset($item);

    return $itens;
}

function normalizarNumeroRelatorio($valor): float
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }

    return (float) str_replace(',', '.', (string) $valor);
}

function carregarConfigMetricasClienteRelatorio(PDO $conn, $contaId): array
{
    if (empty($contaId)) {
        return [];
    }

    $sql = "
        SELECT mc.config_json
        FROM metricas_config mc
        INNER JOIN contas_ads ca ON ca.cliente_id = mc.cliente_id
        WHERE ca.id = :conta_id
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':conta_id' => $contaId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['config_json'])) {
        return [];
    }

    $json = json_decode($row['config_json'], true);

    return is_array($json) ? $json : [];
}

function classificarValorMetricaRelatorio(array $config, $valor): string
{
    $valor = normalizarNumeroRelatorio($valor);

    $tipo = trim((string) ($config['tipo_leitura'] ?? ''));

    $criticoMin = normalizarNumeroRelatorio($config['critico_min'] ?? null);
    $alertaMin  = normalizarNumeroRelatorio($config['alerta_min'] ?? null);
    $idealMin   = normalizarNumeroRelatorio($config['ideal_min'] ?? null);
    $idealMax   = normalizarNumeroRelatorio($config['ideal_max'] ?? null);
    $alertaMax  = normalizarNumeroRelatorio($config['alerta_max'] ?? null);
    $criticoMax = normalizarNumeroRelatorio($config['critico_max'] ?? null);

    $temFaixaMin = ($criticoMin > 0 || $alertaMin > 0 || $idealMin > 0);
    $temFaixaMax = ($idealMax > 0 || $alertaMax > 0 || $criticoMax > 0);
    $temAlgumaFaixa = $temFaixaMin || $temFaixaMax;

    if ($tipo === '' || !$temAlgumaFaixa) {
        return 'metric-value-neutral';
    }

    if ($tipo === 'menor_melhor') {
        if ($idealMax > 0 && $valor <= $idealMax) return 'metric-value-good';
        if ($alertaMax > 0 && $valor <= $alertaMax) return 'metric-value-warning';
        if ($criticoMax > 0 && $valor >= $criticoMax) return 'metric-value-bad';
        return 'metric-value-neutral';
    }

    if ($tipo === 'maior_melhor') {
        if ($idealMin > 0 && $valor >= $idealMin) return 'metric-value-good';
        if ($alertaMin > 0 && $valor >= $alertaMin) return 'metric-value-warning';
        if ($criticoMin > 0 && $valor <= $criticoMin) return 'metric-value-bad';
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

function getEstadoMetricaRelatorio(array $config, $valor): string
{
    $classe = classificarValorMetricaRelatorio($config, $valor);

    if ($classe === 'metric-value-good') return 'good';
    if ($classe === 'metric-value-warning') return 'warning';
    if ($classe === 'metric-value-bad') return 'bad';

    return 'neutral';
}

function calcularVariacaoPercentualRelatorio($valorAtual, $valorAnterior): ?float
{
    $valorAtual = normalizarNumeroRelatorio($valorAtual);
    $valorAnterior = normalizarNumeroRelatorio($valorAnterior);

    if ($valorAnterior == 0.0) {
        return null;
    }

    return (($valorAtual - $valorAnterior) / $valorAnterior) * 100;
}

function classificarTrendRelatorio(array $config, $valorAtual, $valorAnterior): string
{
    $valorAtual = normalizarNumeroRelatorio($valorAtual);
    $valorAnterior = normalizarNumeroRelatorio($valorAnterior);

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

    $classeAtual = classificarValorMetricaRelatorio($config, $valorAtual);
    $classeAnterior = classificarValorMetricaRelatorio($config, $valorAnterior);

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

function formatarValorRelatorio(string $chave, $valor): string
{
    $moneyKeys = ['valor_gasto', 'cpc', 'cpl', 'cpm', 'custo_por_compra', 'custo_por_conversa', 'custo_resultado'];
    $percentKeys = ['ctr', 'taxa_conversao'];
    $integerKeys = ['impressoes', 'cliques_link', 'leads', 'resultados', 'compras', 'conversas_whatsapp'];

    if (in_array($chave, $moneyKeys, true)) {
        return moneyBr((float) $valor);
    }

    if (in_array($chave, $percentKeys, true)) {
        return numberBr($valor, 2) . '%';
    }

    if (in_array($chave, $integerKeys, true)) {
        return numberBr($valor);
    }

    if ($chave === 'roas') {
        return numberBr($valor, 2) . 'x';
    }

    return numberBr($valor, 2);
}



/* =========================
   CONFIG MÉTRICAS
========================= */
$configMetricasJson = carregarConfigMetricasClienteRelatorio($conn, $contaId);
$configMetricas = $configMetricasJson['metricas'] ?? [];

if (empty($configMetricas)) {
    $configMetricas = [
        'ctr' => ['label' => 'CTR', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
        'resultados' => ['label' => 'Resultados', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
        'cpl' => ['label' => 'CPL', 'tipo_leitura' => 'menor_melhor', 'ativo' => true],
        'compras' => ['label' => 'Compras', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
        'custo_por_compra' => ['label' => 'Custo por Compra', 'tipo_leitura' => 'menor_melhor', 'ativo' => true],
        'roas' => ['label' => 'ROAS', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
        'cpm' => ['label' => 'CPM', 'tipo_leitura' => 'menor_melhor', 'ativo' => true],
        'cpc' => ['label' => 'CPC', 'tipo_leitura' => 'menor_melhor', 'ativo' => true],
        'conversas_whatsapp' => ['label' => 'Conversas WhatsApp', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
        'custo_por_conversa' => ['label' => 'Custo por Conversa', 'tipo_leitura' => 'menor_melhor', 'ativo' => true],
        'taxa_conversao' => ['label' => 'Taxa de Conversão', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
        'frequencia' => ['label' => 'Frequência', 'tipo_leitura' => 'faixa_ideal', 'ativo' => true],
        'cliques_link' => ['label' => 'Cliques no Link', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
        'alcance' => ['label' => 'Alcance', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
        'impressoes' => ['label' => 'Impressões', 'tipo_leitura' => 'maior_melhor', 'ativo' => true],
    ];
}

$funilMetricas = montarFunilMetricasRelatorio($configMetricas, $resumo, $resumoAnterior);

/* =========================
   MAPA DE CARDS
========================= */
$metricCards = [
    [
        'key' => 'ctr',
        'label' => 'CTR',
        'value' => $resumo['ctr'] ?? 0,
        'previous' => $resumoAnterior['ctr'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'ctr',
    ],
    [
        'key' => 'resultados',
        'label' => 'Resultados',
        'value' => $resumo['resultados'] ?? ($resumo['leads'] ?? 0),
        'previous' => $resumoAnterior['resultados'] ?? ($resumoAnterior['leads'] ?? 0),
        'sub' => !empty($resumo['nome_resultado']) ? $resumo['nome_resultado'] : 'Leads/Conversões',
        'config_key' => 'resultados',
    ],
    [
        'key' => 'cpl',
        'label' => 'CPL',
        'value' => $resumo['cpl'] ?? 0,
        'previous' => $resumoAnterior['cpl'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'cpl',
    ],
    [
        'key' => 'compras',
        'label' => 'Compras',
        'value' => $resumo['compras'] ?? 0,
        'previous' => $resumoAnterior['compras'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'compras',
    ],
    [
        'key' => 'custo_por_compra',
        'label' => 'Custo por Compra',
        'value' => $resumo['custo_por_compra'] ?? 0,
        'previous' => $resumoAnterior['custo_por_compra'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'custo_por_compra',
    ],
    [
        'key' => 'roas',
        'label' => 'ROAS',
        'value' => $resumo['roas'] ?? 0,
        'previous' => $resumoAnterior['roas'] ?? 0,
        'sub' => 'Retorno sobre investimento',
        'config_key' => 'roas',
    ],
    [
        'key' => 'cpm',
        'label' => 'CPM',
        'value' => $resumo['cpm'] ?? 0,
        'previous' => $resumoAnterior['cpm'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'cpm',
    ],
    [
        'key' => 'cpc',
        'label' => 'CPC',
        'value' => $resumo['cpc'] ?? 0,
        'previous' => $resumoAnterior['cpc'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'cpc',
    ],
    [
        'key' => 'conversas_whatsapp',
        'label' => 'Conversas WhatsApp',
        'value' => $resumo['conversas_whatsapp'] ?? 0,
        'previous' => $resumoAnterior['conversas_whatsapp'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'conversas_whatsapp',
    ],
    [
        'key' => 'custo_por_conversa',
        'label' => 'Custo por Conversa',
        'value' => $resumo['custo_por_conversa'] ?? 0,
        'previous' => $resumoAnterior['custo_por_conversa'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'custo_por_conversa',
    ],
    [
        'key' => 'taxa_conversao',
        'label' => 'Taxa de Conversão',
        'value' => $resumo['taxa_conversao'] ?? 0,
        'previous' => $resumoAnterior['taxa_conversao'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'taxa_conversao',
    ],
    [
        'key' => 'frequencia',
        'label' => 'Frequência',
        'value' => $resumo['frequencia'] ?? 0,
        'previous' => $resumoAnterior['frequencia'] ?? 0,
        'sub' => 'Controle de repetição',
        'config_key' => 'frequencia',
    ],
    [
        'key' => 'cliques_link',
        'label' => 'Cliques no Link',
        'value' => $resumo['cliques_link'] ?? 0,
        'previous' => $resumoAnterior['cliques_link'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'cliques_link',
    ],
    [
        'key' => 'alcance',
        'label' => 'Alcance',
        'value' => $resumo['alcance'] ?? 0,
        'previous' => $resumoAnterior['alcance'] ?? 0,
        'sub' => 'Único estimado',
        'config_key' => 'alcance',
    ],
    [
        'key' => 'impressoes',
        'label' => 'Impressões',
        'value' => $resumo['impressoes'] ?? 0,
        'previous' => $resumoAnterior['impressoes'] ?? 0,
        'sub' => 'Comparado ao período anterior',
        'config_key' => 'impressoes',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/relatorio-view.css">
</head>

<body>
    <div class="page">
        <?php if (!$printMode): ?>
            <div class="actions">
                <button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button>
            </div>
        <?php endif; ?>

        <div class="header">
            <div>
                <h1>Relatório de Performance</h1>
                <p class="header-subtitle">Visão executiva das campanhas, com foco em investimento, eficiência e geração de resultados no período selecionado.</p>
                <p><strong>Cliente:</strong> <?= htmlspecialchars($cliente['nome'] ?? 'Todos'); ?></p>
                <p><strong>Conta:</strong> <?= htmlspecialchars($conta['nome'] ?? 'Todas'); ?></p>
                <p><strong>Campanha:</strong> <?= htmlspecialchars($campanha['nome'] ?? 'Todas'); ?></p>
            </div>
            <div>
                <p><strong>Período:</strong> <?= date('d/m/Y', strtotime($dataInicio)); ?> até <?= date('d/m/Y', strtotime($dataFim)); ?></p>
                <p><strong>Comparação:</strong> <?= date('d/m/Y', strtotime($dataInicioAnterior)); ?> até <?= date('d/m/Y', strtotime($dataFimAnterior)); ?></p>
                <p><strong>Gerado em:</strong> <?= date('d/m/Y H:i'); ?></p>
                <p><strong>Sistema:</strong> Dashboard ALP</p>
            </div>
        </div>

        <div class="grid">
            <?php foreach ($metricCards as $item): ?>
                <?php
                $configKey = $item['config_key'] ?? $item['key'];

                if (!metricaAtivaRelatorio($configMetricas, $configKey)) {
                    continue;
                }

                $config = $configMetricas[$configKey] ?? [];
                $valorAtual = $item['value'] ?? 0;
                $valorAnterior = $item['previous'] ?? 0;

                $classeValor = classificarValorMetricaRelatorio($config, $valorAtual);
                $estado = getEstadoMetricaRelatorio($config, $valorAtual);
                $classeTrend = classificarTrendRelatorio($config, $valorAtual, $valorAnterior);
                $variacao = calcularVariacaoPercentualRelatorio($valorAtual, $valorAnterior);

                $trendUp = normalizarNumeroRelatorio($valorAtual) >= normalizarNumeroRelatorio($valorAnterior);
                ?>
                <div class="card metric-card metric-dynamic metric-state-<?= htmlspecialchars($estado); ?>">
                    <div class="metric-head">
                        <span class="metric-label"><?= htmlspecialchars($item['label']); ?></span>

                        <span class="metric-trend <?= htmlspecialchars($classeTrend); ?>">
                            <?php if ($variacao !== null): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <?php if ($trendUp): ?>
                                        <path d="M7 17L17 7"></path>
                                        <path d="M8 7h9v9"></path>
                                    <?php else: ?>
                                        <path d="M7 7l10 10"></path>
                                        <path d="M8 17h9V8"></path>
                                    <?php endif; ?>
                                </svg>
                                <?= htmlspecialchars(number_format(abs($variacao), 1, ',', '.')); ?>%
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="metric-value <?= htmlspecialchars($classeValor); ?>">
                        <?= htmlspecialchars(formatarValorRelatorio($item['key'], $valorAtual)); ?>
                    </div>

                    <div class="metric-sub"><?= htmlspecialchars($item['sub']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Evolução do período</h2>
            <div class="chart-card">
                <div class="chart-wrap">
                    <canvas id="relatorioChart"></canvas>
                </div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Funil de Conversão</h2>

            <?php if (empty($funilMetricas)): ?>
                <div class="empty-funnel">
                    Nenhuma etapa suficiente para montar o funil neste período.
                </div>
            <?php else: ?>
                <div class="funnel-premium">
                    <?php
                    $totalEtapas = count($funilMetricas);
                    foreach ($funilMetricas as $index => $item):
                        $width = 100 - ($index * 6);
                        if ($width < 62) $width = 62;
                    ?>
                        <div
                            class="funnel-card funnel-state-<?= htmlspecialchars($item['estado']); ?>"
                            style="width: <?= $width; ?>%;">
                            <div class="funnel-top">
                                <span class="funnel-label"><?= htmlspecialchars($item['label']); ?></span>
                                <span class="funnel-top-percent">
                                    <?= number_format($item['percentual_topo'] ?? 100, 1, ',', '.'); ?>% do topo
                                </span>
                            </div>

                            <div class="funnel-value">
                                <?= htmlspecialchars($item['valor_formatado']); ?>
                            </div>

                            <div class="funnel-meta">
                                <span>
                                    <?= number_format($item['percentual_etapa'] ?? 100, 1, ',', '.'); ?>% da etapa anterior
                                </span>

                                <span>
                                    <?php if ($item['variacao_percentual'] !== null): ?>
                                        <?= number_format(abs($item['variacao_percentual']), 1, ',', '.'); ?>% vs período anterior
                                    <?php else: ?>
                                        Sem base comparativa
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($index < $totalEtapas - 1): ?>
                            <div class="funnel-connector">
                                <span></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Insights automáticos</h2>
            <div class="insights">
                <?php foreach ($insights as $insight): ?>
                    <div class="insight-item"><?= htmlspecialchars($insight); ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Campanhas do período</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Campanha</th>
                            <th>Status</th>
                            <th>Invest.</th>
                            <th>Imp.</th>
                            <th>Cliques</th>
                            <th>CTR</th>
                            <th>CPC</th>
                            <th>Leads</th>
                            <th>CPL</th>
                            <th>ROAS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($campanhas)): ?>
                            <tr>
                                <td colspan="10" class="empty-state">Nenhum dado encontrado para o período selecionado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($campanhas as $camp): ?>
                                <?php
                                $status = strtoupper((string) $camp['status']);
                                $statusClass = 'tag-off';

                                if (in_array($status, ['ACTIVE', 'ATIVO'], true)) {
                                    $statusClass = 'tag-active';
                                } elseif (in_array($status, ['PAUSED'], true)) {
                                    $statusClass = 'tag-paused';
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($camp['nome']); ?></td>
                                    <td><span class="tag <?= $statusClass; ?>"><?= htmlspecialchars($camp['status']); ?></span></td>
                                    <td><?= moneyBr((float) $camp['valor_gasto']); ?></td>
                                    <td><?= numberBr($camp['impressoes']); ?></td>
                                    <td><?= numberBr($camp['cliques_link']); ?></td>
                                    <td><?= numberBr($camp['ctr'], 2); ?>%</td>
                                    <td><?= moneyBr((float) $camp['cpc']); ?></td>
                                    <td><?= numberBr($camp['leads']); ?></td>
                                    <td><?= moneyBr((float) $camp['cpl']); ?></td>
                                    <td><?= numberBr($camp['roas'], 2); ?>x</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const labels = <?= json_encode($serie['labels'], JSON_UNESCAPED_UNICODE); ?>;
        const dataGasto = <?= json_encode($serie['gasto']); ?>;
        const dataLeads = <?= json_encode($serie['leads']); ?>;
        const dataCliques = <?= json_encode($serie['cliques']); ?>;

        const ctx = document.getElementById('relatorioChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                            label: 'Investimento',
                            data: dataGasto,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,0.10)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Cliques',
                            data: dataCliques,
                            borderColor: '#16a34a',
                            tension: 0.4,
                            borderWidth: 2,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'Leads',
                            data: dataLeads,
                            borderColor: '#d97706',
                            tension: 0.4,
                            borderWidth: 2,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'start',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 10,
                                boxHeight: 10,
                                padding: 18,
                                color: '#475569',
                                font: {
                                    size: 12,
                                    weight: '600'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#ffffff',
                            bodyColor: '#e2e8f0',
                            padding: 12,
                            cornerRadius: 12,
                            displayColors: true
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            grid: {
                                color: 'rgba(148, 163, 184, 0.16)'
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }

        <?php if ($printMode): ?>
            window.onload = function() {
                window.print();
            };
        <?php endif; ?>
    </script>
</body>

</html>