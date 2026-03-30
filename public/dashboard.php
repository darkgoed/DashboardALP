<!-- dashboard -->

<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

$db = new Database();
$conn = $db->connect();

$clienteModel = new Cliente($conn, $empresaId);
$contaModel = new ContaAds($conn, $empresaId);
$campanhaModel = new Campanha($conn, $empresaId);
$metricsService = new MetricsService($conn);

$contaId = isset($_GET['conta_id']) && $_GET['conta_id'] !== ''
    ? (int) $_GET['conta_id']
    : null;

$campanhaId = isset($_GET['campanha_id']) && $_GET['campanha_id'] !== ''
    ? (int) $_GET['campanha_id']
    : null;

$periodo = isset($_GET['periodo']) && $_GET['periodo'] !== ''
    ? $_GET['periodo']
    : '90';

$dataInicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
$dataFim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';

$filters = [
    'empresa_id'  => $empresaId,
    'conta_id'    => $contaId,
    'campanha_id' => $campanhaId,
    'periodo'     => $periodo,
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
];

if (!empty($contaId) && !empty($campanhaId)) {
    $campanhasDaConta = $campanhaModel->getByConta($contaId);
    $campanhaValida = false;

    foreach ($campanhasDaConta as $camp) {
        if ((string)$camp['id'] === (string)$campanhaId) {
            $campanhaValida = true;
            break;
        }
    }

    if (!$campanhaValida) {
        $campanhaId = '';
    }
}

$dashboard = $metricsService->getDashboardData($filters);
$filtrosComparacao = $filters;

$resumo = $dashboard['resumo'] ?? [];
$contexto = $dashboard['contexto'] ?? [];
$periodoResolvido = $dashboard['periodo'] ?? [
    'data_inicio' => date('Y-m-d'),
    'data_fim' => date('Y-m-d')
];

$dataInicioAtual = !empty($periodoResolvido['data_inicio']) ? $periodoResolvido['data_inicio'] : date('Y-m-d');
$dataFimAtual = !empty($periodoResolvido['data_fim']) ? $periodoResolvido['data_fim'] : date('Y-m-d');

$inicioTs = strtotime($dataInicioAtual);
$fimTs = strtotime($dataFimAtual);

$diasPeriodo = 1;
if ($inicioTs && $fimTs && $fimTs >= $inicioTs) {
    $diasPeriodo = (int) floor(($fimTs - $inicioTs) / 86400) + 1;
}

$filtrosComparacao['periodo'] = 'custom';
$filtrosComparacao['data_fim'] = date('Y-m-d', strtotime($dataInicioAtual . ' -1 day'));
$filtrosComparacao['data_inicio'] = date('Y-m-d', strtotime($filtrosComparacao['data_fim'] . ' -' . ($diasPeriodo - 1) . ' days'));

$dashboardAnterior = $metricsService->getDashboardData($filtrosComparacao);
$resumoAnterior = $dashboardAnterior['resumo'] ?? [];
$resumoMetricasAnterior = obterResumoMetricasDashboard($resumoAnterior);

$configMetricasJson = carregarConfigMetricasCliente($conn, $contaId);
$configMetricas = $configMetricasJson['metricas'] ?? [];
$resumoMetricas = obterResumoMetricasDashboard($resumo);

/* fallback */
if (empty($configMetricas)) {
    $configMetricas = [
        'gasto' => ['label' => 'Gasto Total', 'ativo' => true, 'peso' => 100],
        'impressoes' => ['label' => 'Impressões', 'ativo' => true, 'peso' => 95],
        'alcance' => ['label' => 'Alcance', 'ativo' => true, 'peso' => 90],
        'cliques' => ['label' => 'Cliques', 'ativo' => true, 'peso' => 85],
        'cliques_link' => ['label' => 'Cliques no Link', 'ativo' => true, 'peso' => 80],
        'ctr' => ['label' => 'CTR', 'ativo' => true, 'peso' => 75],
        'cpc' => ['label' => 'CPC', 'ativo' => true, 'peso' => 70],
        'cpm' => ['label' => 'CPM', 'ativo' => true, 'peso' => 65],
        'resultados' => ['label' => 'Resultados', 'ativo' => true, 'peso' => 60],
        'custo_resultado' => ['label' => 'Custo por Resultado', 'ativo' => true, 'peso' => 55],
    ];
}

