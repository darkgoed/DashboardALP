<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

$db = new Database();
$conn = $db->connect();
try {
    $publicToken = trim((string) ($_GET['token'] ?? ''));
    $isPublicView = $publicToken !== '';

    if (!$isPublicView) {
        Auth::requireLogin();
        EmpresaAccessGuard::assertPodeOperar($conn);
    }

    $relatorioViewPageService = new RelatorioViewPageService($conn);
    $pageData = $relatorioViewPageService->build($_GET, !$isPublicView);

    $empresaId = $pageData['empresa_id'];
    $clienteId = $pageData['cliente_id'];
    $contaId = $pageData['conta_id'];
    $campanhaId = $pageData['campanha_id'];
    $campanhaStatus = $pageData['campanha_status'];
    $periodo = $pageData['periodo'];
    $dataInicio = $pageData['data_inicio'];
    $dataFim = $pageData['data_fim'];
    $dataInicioAnterior = $pageData['data_inicio_anterior'];
    $dataFimAnterior = $pageData['data_fim_anterior'];
    $printMode = $pageData['print_mode'];
    $cliente = $pageData['cliente'];
    $conta = $pageData['conta'];
    $campanha = $pageData['campanha'];
    $mercadoPhoneResumo = $pageData['mercado_phone_resumo'];
    $mercadoPhoneResumoAnterior = $pageData['mercado_phone_resumo_anterior'];
    $resumo = $pageData['resumo'];
    $resumoAnterior = $pageData['resumo_anterior'];
    $serie = $pageData['serie'];
    $campanhas = $pageData['campanhas'];
    $configMetricasJson = $pageData['config_metricas_json'];

    $relatorioService = new RelatorioService($conn, $empresaId);
    $insights = $relatorioService->getInsightsAutomaticos($resumo, $campanhas);
} catch (Throwable $e) {
    http_response_code(403);
    exit($e->getMessage());
}



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
    return RelatorioMetricsHelper::isMetricActive($configMetricas, $chave);
}

function calcularPercentualSeguroRelatorio($atual, $base): float
{
    return RelatorioMetricsHelper::calculateSafePercent($atual, $base);
}

function obterLabelRelatorio(string $chave, array $config = []): string
{
    return RelatorioMetricsHelper::resolveLabel($chave, $config);
}

function montarFunilMetricasRelatorio(array $configMetricas, array $resumo, array $resumoAnterior = []): array
{
    return RelatorioMetricsHelper::buildFunnelMetrics($configMetricas, $resumo, $resumoAnterior);
}

function classificarValorMetricaRelatorio(array $config, $valor): string
{
    return RelatorioMetricsHelper::classifyMetricValue($config, $valor);
}

function getEstadoMetricaRelatorio(array $config, $valor): string
{
    return RelatorioMetricsHelper::getMetricState($config, $valor);
}

function calcularVariacaoPercentualRelatorio($valorAtual, $valorAnterior): ?float
{
    return RelatorioMetricsHelper::calculateVariationPercent($valorAtual, $valorAnterior);
}

function classificarTrendRelatorio(array $config, $valorAtual, $valorAnterior): string
{
    return RelatorioMetricsHelper::classifyTrend($config, $valorAtual, $valorAnterior);
}

function formatarValorRelatorio(string $chave, $valor): string
{
    return RelatorioMetricsHelper::formatMetricValue($chave, $valor);
}

function formatarValorMercadoPhoneRelatorio(string $chave, $valor): string
{
    return RelatorioMetricsHelper::formatMercadoPhoneValue($chave, $valor);
}

function obterEstadoMercadoPhoneRelatorio($valorAtual, $valorAnterior): string
{
    return RelatorioMetricsHelper::getMercadoPhoneState($valorAtual, $valorAnterior);
}

function obterClasseTrendMercadoPhoneRelatorio($valorAtual, $valorAnterior): string
{
    return RelatorioMetricsHelper::getMercadoPhoneTrendClass($valorAtual, $valorAnterior);
}



/* =========================
   CONFIG MÉTRICAS
========================= */
$configMetricasJson = $configMetricasJson ?? [];
$configMetricas = $configMetricasJson['metricas'] ?? [];

