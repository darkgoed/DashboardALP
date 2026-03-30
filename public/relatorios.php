<!-- relatorios -->

<?php

require_once __DIR__ . '/../app/config/bootstrap.php';
require_once __DIR__ . '/../app/models/Cliente.php';
require_once __DIR__ . '/../app/models/ContaAds.php';
require_once __DIR__ . '/../app/models/Campanha.php';

Auth::requireLogin();

$empresaId = Tenant::getEmpresaId();

$db = new Database();
$conn = $db->connect();

$clienteModel  = new Cliente($conn, $empresaId);
$contaModel    = new ContaAds($conn, $empresaId);
$campanhaModel = new Campanha($conn, $empresaId);

$currentPage = 'relatorios';

$clientes = $clienteModel->getAll();

$clienteId  = isset($_GET['cliente_id']) && $_GET['cliente_id'] !== ''
    ? (int) $_GET['cliente_id']
    : 0;

$contaId = isset($_GET['conta_id']) && $_GET['conta_id'] !== ''
    ? (int) $_GET['conta_id']
    : 0;

$campanhaId = isset($_GET['campanha_id']) && $_GET['campanha_id'] !== ''
    ? (int) $_GET['campanha_id']
    : 0;

$periodo = isset($_GET['periodo']) && $_GET['periodo'] !== ''
    ? trim($_GET['periodo'])
    : '30';

$dataInicio = isset($_GET['data_inicio']) && $_GET['data_inicio'] !== ''
    ? $_GET['data_inicio']
    : date('Y-m-d', strtotime('-29 days'));

$dataFim = isset($_GET['data_fim']) && $_GET['data_fim'] !== ''
    ? $_GET['data_fim']
    : date('Y-m-d');

$periodosPermitidos = ['7', '15', '30', '90', 'custom'];
if (!in_array($periodo, $periodosPermitidos, true)) {
    $periodo = '30';
}

if ($periodo !== 'custom') {
    $dias = (int) $periodo;

    if ($dias > 0) {
        $dataInicio = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));
        $dataFim = date('Y-m-d');
    }
}

if (!$clienteId && $contaId) {
    $contaAtual = $contaModel->getById($contaId);
    if ($contaAtual && isset($contaAtual['cliente_id'])) {
        $clienteId = (int) $contaAtual['cliente_id'];
    }
}

$contas = $clienteId > 0
    ? $contaModel->getByCliente($clienteId)
    : [];

$campanhas = $contaId > 0
    ? $campanhaModel->getByConta($contaId)
    : [];

$queryData = [
    'cliente_id'      => $clienteId ?: '',
    'conta_id'        => $contaId ?: '',
    'campanha_id'     => $campanhaId ?: '',
    'periodo'         => $periodo,
    'data_inicio'     => $dataInicio,
    'data_fim'        => $dataFim,
];

$query = http_build_query($queryData);
$previewUrl = 'relatorio_view.php?' . $query;
$printUrl   = 'relatorio_view.php?' . $query . '&print=1';

