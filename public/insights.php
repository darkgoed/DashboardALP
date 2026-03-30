<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

$db = new Database();
$conn = $db->connect();

$clienteModel = new Cliente($conn, $empresaId);

$empresaId = Tenant::getEmpresaId();

$clienteModel = new Cliente($conn, $empresaId);
$contaModel = new ContaAds($conn, $empresaId);

$clientes = $clienteModel->getAll();

$clienteId = isset($_GET['cliente_id']) && $_GET['cliente_id'] !== ''
    ? (int)$_GET['cliente_id']
    : 0;

$contas = $clienteId > 0 ? $contaModel->getByCliente($clienteId) : [];

$contaId = isset($_GET['conta_id']) && $_GET['conta_id'] !== ''
    ? (int)$_GET['conta_id']
    : 0;

$nivel = $_GET['nivel'] ?? '';
$inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-29 days'));
$fim = $_GET['fim'] ?? date('Y-m-d');

$sql = "
    SELECT
        i.*,
        c.nome AS cliente_nome,
        ca.nome AS conta_nome,
        cp.nome AS campanha_nome,
        cj.nome AS conjunto_nome,
        a.nome AS anuncio_nome
    FROM insights_diarios i
    LEFT JOIN clientes c ON c.id = i.cliente_id
    LEFT JOIN contas_ads ca ON ca.id = i.conta_id
    LEFT JOIN campanhas cp ON cp.id = i.campanha_id
    LEFT JOIN conjuntos cj ON cj.id = i.conjunto_id
    LEFT JOIN anuncios a ON a.id = i.anuncio_id
    WHERE 1=1
";

$params = [];

if ($clienteId > 0) {
    $sql .= " AND i.cliente_id = :cliente_id";
    $params[':cliente_id'] = $clienteId;
}

if ($contaId > 0) {
    $sql .= " AND i.conta_id = :conta_id";
    $params[':conta_id'] = $contaId;
}

if ($nivel !== '') {
    $sql .= " AND i.nivel = :nivel";
    $params[':nivel'] = $nivel;
}

if ($inicio !== '' && $fim !== '') {
    $sql .= " AND i.data BETWEEN :inicio AND :fim";
    $params[':inicio'] = $inicio;
    $params[':fim'] = $fim;
}

$sql .= " ORDER BY i.data DESC, i.id DESC LIMIT 300";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

function moeda($valor)
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function numero($valor, $decimais = 0)
{
    return number_format((float)$valor, $decimais, ',', '.');
}

$totalInsights = count($lista);
$totalGasto = 0;
$totalLeads = 0;
$totalReceita = 0;