if (empty($configMetricas)) {
    $configMetricas = [
        'valor_gasto' => ['label' => 'Valor Gasto', 'tipo_leitura' => 'faixa_ideal', 'ativo' => true],
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

if (!empty($mercadoPhoneResumo) && (int) ($mercadoPhoneResumo['pedidos'] ?? 0) > 0) {
    $pedidoAtual = (int) ($mercadoPhoneResumo['pedidos'] ?? 0);
    $pedidoAnterior = (int) ($mercadoPhoneResumoAnterior['pedidos'] ?? 0);

    $valorTopoFunil = !empty($funilMetricas)
        ? RelatorioMetricsHelper::normalizeNumber($funilMetricas[0]['valor'] ?? 0)
        : $pedidoAtual;

    $valorEtapaAnterior = !empty($funilMetricas)
        ? RelatorioMetricsHelper::normalizeNumber($funilMetricas[count($funilMetricas) - 1]['valor'] ?? 0)
        : $pedidoAtual;

    $funilMetricas[] = [
        'chave' => 'mercado_phone_pedidos',
        'label' => 'Pedidos',
        'valor' => $pedidoAtual,
        'valor_formatado' => numberBr($pedidoAtual),
        'estado' => 'neutral',
        'variacao_percentual' => calcularVariacaoPercentualRelatorio($pedidoAtual, $pedidoAnterior),
        'percentual_etapa' => empty($funilMetricas) ? 100 : calcularPercentualSeguroRelatorio($pedidoAtual, $valorEtapaAnterior),
        'percentual_topo' => empty($funilMetricas) ? 100 : calcularPercentualSeguroRelatorio($pedidoAtual, $valorTopoFunil),
    ];
}

/* =========================
   MAPA DE CARDS
========================= */
$metricCards = [
    [
        'key' => 'valor_gasto',
        'label' => 'Valor Gasto',
        'value' => $resumo['gasto_total'] ?? ($resumo['valor_gasto'] ?? 0),
        'previous' => $resumoAnterior['gasto_total'] ?? ($resumoAnterior['valor_gasto'] ?? 0),
        'sub' => 'Comparado ao perÃ­odo anterior',
        'config_key' => 'valor_gasto',
    ],
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

                $trendUp = RelatorioMetricsHelper::normalizeNumber($valorAtual) >= RelatorioMetricsHelper::normalizeNumber($valorAnterior);
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

        <?php if (!empty($mercadoPhoneResumo)): ?>
            <?php
            $mercadoPhoneCards = [
                ['key' => 'pedidos', 'label' => 'Pedidos'],
                ['key' => 'faturamento', 'label' => 'Faturamento'],
                ['key' => 'itens_vendidos', 'label' => 'Itens Vendidos'],
                ['key' => 'ticket_medio', 'label' => 'Ticket Medio'],
            ];
            ?>
            <div class="section">
                <h2 class="section-title">Mercado Phone</h2>
                <div class="grid">
                    <?php foreach ($mercadoPhoneCards as $mpCard): ?>
                        <?php
                        $chave = $mpCard['key'];
                        $valorAtual = $mercadoPhoneResumo[$chave] ?? 0;
                        $valorAnterior = $mercadoPhoneResumoAnterior[$chave] ?? 0;
                        $variacao = calcularVariacaoPercentualRelatorio($valorAtual, $valorAnterior);
                        $trendUp = RelatorioMetricsHelper::normalizeNumber($valorAtual) >= RelatorioMetricsHelper::normalizeNumber($valorAnterior);
                        $estadoMp = obterEstadoMercadoPhoneRelatorio($valorAtual, $valorAnterior);
                        $classeTrendMp = obterClasseTrendMercadoPhoneRelatorio($valorAtual, $valorAnterior);
                        ?>
                        <div class="card metric-card metric-dynamic metric-state-<?= htmlspecialchars($estadoMp); ?>">
                            <div class="metric-head">
                                <span class="metric-label"><?= htmlspecialchars($mpCard['label']); ?></span>
                                <span class="metric-trend <?= htmlspecialchars($classeTrendMp); ?>">
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
                                        -
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="metric-value metric-value-neutral">
                                <?= htmlspecialchars(formatarValorMercadoPhoneRelatorio($chave, $valorAtual)); ?>
                            </div>

                            <div class="metric-sub">
                                <?= !empty($mercadoPhoneResumo['has_data']) ? 'Comparado ao periodo anterior' : 'Integracao ativa aguardando dados sincronizados'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

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