function isSelected($value, $current): string
{
    return (string) $value === (string) $current ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatórios - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">

    <style>
        .page-relatorios .main {
            min-width: 0;
        }

        .page-relatorios .content-area {
            display: flex;
            flex-direction: column;
            gap: 18px;
            min-width: 0;
        }

        .page-relatorios .panel {
            width: 100%;
            min-width: 0;
        }

        .page-relatorios .filters-panel {
            padding: 18px;
        }

        .page-relatorios .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .page-relatorios .section-head h2 {
            margin: 0;
            font-size: 15px;
            line-height: 1.2;
        }

        .page-relatorios .section-head p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .page-relatorios .filters-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .page-relatorios .filters-grid {
            display: grid;
            grid-template-columns:
                minmax(160px, 1.2fr) minmax(160px, 1.2fr) minmax(160px, 1.2fr) minmax(160px, 1fr) minmax(160px, 1fr) minmax(150px, 0.95fr) minmax(150px, 0.95fr);
            gap: 12px;
            align-items: end;
            width: 100%;
        }

        .page-relatorios .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .page-relatorios .field label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-soft);
            line-height: 1.2;
            white-space: nowrap;
        }

        .page-relatorios .field select,
        .page-relatorios .field input[type="date"] {
            width: 100%;
        }

        .page-relatorios .actions-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .page-relatorios .btn {
            min-height: 40px;
        }

        .page-relatorios .btn-link-clean {
            text-decoration: none;
        }

        .page-relatorios .preview-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .page-relatorios .preview-head h2 {
            margin: 0;
            font-size: 15px;
            line-height: 1.2;
        }

        .page-relatorios .preview-head p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 12px;
        }

        .page-relatorios .preview-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-relatorios .preview-frame-wrap {
            width: 100%;
            min-width: 0;
            border-radius: 18px;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .page-relatorios .preview-frame {
            width: 100%;
            min-height: 1080px;
            border: none;
            display: block;
            background: #ffffff;
        }

        .page-relatorios .help-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .page-relatorios .help-card {
            padding: 15px 15px 14px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.02);
        }

        .page-relatorios .help-card strong {
            display: block;
            margin-bottom: 6px;
            color: var(--text-color);
            font-size: 12.5px;
        }

        .page-relatorios .help-card span {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 1480px) {
            .page-relatorios .filters-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 980px) {
            .page-relatorios .filters-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {

            .page-relatorios .filters-grid,
            .page-relatorios .help-grid {
                grid-template-columns: 1fr;
            }

            .page-relatorios .preview-frame {
                min-height: 860px;
            }
        }
    </style>
</head>

<body class="page page-relatorios">
    <div class="layout">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <div class="content-area">

                <section class="page-hero">
                    <div class="page-hero-left">

                        <div class="page-hero-icon">
                            <i data-lucide="scroll-text"></i>
                        </div>

                        <div>
                            <span class="page-kicker">Análise</span>
                            <h1 class="page-title">Relatórios</h1>
                            <p class="page-subtitle">
                                Visualize, filtre e exporte relatórios completos com base nos dados das campanhas.
                                Acompanhe desempenho, tendências e resultados de forma clara e estruturada.
                            </p>
                        </div>

                    </div>

                    <div class="page-hero-actions">
                        <a href="relatorios.php" class="btn btn-secondary">Atualizar tela</a>
                    </div>
                </section>

                <section class="relatorios-grid-top">
                    <div class="panel filters-panel">
                        <div class="section-head">
                            <div>
                                <h2>Filtros do relatório</h2>
                                <p>Defina o escopo do relatório e gere a visualização estruturada abaixo.</p>
                            </div>
                        </div>

                        <form method="GET" action="" class="filters-form">
                            <div class="filters-grid">

                                <div class="field">
                                    <label for="cliente_id">Cliente</label>
                                    <select name="cliente_id" id="cliente_id" onchange="this.form.submit()">
                                        <option value="">Todos</option>
                                        <?php foreach ($clientes as $cliente): ?>
                                            <option value="<?= (int) $cliente['id']; ?>" <?= isSelected($cliente['id'], $clienteId); ?>>
                                                <?= htmlspecialchars($cliente['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="conta_id">Conta</label>
                                    <select name="conta_id" id="conta_id" onchange="this.form.submit()">
                                        <option value="">Todas</option>
                                        <?php foreach ($contas as $conta): ?>
                                            <option value="<?= (int) $conta['id']; ?>" <?= isSelected($conta['id'], $contaId); ?>>
                                                <?= htmlspecialchars($conta['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="campanha_id">Campanha</label>
                                    <select name="campanha_id" id="campanha_id">
                                        <option value="">Todas</option>
                                        <?php foreach ($campanhas as $campanha): ?>
                                            <option value="<?= (int) $campanha['id']; ?>" <?= isSelected($campanha['id'], $campanhaId); ?>>
                                                <?= htmlspecialchars($campanha['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="periodo">Período</label>
                                    <select name="periodo" id="periodo" onchange="toggleCustomPeriod()">
                                        <option value="7" <?= isSelected('7', $periodo); ?>>Últimos 7 dias</option>
                                        <option value="15" <?= isSelected('15', $periodo); ?>>Últimos 15 dias</option>
                                        <option value="30" <?= isSelected('30', $periodo); ?>>Últimos 30 dias</option>
                                        <option value="90" <?= isSelected('90', $periodo); ?>>Últimos 90 dias</option>
                                        <option value="custom" <?= isSelected('custom', $periodo); ?>>Personalizado</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="data_inicio">Data início</label>
                                    <input
                                        type="date"
                                        name="data_inicio"
                                        id="data_inicio"
                                        value="<?= htmlspecialchars($dataInicio); ?>">
                                </div>

                                <div class="field">
                                    <label for="data_fim">Data fim</label>
                                    <input
                                        type="date"
                                        name="data_fim"
                                        id="data_fim"
                                        value="<?= htmlspecialchars($dataFim); ?>">
                                </div>

                            </div>

                            <div class="actions-row">
                                <button type="submit" class="btn btn-primary">Gerar relatório</button>
                                <a href="<?= htmlspecialchars($previewUrl); ?>" target="_blank" class="btn btn-secondary btn-link-clean">
                                    Abrir em nova aba
                                </a>
                                <a href="<?= htmlspecialchars($printUrl); ?>" target="_blank" class="btn btn-secondary btn-link-clean">
                                    Imprimir
                                </a>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="panel">
                    <div class="preview-head">
                        <div>
                            <h2>Preview do relatório</h2>
                            <p>Visualização incorporada da versão final em tema claro.</p>
                        </div>

                        <div class="preview-actions">
                            <a href="<?= htmlspecialchars($previewUrl); ?>" target="_blank" class="btn btn-secondary btn-link-clean">
                                Abrir relatório
                            </a>
                            <a href="<?= htmlspecialchars($printUrl); ?>" target="_blank" class="btn btn-secondary btn-link-clean">
                                Versão de impressão
                            </a>
                        </div>
                    </div>

                    <div class="preview-frame-wrap">
                        <iframe
                            src="<?= htmlspecialchars($previewUrl); ?>"
                            class="preview-frame"
                            loading="lazy"></iframe>
                    </div>
                </section>

                <section class="panel">
                    <div class="section-head">
                        <div>
                            <h2>Próxima estrutura do relatório</h2>
                            <p>Organização correta para não perder padrão conforme o módulo crescer.</p>
                        </div>
                    </div>

                    <div class="help-grid">
                        <div class="help-card">
                            <strong>Resumo executivo</strong>
                            <span>Cards principais, tendência, comparação com período anterior e leitura rápida.</span>
                        </div>

                        <div class="help-card">
                            <strong>Insights estruturados</strong>
                            <span>Blocos por categoria: desempenho, alerta, oportunidade e recomendação.</span>
                        </div>

                        <div class="help-card">
                            <strong>Saída profissional</strong>
                            <span>Versão clara, imprimível, exportável e depois pronta para e-mail e WhatsApp.</span>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <script>
        function toggleCustomPeriod() {
            const periodo = document.getElementById('periodo');
            const dataInicio = document.getElementById('data_inicio');
            const dataFim = document.getElementById('data_fim');

            if (!periodo || !dataInicio || !dataFim) return;

            const isCustom = periodo.value === 'custom';

            dataInicio.disabled = !isCustom;
            dataFim.disabled = !isCustom;
        }

        toggleCustomPeriod();
    </script>

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