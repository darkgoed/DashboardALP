<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();
$dashboardPageService = new DashboardPageService($conn, $empresaId);
$pageData = $dashboardPageService->build($_GET);

$contaId = $pageData['contaId'];
$campanhaId = $pageData['campanhaId'];
$campanhaStatus = $pageData['campanhaStatus'];
$periodo = $pageData['periodo'];
$dataInicio = $pageData['dataInicio'];
$dataFim = $pageData['dataFim'];
$filters = $pageData['filters'];
$filtrosComparacao = $pageData['filtrosComparacao'];
$dashboard = $pageData['dashboard'];
$resumo = $pageData['resumo'];
$contexto = $pageData['contexto'];
$periodoResolvido = $pageData['periodoResolvido'];
$dataInicioAtual = $pageData['dataInicioAtual'];
$dataFimAtual = $pageData['dataFimAtual'];
$mercadoPhoneResumo = $pageData['mercadoPhoneResumo'];
$mercadoPhoneResumoAnterior = $pageData['mercadoPhoneResumoAnterior'];
$dashboardAnterior = $pageData['dashboardAnterior'];
$resumoAnterior = $pageData['resumoAnterior'];
$contas = $pageData['contas'];
$campanhas = $pageData['campanhas'];
$relatoriosUrl = $pageData['relatoriosUrl'];
$dashboardMetaSummaryService = new DashboardMetaSummaryService($conn);

$resumoMetaAtual = $dashboardMetaSummaryService->loadMetaPeriodSummary(
    $empresaId,
    $contaId,
    $campanhaId,
    $dataInicioAtual,
    $dataFimAtual,
    $campanhaStatus ?: null
);
$dashboardMetaSummaryService->applyMetaSummary($resumo, $resumoMetaAtual);

$resumoMetaAnterior = $dashboardMetaSummaryService->loadMetaPeriodSummary(
    $empresaId,
    $contaId,
    $campanhaId,
    $filtrosComparacao['data_inicio'],
    $filtrosComparacao['data_fim'],
    $campanhaStatus ?: null
);
$dashboardMetaSummaryService->applyMetaSummary($resumoAnterior, $resumoMetaAnterior);

$resumoMetricasAnterior = DashboardMetricsHelper::buildMetricsSummary($resumoAnterior);

$configMetricasJson = $dashboardMetaSummaryService->loadMetricConfig($contaId);
$configMetricas = $configMetricasJson['metricas'] ?? [];
$resumoMetricas = DashboardMetricsHelper::buildMetricsSummary($resumo);

$statusEmpresa = EmpresaAccessGuard::check($conn);

$mostrarAvisoTolerancia = !empty($statusEmpresa['em_tolerancia']);
$mensagemTolerancia = $mostrarAvisoTolerancia
    ? 'Sua empresa está em período de tolerância. Regularize a assinatura para evitar bloqueio.'
    : null;

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

$funilMetricas = DashboardMetricsHelper::buildFunnelMetrics(
    $configMetricas,
    $resumoMetricas,
    $resumoMetricasAnterior,
    'obterLabelDashboard',
    'formatarValorDashboard'
);

if (!empty($mercadoPhoneResumo) && (int) ($mercadoPhoneResumo['pedidos'] ?? 0) > 0) {
    $pedidoAtual = (int) ($mercadoPhoneResumo['pedidos'] ?? 0);
    $pedidoAnterior = (int) ($mercadoPhoneResumoAnterior['pedidos'] ?? 0);

    $valorTopoFunil = !empty($funilMetricas)
        ? DashboardMetricsHelper::normalizeNumber($funilMetricas[0]['valor'] ?? 0)
        : $pedidoAtual;

    $valorEtapaAnterior = !empty($funilMetricas)
        ? DashboardMetricsHelper::normalizeNumber($funilMetricas[count($funilMetricas) - 1]['valor'] ?? 0)
        : $pedidoAtual;

    $funilMetricas[] = [
        'chave' => 'mercado_phone_pedidos',
        'label' => 'Pedidos',
        'valor' => $pedidoAtual,
        'valor_formatado' => formatInt($pedidoAtual),
        'estado' => 'neutral',
        'cor' => '#3b82f6',
        'cor_fundo' => 'rgba(59, 130, 246, 0.14)',
        'percentual_etapa' => empty($funilMetricas) ? 100 : DashboardMetricsHelper::calculateSafePercent($pedidoAtual, $valorEtapaAnterior),
        'percentual_topo' => empty($funilMetricas) ? 100 : DashboardMetricsHelper::calculateSafePercent($pedidoAtual, $valorTopoFunil),
        'variacao_percentual' => DashboardMetricsHelper::calculateVariationPercent($pedidoAtual, $pedidoAnterior),
    ];
}

foreach ($configMetricas as $chave => $config) {
    $valorAtual = $resumoMetricas[$chave] ?? 0;
    $valorAnterior = $resumoMetricasAnterior[$chave] ?? 0;

    $estado = DashboardMetricsHelper::getMetricState($config, $valorAtual);
    $cores = DashboardMetricsHelper::getColorsByState($estado);

    $metricasVisuais[$chave] = [
        'estado' => $estado,
        'cor' => $cores['solid'],
        'cor_fundo' => $cores['soft'],
        'variacao_percentual' => DashboardMetricsHelper::calculateVariationPercent($valorAtual, $valorAnterior)
    ];
}

uasort($configMetricas, function ($a, $b) {
    $pesoA = (int)($a['peso'] ?? 0);
    $pesoB = (int)($b['peso'] ?? 0);
    return $pesoB <=> $pesoA;
});