/* AQUI SIM entra o foreach */
$metricasVisuais = [];

$funilMetricas = montarFunilMetricasDashboard(
    $configMetricas,
    $resumoMetricas,
    $resumoMetricasAnterior
);

foreach ($configMetricas as $chave => $config) {
    $valorAtual = $resumoMetricas[$chave] ?? 0;
    $valorAnterior = $resumoMetricasAnterior[$chave] ?? 0;

    $estado = getEstadoMetrica($config, $valorAtual);
    $cores = obterCoresPorEstado($estado);

    $metricasVisuais[$chave] = [
        'estado' => $estado,
        'cor' => $cores['solid'],
        'cor_fundo' => $cores['soft'],
        'variacao_percentual' => calcularVariacaoPercentualDashboard($valorAtual, $valorAnterior)
    ];
}

uasort($configMetricas, function ($a, $b) {
    $pesoA = (int)($a['peso'] ?? 0);
    $pesoB = (int)($b['peso'] ?? 0);
    return $pesoB <=> $pesoA;
});

$contas = $contaModel->getAll();
$campanhas = $campanhaModel->getByConta($contaId);

$mostrarFiltrosSidebar = true;
$campanha_id = $campanhaId ?? '';
$data_inicio = $dataInicio ?? '';
$data_fim = $dataFim ?? '';

function formatMoney($value)
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatInt($value)
{
    return number_format((int)$value, 0, ',', '.');
}

function formatDecimal($value, $casas = 2)
{
    return number_format((float)$value, $casas, ',', '.');
}

function formatPercent($value, $casas = 2)
{
    return number_format((float)$value, $casas, ',', '.') . '%';
}

function carregarConfigMetricasCliente(PDO $conn, $contaId): array
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

    if (!is_array($json)) {
        return [];
    }

    return $json;
}

function metricaAtiva(array $configMetricas, string $chave): bool
{
    return !empty($configMetricas[$chave]['ativo']);
}

function formatarValorDashboard(string $chave, $valor, array $config = []): string
{
    $unit = $config['unit'] ?? '';

    $moneyKeys = [
        'gasto',
        'gasto_total',
        'cpc',
        'cpm',
        'cpl',
        'cpa',
        'custo_resultado',
        'custo_por_resultado',
        'custo_por_conversa',
        'custo_por_compra',
        'receita'
    ];

    $percentKeys = [
        'ctr',
        'taxa_conversao',
        'roas_percent'
    ];

    $integerKeys = [
        'impressoes',
        'alcance',
        'cliques',
        'cliques_link',
        'resultados',
        'leads',
        'conversoes',
        'compras',
        'conversas_whatsapp'
    ];

    if ($unit === 'money' || in_array($chave, $moneyKeys, true)) {
        return formatMoney($valor);
    }

    if ($unit === 'percent' || in_array($chave, $percentKeys, true)) {
        return formatPercent($valor);
    }

    if ($unit === 'integer' || in_array($chave, $integerKeys, true)) {
        return formatInt($valor);
    }

    return formatDecimal($valor);
}

function obterLabelDashboard(string $chave, array $config = []): string
{
    if (!empty($config['label'])) {
        return $config['label'];
    }

    $labels = [
        'gasto' => 'Gasto Total',
        'gasto_total' => 'Gasto Total',
        'impressoes' => 'Impressões',
        'alcance' => 'Alcance',
        'frequencia' => 'Frequência',
        'cliques' => 'Cliques',
        'cliques_link' => 'Cliques no Link',
        'ctr' => 'CTR',
        'cpc' => 'CPC',
        'cpl' => 'CPL',
        'cpa' => 'CPA',
        'cpm' => 'CPM',
        'resultados' => 'Resultados',
        'custo_resultado' => 'Custo por Resultado',
        'custo_por_resultado' => 'Custo por Resultado',
        'leads' => 'Leads',
        'conversas_whatsapp' => 'Conversas WhatsApp',
        'custo_por_conversa' => 'Custo por Conversa',
        'conversoes' => 'Conversões',
        'compras' => 'Compras',
        'custo_por_compra' => 'Custo por Compra',
        'taxa_conversao' => 'Taxa de Conversão',
        'receita' => 'Receita',
        'roas' => 'ROAS',
    ];

    return $labels[$chave] ?? ucfirst(str_replace('_', ' ', $chave));
}