foreach ($lista as $item) {
    $totalGasto += (float)($item['gasto'] ?? 0);
    $totalLeads += (float)($item['leads'] ?? 0);
    $totalReceita += (float)($item['receita'] ?? 0);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insights - Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/global.css">
</head>

<body class="page page-insights">

    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 3v18h18"></path>
                            <path d="M7 14l4-4 3 3 5-7"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Análise</span>
                        <h1 class="page-title">Insights</h1>
                        <p class="page-subtitle">
                            Visualize os dados consolidados por cliente, conta, campanha, conjunto ou anúncio.
                            Use os filtros para investigar o desempenho e encontrar padrões na operação.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="insights.php" class="btn btn-secondary">Limpar visão</a>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Registros encontrados</span>
                    <strong><?= numero($totalInsights) ?></strong>
                    <small>Linhas retornadas na consulta</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Gasto total</span>
                    <strong><?= moeda($totalGasto) ?></strong>
                    <small>Somatório do período filtrado</small>
                </div>

                <div class="metric-card metric-green">
                    <span>Leads totais</span>
                    <strong><?= numero($totalLeads) ?></strong>
                    <small>Resultados encontrados no período</small>
                </div>

                <div class="metric-card">
                    <span>Receita total</span>
                    <strong><?= moeda($totalReceita) ?></strong>
                    <small>Valor consolidado da base</small>
                </div>
            </section>

            <section class="content-grid-wide">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Filtros de insights</h3>
                            <p class="panel-subtitle">
                                Refine a visualização por cliente, conta, nível e intervalo de datas.
                            </p>
                        </div>
                    </div>

                    <form method="GET" class="form-stack" id="filtrosInsights">
                        <div class="content-grid" style="grid-template-columns: repeat(5, minmax(0, 1fr));">
                            <div class="field field-select">
                                <label for="cliente_id">Cliente</label>
                                <select name="cliente_id" id="cliente_id" onchange="resetContaEEnviar()">
                                    <option value="">Todos</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= (int)$cliente['id'] ?>" <?= $clienteId === (int)$cliente['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cliente['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field field-select">
                                <label for="conta_id">Conta</label>
                                <select name="conta_id" id="conta_id" onchange="this.form.submit()">
                                    <option value="">Todas</option>
                                    <?php foreach ($contas as $conta): ?>
                                        <option value="<?= (int)$conta['id'] ?>" <?= $contaId === (int)$conta['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($conta['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field field-select">
                                <label for="nivel">Nível</label>
                                <select name="nivel" id="nivel" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <option value="account" <?= $nivel === 'account' ? 'selected' : '' ?>>Conta</option>
                                    <option value="campaign" <?= $nivel === 'campaign' ? 'selected' : '' ?>>Campanha</option>
                                    <option value="adset" <?= $nivel === 'adset' ? 'selected' : '' ?>>Conjunto</option>
                                    <option value="ad" <?= $nivel === 'ad' ? 'selected' : '' ?>>Anúncio</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="inicio">Início</label>
                                <input type="date" name="inicio" id="inicio" value="<?= htmlspecialchars($inicio) ?>" onchange="this.form.submit()">
                            </div>

                            <div class="field">
                                <label for="fim">Fim</label>
                                <input type="date" name="fim" id="fim" value="<?= htmlspecialchars($fim) ?>" onchange="this.form.submit()">
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="insights.php" class="btn btn-ghost">Limpar filtros</a>
                        </div>
                    </form>
                </div>

                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Tabela de insights</h3>
                            <p class="panel-subtitle">
                                Exibição detalhada da base retornada, limitada aos 300 registros mais recentes da consulta.
                            </p>
                        </div>

                        <div class="panel-actions">
                            <span class="badge badge-purple"><?= numero($totalInsights) ?> registros</span>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>Conta</th>
                                    <th>Nível</th>
                                    <th>Referência</th>
                                    <th>Gasto</th>
                                    <th>Impressões</th>
                                    <th>Cliques</th>
                                    <th>CTR</th>
                                    <th>Leads</th>
                                    <th>Conversões</th>
                                    <th>Receita</th>
                                    <th>ROAS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lista)): ?>
                                    <tr>
                                        <td colspan="13" style="text-align: center;">Nenhum insight encontrado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lista as $item): ?>
                                        <?php
                                        $referencia = '—';

                                        if ($item['nivel'] === 'campaign') {
                                            $referencia = $item['campanha_nome'] ?: 'Campanha #' . $item['campanha_id'];
                                        } elseif ($item['nivel'] === 'adset') {
                                            $referencia = $item['conjunto_nome'] ?: 'Conjunto #' . $item['conjunto_id'];
                                        } elseif ($item['nivel'] === 'ad') {
                                            $referencia = $item['anuncio_nome'] ?: 'Anúncio #' . $item['anuncio_id'];
                                        } elseif ($item['nivel'] === 'account') {
                                            $referencia = $item['conta_nome'] ?: 'Conta #' . $item['conta_id'];
                                        }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['data']) ?></td>
                                            <td><?= htmlspecialchars($item['cliente_nome'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($item['conta_nome'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($item['nivel']) ?></td>
                                            <td><?= htmlspecialchars($referencia) ?></td>
                                            <td><?= moeda($item['gasto'] ?? 0) ?></td>
                                            <td><?= numero($item['impressoes'] ?? 0) ?></td>
                                            <td><?= numero($item['cliques'] ?? 0) ?></td>
                                            <td><?= numero($item['ctr'] ?? 0, 2) ?>%</td>
                                            <td><?= numero($item['leads'] ?? 0) ?></td>
                                            <td><?= numero($item['conversoes'] ?? 0) ?></td>
                                            <td><?= moeda($item['receita'] ?? 0) ?></td>
                                            <td><?= numero($item['roas'] ?? 0, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
    lucide.createIcons();

    function resetContaEEnviar() {
        const conta = document.getElementById('conta_id');
        if (conta) {
            conta.value = '';
        }
        document.getElementById('filtrosInsights').submit();
    }
</script>

<script src="../assets/js/nav-config.js"></script>
    
</body>

</html>