$mostrarFiltrosSidebar = true;
$campanha_id = $campanhaId ?? '';
$campanhaStatus = $campanhaStatus ?? '';
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

function formatarValorMercadoPhoneDashboard(string $chave, $valor): string
{
    if ($chave === 'faturamento' || $chave === 'ticket_medio') {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    return number_format((float) $valor, 0, ',', '.');
}

function obterEstadoMercadoPhoneDashboard($valorAtual, $valorAnterior): string
{
    $valorAtual = DashboardMetricsHelper::normalizeNumber($valorAtual);
    $valorAnterior = DashboardMetricsHelper::normalizeNumber($valorAnterior);

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

function obterClasseTrendMercadoPhoneDashboard($valorAtual, $valorAnterior): string
{
    $valorAtual = DashboardMetricsHelper::normalizeNumber($valorAtual);
    $valorAnterior = DashboardMetricsHelper::normalizeNumber($valorAnterior);

    if ($valorAnterior == 0.0 || $valorAtual == $valorAnterior) {
        return 'metric-trend-neutral';
    }

    return $valorAtual > $valorAnterior
        ? 'metric-trend-up-good'
        : 'metric-trend-down-bad';
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
        'valor_gasto',
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
        'valor_gasto' => 'Valor Gasto',
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
    <script>
        (function() {
            const savedTheme = localStorage.getItem("theme") || "dark";
            document.documentElement.setAttribute("data-theme", savedTheme);
        })();
    </script>
</head>

<body class="page page-dashboard">

    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <?php if (!empty($mostrarAvisoTolerancia)): ?>
            <div class="alert alert-warning">
                <?= htmlspecialchars($mensagemTolerancia); ?>
            </div>
        <?php endif; ?>
        
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
                    <a href="<?= htmlspecialchars($relatoriosUrl); ?>" class="btn btn-top">
                        <i data-lucide="file-text"></i>
                        <span>Gerar Relatório</span>
                    </a>
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

                    $classeValor = DashboardMetricsHelper::classifyMetricValue($config, $valorAtual);
                    $estado = DashboardMetricsHelper::getMetricState($config, $valorAtual);
                    $classeTrend = DashboardMetricsHelper::classifyTrend($config, $valorAtual, $valorAnterior);


                    $variacao = DashboardMetricsHelper::calculateVariationPercent($valorAtual, $valorAnterior);
                    $trendUp = DashboardMetricsHelper::normalizeNumber($valorAtual) >= DashboardMetricsHelper::normalizeNumber($valorAnterior);

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

            <?php if (!empty($mercadoPhoneResumo)): ?>
                <?php
                $mercadoPhoneCards = [
                    ['key' => 'pedidos', 'label' => 'Pedidos'],
                    ['key' => 'faturamento', 'label' => 'Faturamento'],
                    ['key' => 'itens_vendidos', 'label' => 'Itens Vendidos'],
                    ['key' => 'ticket_medio', 'label' => 'Ticket Médio'],
                ];
                ?>
                <section class="panel" style="margin-top: 24px; margin-bottom: 28px;">
                    <div class="panel-header">
                        <div>
                            <h3>Mercado Phone</h3>
                            <p class="panel-subtitle">
                                Métricas comerciais exibidas apenas para clientes com integração ativa e token configurado.
                            </p>
                        </div>
                    </div>

                    <div class="cards-grid" style="margin-top: 10px;">
                        <?php foreach ($mercadoPhoneCards as $mpCard): ?>
                            <?php
                            $chave = $mpCard['key'];
                            $valorAtual = $mercadoPhoneResumo[$chave] ?? 0;
                            $valorAnterior = $mercadoPhoneResumoAnterior[$chave] ?? 0;
                            $variacao = DashboardMetricsHelper::calculateVariationPercent($valorAtual, $valorAnterior);
                            $trendUp = DashboardMetricsHelper::normalizeNumber($valorAtual) >= DashboardMetricsHelper::normalizeNumber($valorAnterior);
                            $estadoMp = obterEstadoMercadoPhoneDashboard($valorAtual, $valorAnterior);
                            $classeTrendMp = obterClasseTrendMercadoPhoneDashboard($valorAtual, $valorAnterior);
                            ?>
                            <div class="metric-card metric-dynamic metric-state-<?= htmlspecialchars($estadoMp) ?>">
                                <div class="metric-head">
                                    <span class="metric-label"><?= htmlspecialchars($mpCard['label']) ?></span>
                                    <span class="metric-trend <?= htmlspecialchars($classeTrendMp) ?>">
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
                                            -
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="metric-value metric-value-neutral">
                                    <?= htmlspecialchars(formatarValorMercadoPhoneDashboard($chave, $valorAtual)) ?>
                                </div>

                                <span class="metric-extra">
                                    <?= !empty($mercadoPhoneResumo['has_data']) ? 'Comparado ao periodo anterior' : 'Integracao ativa aguardando dados sincronizados' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

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
    <script src="../assets/js/dashboard.js?v=<?= time(); ?>"></script>


    <script>
        const themeSelect = document.getElementById("theme-select");

        if (themeSelect) {
            const currentTheme = localStorage.getItem("theme") || "dark";
            themeSelect.value = currentTheme;

            themeSelect.addEventListener("change", function() {
                const selectedTheme = this.value;

                document.documentElement.setAttribute("data-theme", selectedTheme);
                localStorage.setItem("theme", selectedTheme);

                window.dispatchEvent(new Event("themechange"));
            });
        }
    </script>

    <script src="../assets/js/bootstrap.js"></script>

</body>

</html>