function obterResumoMetricasDashboard(array $resumo): array
{
    return [
        'gasto' => $resumo['gasto_total'] ?? 0,
        'impressoes' => $resumo['impressoes'] ?? 0,
        'alcance' => $resumo['alcance'] ?? 0,
        'frequencia' => $resumo['frequencia'] ?? 0,
        'cliques' => (int) $resumo['cliques_link'],
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

function normalizarNumeroDashboard($valor): float
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }

    return (float) str_replace(',', '.', (string) $valor);
}

function classificarValorMetricaDashboard(array $config, $valor): string
{
    $valor = normalizarNumeroDashboard($valor);

    $tipo = trim((string)($config['tipo_leitura'] ?? ''));

    $criticoMin = normalizarNumeroDashboard($config['critico_min'] ?? null);
    $alertaMin  = normalizarNumeroDashboard($config['alerta_min'] ?? null);
    $idealMin   = normalizarNumeroDashboard($config['ideal_min'] ?? null);
    $idealMax   = normalizarNumeroDashboard($config['ideal_max'] ?? null);
    $alertaMax  = normalizarNumeroDashboard($config['alerta_max'] ?? null);
    $criticoMax = normalizarNumeroDashboard($config['critico_max'] ?? null);

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

function getEstadoMetrica(array $config, $valor): string
{
    $classe = classificarValorMetricaDashboard($config, $valor);

    if ($classe === 'metric-value-good') return 'good';
    if ($classe === 'metric-value-warning') return 'warning';
    if ($classe === 'metric-value-bad') return 'bad';

    return 'neutral';
}

function obterCoresPorEstado(string $estado): array
{
    switch ($estado) {
        case 'good':
            return [
                'solid' => '#22c55e',
                'soft' => 'rgba(34, 197, 94, 0.18)'
            ];

        case 'warning':
            return [
                'solid' => '#f59e0b',
                'soft' => 'rgba(245, 158, 11, 0.18)'
            ];

        case 'bad':
            return [
                'solid' => '#ef4444',
                'soft' => 'rgba(239, 68, 68, 0.18)'
            ];

        default:
            return [
                'solid' => '#60a5fa',
                'soft' => 'rgba(96, 165, 250, 0.18)'
            ];
    }
}

function calcularPercentualSeguroDashboard($atual, $base): float
{
    $atual = normalizarNumeroDashboard($atual);
    $base = normalizarNumeroDashboard($base);

    if ($base <= 0) {
        return 0;
    }

    return ($atual / $base) * 100;
}

function obterChaveFunilDashboard(array $configMetricas, array $candidatas, string $fallback): string
{
    foreach ($candidatas as $chave) {
        if (isset($configMetricas[$chave])) {
            return $chave;
        }
    }

    return $fallback;
}

function montarFunilMetricasDashboard(array $configMetricas, array $resumoMetricas, array $resumoMetricasAnterior = []): array
{
    $chaveCliques = obterChaveFunilDashboard(
        $configMetricas,
        ['cliques_link', 'cliques'],
        'cliques'
    );

    $chaveResultados = obterChaveFunilDashboard(
        $configMetricas,
        ['resultados', 'leads', 'conversoes', 'compras', 'conversas_whatsapp'],
        'resultados'
    );

    $estrutura = [
        [
            'chave' => 'impressoes',
            'label' => obterLabelDashboard('impressoes', $configMetricas['impressoes'] ?? [])
        ],
        [
            'chave' => 'alcance',
            'label' => obterLabelDashboard('alcance', $configMetricas['alcance'] ?? [])
        ],
        [
            'chave' => $chaveCliques,
            'label' => obterLabelDashboard($chaveCliques, $configMetricas[$chaveCliques] ?? [])
        ],
        [
            'chave' => $chaveResultados,
            'label' => obterLabelDashboard($chaveResultados, $configMetricas[$chaveResultados] ?? [])
        ],
    ];

    $itens = [];
    $valorTopo = 0.0;

    foreach ($estrutura as $index => $item) {
        $chave = $item['chave'];
        $config = $configMetricas[$chave] ?? [];
        $valorAtual = $resumoMetricas[$chave] ?? 0;
        $valorAnterior = $resumoMetricasAnterior[$chave] ?? 0;

        if ($index === 0) {
            $valorTopo = normalizarNumeroDashboard($valorAtual);
        }

        $estado = getEstadoMetrica($config, $valorAtual);
        $cores = obterCoresPorEstado($estado);

        $valorEtapaAnterior = $index > 0
            ? normalizarNumeroDashboard($itens[$index - 1]['valor'])
            : normalizarNumeroDashboard($valorAtual);

        $itens[] = [
            'chave' => $chave,
            'label' => $item['label'],
            'valor' => $valorAtual,
            'valor_formatado' => formatarValorDashboard($chave, $valorAtual, $config),
            'estado' => $estado,
            'cor' => $cores['solid'],
            'cor_fundo' => $cores['soft'],
            'percentual_etapa' => $index === 0 ? 100 : calcularPercentualSeguroDashboard($valorAtual, $valorEtapaAnterior),
            'percentual_topo' => $index === 0 ? 100 : calcularPercentualSeguroDashboard($valorAtual, $valorTopo),
            'variacao_percentual' => calcularVariacaoPercentualDashboard($valorAtual, $valorAnterior),
        ];
    }

    return $itens;
}

function obterEstadoCardDashboard(string $classeValor): string
{
    if ($classeValor === 'metric-value-good') {
        return 'metric-state-good';
    }

    if ($classeValor === 'metric-value-warning') {
        return 'metric-state-warning';
    }

    if ($classeValor === 'metric-value-bad') {
        return 'metric-state-bad';
    }

    return 'metric-state-neutral';
}

function classificarTrendDashboard(array $config, $valorAtual, $valorAnterior): string
{
    $valorAtual = normalizarNumeroDashboard($valorAtual);
    $valorAnterior = normalizarNumeroDashboard($valorAnterior);

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

    $classeAtual = classificarValorMetricaDashboard($config, $valorAtual);
    $classeAnterior = classificarValorMetricaDashboard($config, $valorAnterior);

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

function calcularVariacaoPercentualDashboard($valorAtual, $valorAnterior): ?float
{
    $valorAtual = normalizarNumeroDashboard($valorAtual);
    $valorAnterior = normalizarNumeroDashboard($valorAnterior);

    if ($valorAnterior == 0.0) {
        return null;
    }

    return (($valorAtual - $valorAnterior) / $valorAnterior) * 100;
}

$dashboardJson = json_encode([
    'serie_gasto_resultado' => $dashboard['serie_gasto_resultado'] ?? [],
    'serie_custo_resultado' => $dashboard['serie_custo_resultado'] ?? [],
    'serie_freq_ctr'        => $dashboard['serie_freq_ctr'] ?? [],
    'funil'                 => $dashboard['funil'] ?? [],
    'funil_metricas'        => $funilMetricas ?? [],
    'metricas_visuais'      => $metricasVisuais,
    'config_metricas'       => $configMetricas,
], JSON_UNESCAPED_UNICODE);


?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/dashboard-metrics.css">
</head>

<body class="page page-dashboard">

    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <header class="topbar">
                <div>
                    <h1>Dashboard</h1>
                    <p>
                        <?= htmlspecialchars($contexto['conta_nome']); ?>
                        &gt;
                        <?= htmlspecialchars($contexto['campanha_nome']); ?>
                        &middot;
                        <?= htmlspecialchars($periodoResolvido['data_inicio']); ?>
                        até
                        <?= htmlspecialchars($periodoResolvido['data_fim']); ?>
                    </p>
                </div>

                <div class="topbar-right">
                    <button class="btn btn-top">
                        <i data-lucide="file-text"></i>
                        <span>Gerar Relatório</span>
                    </button>
                    <small>Dados do banco · <?= date('d/m/Y H:i'); ?></small>
                </div>
            </header>

            <section class="cards-grid">
                <?php foreach ($configMetricas as $chave => $config): ?>
                    <?php
                    if (!metricaAtiva($configMetricas, $chave)) {
                        continue;
                    }

                    if (!array_key_exists($chave, $resumoMetricas) && $chave !== 'custo_resultado') {
                        continue;
                    }

                    $valorAtual = $resumoMetricas[$chave] ?? 0;
                    $valorAnterior = $resumoMetricasAnterior[$chave] ?? 0;

                    $label = obterLabelDashboard($chave, $config);
                    $valorFormatado = formatarValorDashboard($chave, $valorAtual, $config);

                    $classeValor = classificarValorMetricaDashboard($config, $valorAtual);
                    $estado = getEstadoMetrica($config, $valorAtual);
                    $classeTrend = classificarTrendDashboard($config, $valorAtual, $valorAnterior);


                    $variacao = calcularVariacaoPercentualDashboard($valorAtual, $valorAnterior);
                    $trendUp = normalizarNumeroDashboard($valorAtual) >= normalizarNumeroDashboard($valorAnterior);

                    $small = '';
                    if ($chave === 'alcance') {
                        $small = 'Único estimado';
                    } elseif ($chave === 'resultados') {
                        $small = !empty($resumo['nome_resultado']) ? $resumo['nome_resultado'] : 'Leads/Conversões';
                    } elseif ($chave === 'frequencia') {
                        $small = 'Controle de repetição';
                    } elseif ($chave === 'roas') {
                        $small = 'Retorno sobre investimento';
                    }
                    ?>
                    <div class="metric-card metric-dynamic metric-state-<?= htmlspecialchars($estado) ?>">

                        <div class="metric-head">
                            <span class="metric-label"><?= htmlspecialchars($label) ?></span>

                            <span class="metric-trend <?= htmlspecialchars($classeTrend) ?>">
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
                                    <?= htmlspecialchars(number_format(abs($variacao), 1, ',', '.')) ?>%
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="metric-value <?= htmlspecialchars($classeValor) ?>">
                            <?= htmlspecialchars($valorFormatado) ?>
                        </div>

                        <?php if ($small !== ''): ?>
                            <small><?= htmlspecialchars($small) ?></small>
                        <?php else: ?>
                            <span class="metric-extra">
                                Comparado ao período anterior
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="charts-row">
                <div class="panel panel-lg">
                    <div class="panel-header">
                        <h3>Gasto &amp; Resultados</h3>
                    </div>
                    <div class="chart-box">
                        <canvas id="chartGastoResultado"></canvas>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Custo por Resultado</h3>
                    </div>
                    <div class="chart-box">
                        <canvas id="chartCustoResultado"></canvas>
                    </div>
                </div>
            </section>

            <section class="charts-row bottom-row">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Funil de Conversão</h3>
                    </div>
                    <div class="funnel-grid">
                        <?php foreach ($funilMetricas as $item): ?>
                            <div
                                class="funnel-item funnel-item-<?= htmlspecialchars($item['estado']) ?>"
                                style="--funnel-color: <?= htmlspecialchars($item['cor']) ?>; --funnel-bg: <?= htmlspecialchars($item['cor_fundo']) ?>;">
                                <span><?= htmlspecialchars($item['label']) ?></span>

                                <strong><?= htmlspecialchars($item['valor_formatado']) ?></strong>

                                <small>
                                    <?= number_format($item['percentual_etapa'], 1, ',', '.') ?>% da etapa anterior
                                    ·
                                    <?= number_format($item['percentual_topo'], 1, ',', '.') ?>% do topo
                                </small>

                                <em>
                                    <?php if ($item['variacao_percentual'] !== null): ?>
                                        <?= number_format(abs($item['variacao_percentual']), 1, ',', '.') ?>% vs período anterior
                                    <?php else: ?>
                                        Sem base comparativa
                                    <?php endif; ?>
                                </em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Frequência &amp; CTR</h3>
                    </div>
                    <div class="chart-box">
                        <canvas id="chartFreqCtr"></canvas>
                    </div>
                </div>
            </section>
        </main>

    </div>

    <script>
        window.dashboardData = <?= $dashboardJson ? $dashboardJson : '{}'; ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="../assets/js/dashboard.js"></script>

    <script src="../assets/js/nav-config.js"></script>

</body>

</html>