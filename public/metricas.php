<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

$db = new Database();
$conn = $db->connect();

$clienteModel = new Cliente($conn, $empresaId);
$clientes = $clienteModel->getAll();

$clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
$mensagem = $_GET['msg'] ?? '';

function carregarMetricasBase(): array
{
    $arquivo = __DIR__ . '/../app/config/metricas_base.json';

    if (!file_exists($arquivo)) {
        return [
            '_erro' => 'Arquivo metricas_base.json não encontrado em app/config.'
        ];
    }

    $conteudo = file_get_contents($arquivo);
    $json = json_decode($conteudo, true);

    if (!is_array($json)) {
        return [
            '_erro' => 'O arquivo metricas_base.json está inválido.'
        ];
    }

    return $json;
}

function buscarNomeCliente(array $clientes, int $clienteId): string
{
    foreach ($clientes as $cliente) {
        if ((int)$cliente['id'] === $clienteId) {
            return $cliente['nome'];
        }
    }

    return 'Empresa';
}

function carregarConfigCliente(PDO $conn, int $clienteId): ?array
{
    if ($clienteId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT config_json FROM metricas_config WHERE cliente_id = :cliente_id LIMIT 1");
    $stmt->execute([
        ':cliente_id' => $clienteId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['config_json'])) {
        return null;
    }

    $json = json_decode($row['config_json'], true);

    return is_array($json) ? $json : null;
}

function montarConfigPadraoParaCliente(array $base, string $nomeCliente): array
{
    return [
        'empresa' => $nomeCliente,
        'perfil' => $base['perfil_padrao'] ?? [
            'segmento' => '',
            'objetivo_principal' => 'Leads',
            'tipo_operacao' => 'Meta Ads',
            'observacoes' => ''
        ],
        'categorias' => $base['categorias'] ?? [],
        'metricas' => $base['metricas'] ?? []
    ];
}

function extrairCategoriasDasMetricas(array $metricas, array $categoriasBase = []): array
{
    $categorias = [];

    foreach ($metricas as $metrica) {
        $chaveCategoria = $metrica['categoria'] ?? '';

        if ($chaveCategoria === '') {
            continue;
        }

        if (isset($categoriasBase[$chaveCategoria])) {
            $categorias[$chaveCategoria] = $categoriasBase[$chaveCategoria];
        } else {
            $categorias[$chaveCategoria] = ucfirst(str_replace('_', ' ', $chaveCategoria));
        }
    }

    return $categorias;
}

function normalizarCheckbox($valor): bool
{
    return $valor === '1' || $valor === 1 || $valor === true || $valor === 'on';
}

function floatOuZero($valor): float
{
    if ($valor === null || $valor === '') {
        return 0;
    }

    $valor = str_replace(',', '.', (string)$valor);
    return (float)$valor;
}

function intOuZero($valor): int
{
    if ($valor === null || $valor === '') {
        return 0;
    }

    return (int)$valor;
}

$metricasBase = carregarMetricasBase();
$erroBase = $metricasBase['_erro'] ?? '';

if ($erroBase) {
    $metricasBase = [
        'perfil_padrao' => [
            'segmento' => '',
            'objetivo_principal' => 'Leads',
            'tipo_operacao' => 'Meta Ads',
            'observacoes' => ''
        ],
        'categorias' => [],
        'metricas' => []
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAcao = $_POST['acao'] ?? '';
    $clienteIdPost = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;

    if ($postAcao === 'salvar_metricas' && $clienteIdPost > 0) {
        $nomeCliente = buscarNomeCliente($clientes, $clienteIdPost);

        $perfilPost = $_POST['perfil'] ?? [];
        $metricasPost = $_POST['metricas'] ?? [];
        $jsonImportado = trim($_POST['json_importado'] ?? '');

        $configFinal = [
            'empresa' => $nomeCliente,
            'perfil' => [
                'segmento' => trim($perfilPost['segmento'] ?? ''),
                'objetivo_principal' => trim($perfilPost['objetivo_principal'] ?? 'Leads'),
                'tipo_operacao' => trim($perfilPost['tipo_operacao'] ?? 'Meta Ads'),
                'observacoes' => trim($perfilPost['observacoes'] ?? '')
            ],
            'categorias' => $metricasBase['categorias'] ?? [],
            'metricas' => []
        ];

        if ($jsonImportado !== '') {
            $jsonDecodificado = json_decode($jsonImportado, true);

            if (is_array($jsonDecodificado)) {
                $configFinal = $jsonDecodificado;

                $configFinal['empresa'] = $nomeCliente;

                if (!isset($configFinal['perfil']) || !is_array($configFinal['perfil'])) {
                    $configFinal['perfil'] = [];
                }

                $configFinal['perfil']['segmento'] = trim($configFinal['perfil']['segmento'] ?? ($perfilPost['segmento'] ?? ''));
                $configFinal['perfil']['objetivo_principal'] = trim($configFinal['perfil']['objetivo_principal'] ?? ($perfilPost['objetivo_principal'] ?? 'Leads'));
                $configFinal['perfil']['tipo_operacao'] = trim($configFinal['perfil']['tipo_operacao'] ?? ($perfilPost['tipo_operacao'] ?? 'Meta Ads'));
                $configFinal['perfil']['observacoes'] = trim($configFinal['perfil']['observacoes'] ?? ($perfilPost['observacoes'] ?? ''));

                if (!isset($configFinal['categorias']) || !is_array($configFinal['categorias'])) {
                    $configFinal['categorias'] = $metricasBase['categorias'] ?? [];
                }

                if (!isset($configFinal['metricas']) || !is_array($configFinal['metricas'])) {
                    $configFinal['metricas'] = [];
                }
            }
        }

        if (empty($configFinal['metricas'])) {
            foreach ($metricasPost as $chave => $metrica) {
                $configFinal['metricas'][$chave] = [
                    'label' => trim($metrica['label'] ?? ''),
                    'unit' => trim($metrica['unit'] ?? ''),
                    'categoria' => trim($metrica['categoria'] ?? ''),
                    'tipo_leitura' => trim($metrica['tipo_leitura'] ?? 'faixa_ideal'),
                    'peso' => intOuZero($metrica['peso'] ?? 0),
                    'ativo' => normalizarCheckbox($metrica['ativo'] ?? false),
                    'critico_min' => floatOuZero($metrica['critico_min'] ?? 0),
                    'alerta_min' => floatOuZero($metrica['alerta_min'] ?? 0),
                    'ideal_min' => floatOuZero($metrica['ideal_min'] ?? 0),
                    'ideal_max' => floatOuZero($metrica['ideal_max'] ?? 0),
                    'alerta_max' => floatOuZero($metrica['alerta_max'] ?? 0),
                    'critico_max' => floatOuZero($metrica['critico_max'] ?? 0),
                    'descricao' => trim($metrica['descricao'] ?? '')
                ];
            }
        }

        if (empty($configFinal['categorias'])) {
            $configFinal['categorias'] = extrairCategoriasDasMetricas($configFinal['metricas']);
        }

        $jsonFinal = json_encode($configFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $check = $conn->prepare("SELECT id FROM metricas_config WHERE cliente_id = :cliente_id LIMIT 1");
        $check->execute([
            ':cliente_id' => $clienteIdPost
        ]);

        $existe = $check->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            $stmt = $conn->prepare("
                UPDATE metricas_config
                SET config_json = :config_json, updated_at = NOW()
                WHERE cliente_id = :cliente_id
            ");
        } else {
            $stmt = $conn->prepare("
                INSERT INTO metricas_config (cliente_id, nome_config, config_json, created_at, updated_at)
                VALUES (:cliente_id, 'Padrão', :config_json, NOW(), NOW())
            ");
        }

        $stmt->execute([
            ':cliente_id' => $clienteIdPost,
            ':config_json' => $jsonFinal
        ]);

        header('Location: metricas.php?cliente_id=' . $clienteIdPost . '&msg=salvo');
        exit;
    }
}

$configCliente = carregarConfigCliente($conn, $clienteId);

if ($configCliente) {
    $configAtual = $configCliente;
} else {
    $configAtual = montarConfigPadraoParaCliente(
        $metricasBase,
        buscarNomeCliente($clientes, $clienteId)
    );
}

$perfilAtual = $configAtual['perfil'] ?? [];
$metricas = $configAtual['metricas'] ?? [];

$categoriasBase = $metricasBase['categorias'] ?? [];
$categorias = $configAtual['categorias'] ?? [];

if (empty($categorias) && !empty($metricas)) {
    $categorias = extrairCategoriasDasMetricas($metricas, $categoriasBase);
}

$totalClientes = count($clientes);

$stmtTotal = $conn->query("SELECT COUNT(*) AS total FROM metricas_config");
$rowTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
$totalConfigurados = (int)($rowTotal['total'] ?? 0);

$totalMetricas = count($metricas);
$totalAtivas = 0;
$categoriasUsadas = [];

foreach ($metricas as $metrica) {
    if (!empty($metrica['ativo'])) {
        $totalAtivas++;
    }

    $categoriaKey = $metrica['categoria'] ?? '';
    if ($categoriaKey !== '') {
        $categoriasUsadas[$categoriaKey] = true;
    }
}

$totalCategorias = count($categoriasUsadas);
$jsonPreview = json_encode($configAtual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Métricas - Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/global.css">
</head>

<body class="page page-metricas">

    <div class="app">
        <?php
        $mostrarFiltrosSidebar = false;
        require_once __DIR__ . '/partials/menu_lateral.php';
        ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 3v18h18"></path>
                            <path d="M7 14l3-3 3 2 4-5"></path>
                            <circle cx="7" cy="14" r="1"></circle>
                            <circle cx="10" cy="11" r="1"></circle>
                            <circle cx="13" cy="13" r="1"></circle>
                            <circle cx="17" cy="8" r="1"></circle>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Configurações</span>
                        <h1 class="page-title">Métricas por empresa</h1>
                        <p class="page-subtitle">
                            Configure manualmente os parâmetros de cada métrica importante para análise de tráfego pago,
                            separando o que é ideal, alerta e crítico por cliente.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="metricas.php<?= $clienteId > 0 ? '?cliente_id=' . $clienteId : '' ?>" class="btn btn-secondary">Atualizar tela</a>
                </div>
            </section>

            <?php if (!empty($erroBase)): ?>
                <section class="content-grid">
                    <div class="panel">
                        <div class="panel-header">
                            <div>
                                <h3>Erro ao carregar métricas base</h3>
                                <p class="panel-subtitle"><?= htmlspecialchars($erroBase) ?></p>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Total de clientes</span>
                    <strong><?= $totalClientes ?></strong>
                    <small>Clientes disponíveis no sistema</small>
                </div>

                <div class="metric-card metric-green">
                    <span>Clientes configurados</span>
                    <strong><?= $totalConfigurados ?></strong>
                    <small>Com JSON salvo no banco</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Total de métricas</span>
                    <strong><?= $totalMetricas ?></strong>
                    <small>Base carregada do JSON</small>
                </div>

                <div class="metric-card">
                    <span>Métricas ativas</span>
                    <strong><?= $totalAtivas ?></strong>
                    <small><?= $totalCategorias ?> categorias em uso</small>
                </div>
            </section>

            <?php if ($mensagem === 'salvo'): ?>
                <section class="content-grid">
                    <div class="panel">
                        <div class="panel-header">
                            <div>
                                <h3>Configuração salva com sucesso</h3>
                                <p class="panel-subtitle">As métricas dessa empresa foram atualizadas e o JSON final foi salvo.</p>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="content-grid grid-2">

                <!-- PERFIL -->
                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Perfil da operação</h3>
                            <p class="panel-subtitle">
                                Esses dados ajudam na organização da empresa e também servem de contexto para IA.
                            </p>
                        </div>

                        <div class="panel-actions">
                            <span class="badge badge-blue">Contexto do cliente</span>
                        </div>
                    </div>

                    <div class="data-list">
                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Segmento</div>
                                <div class="data-item-meta">
                                    <span>Ex.: loja, clínica, decoração, imobiliária, delivery</span>
                                </div>
                            </div>
                            <div class="data-item-right">
                                <span class="badge badge-muted"><?= htmlspecialchars($perfilAtual['segmento'] ?? '') ?: 'Não definido' ?></span>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Objetivo principal</div>
                                <div class="data-item-meta">
                                    <span>Lead, WhatsApp, tráfego, conversão, compra</span>
                                </div>
                            </div>
                            <div class="data-item-right">
                                <span class="badge badge-green"><?= htmlspecialchars($perfilAtual['objetivo_principal'] ?? 'Leads') ?></span>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Tipo de operação</div>
                                <div class="data-item-meta">
                                    <span>Canal principal usado para análise</span>
                                </div>
                            </div>
                            <div class="data-item-right">
                                <span class="badge badge-blue"><?= htmlspecialchars($perfilAtual['tipo_operacao'] ?? 'Meta Ads') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- REGRAS -->
                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Regras de leitura</h3>
                            <p class="panel-subtitle">
                                Cada métrica pode ser interpretada de uma forma diferente no dashboard.
                            </p>
                        </div>
                    </div>

                    <div class="data-list">
                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Menor é melhor</div>
                                <div class="data-item-meta">
                                    <span>Usado em métricas como CPM, CPC, CPL, custo por conversa e CPA.</span>
                                </div>
                            </div>
                            <div class="data-item-right">
                                <span class="badge badge-green">Custo</span>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Maior é melhor</div>
                                <div class="data-item-meta">
                                    <span>Usado em CTR, resultados, compras, ROAS, cliques e conversões.</span>
                                </div>
                            </div>
                            <div class="data-item-right">
                                <span class="badge badge-blue">Volume</span>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Faixa ideal</div>
                                <div class="data-item-meta">
                                    <span>Usado em frequência, orçamento e casos em que extremos podem ser ruins.</span>
                                </div>
                            </div>
                            <div class="data-item-right">
                                <span class="badge badge-yellow">Equilíbrio</span>
                            </div>
                        </div>
                    </div>
                </div>

            </section>

            <!-- SELECT (GET separado) -->
            <section class="content-grid">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Selecionar empresa</h3>
                            <p class="panel-subtitle">
                                Escolha o cliente para carregar as métricas padrão ou a configuração já salva.
                            </p>
                        </div>
                    </div>

                    <form method="GET" class="form-stack">
                        <div class="field">
                            <label for="cliente_id">Empresa</label>
                            <select id="cliente_id" name="cliente_id" class="input" onchange="this.form.submit()">
                                <option value="">Selecione um cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= (int)$cliente['id'] ?>" <?= $clienteId === (int)$cliente['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cliente['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </section>

            <!-- 🔥 AQUI COMEÇA O FORM POST -->
            <form method="POST" class="form-stack">
                <input type="hidden" name="acao" value="salvar_metricas">
                <input type="hidden" name="cliente_id" value="<?= (int)$clienteId ?>">

                <!-- DADOS DA EMPRESA -->
                <section class="content-grid">
                    <div class="panel form-panel">
                        <div class="panel-header">
                            <div>
                                <h3>Dados gerais da empresa</h3>
                                <p class="panel-subtitle">
                                    Essas informações acompanham o JSON salvo e ajudam a IA a sugerir ajustes melhores.
                                </p>
                            </div>
                        </div>

                        <div class="form-stack">
                            <div class="field">
                                <label for="segmento">Segmento</label>
                                <input
                                    id="segmento"
                                    type="text"
                                    name="perfil[segmento]"
                                    class="input"
                                    value="<?= htmlspecialchars($perfilAtual['segmento'] ?? '') ?>"
                                    <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                            </div>

                            <div class="field">
                                <label for="objetivo_principal">Objetivo principal</label>
                                <input
                                    id="objetivo_principal"
                                    type="text"
                                    name="perfil[objetivo_principal]"
                                    class="input"
                                    placeholder="Ex: Leads para WhatsApp"
                                    value="<?= htmlspecialchars($perfilAtual['objetivo_principal'] ?? 'Leads') ?>"
                                    <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                            </div>

                            <div class="field">
                                <label for="tipo_operacao">Tipo de operação</label>
                                <input
                                    id="tipo_operacao"
                                    type="text"
                                    name="perfil[tipo_operacao]"
                                    class="input"
                                    placeholder="Ex: Meta Ads"
                                    value="<?= htmlspecialchars($perfilAtual['tipo_operacao'] ?? 'Meta Ads') ?>"
                                    <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                            </div>

                            <div class="field">
                                <label for="observacoes">Observações</label>
                                <textarea
                                    id="observacoes"
                                    name="perfil[observacoes]"
                                    class="input"
                                    rows="5"
                                    placeholder="Contexto da operação, ticket, sazonalidade, público..."
                                    <?= $clienteId <= 0 ? 'disabled' : '' ?>><?= htmlspecialchars($perfilAtual['observacoes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION DO JSON!!! -->

                    <section class="content-grid content-grid-json">
                        <div class="panel list-panel">
                            <div class="panel-header">
                                <div>
                                    <h3>JSON da configuração</h3>
                                    <p class="panel-subtitle">
                                        Cole aqui o JSON ajustado ou edite manualmente antes de salvar.
                                    </p>
                                </div>

                                <div class="panel-actions">
                                    <span class="badge badge-muted">Preview</span>
                                </div>
                            </div>

                            <div class="field">
                                <label for="json_preview">JSON editável</label>
                                <textarea
                                    id="json_preview"
                                    name="json_importado"
                                    class="input"
                                    rows="24"
                                    <?= $clienteId <= 0 ? 'disabled' : '' ?>><?= htmlspecialchars($jsonPreview) ?></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="copiarJsonPreview()">Copiar JSON</button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="resetarJsonBase()"
                                    <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                    Resetar para padrão
                                </button>

                                <button type="submit" class="btn btn-primary" <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                    Salvar métricas
                                </button>
                            </div>
                        </div>

                        <div class="panel list-panel">
                            <div class="panel-header">
                                <div>
                                    <h3>Prompt para IA</h3>
                                    <p class="panel-subtitle">
                                        Copie este texto, envie para IA e depois cole de volta a versão ajustada.
                                    </p>
                                </div>
                            </div>

                            <div class="field">
                                <label for="prompt_ia">Prompt sugerido</label>
                                <textarea id="prompt_ia" class="input" rows="24" readonly>Analise o JSON abaixo e devolva somente um JSON válido, mantendo a mesma estrutura.
Objetivo:
- melhorar as faixas de ideal, alerta e crítico
- considerar o segmento da empresa
- considerar campanhas Meta Ads focadas em leads, WhatsApp, tráfego ou vendas
- manter coerência entre CPM, CTR, CPC, CPL, CPA, ROAS, frequência e taxa de conversão
- se uma métrica não fizer sentido para o contexto, mantenha "ativo" como false
- não explique nada fora do JSON

JSON atual:
<?= htmlspecialchars($jsonPreview) ?></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="copiarPromptIa()">Copiar prompt</button>
                            </div>
                        </div>
                    </section>

                    <section class="manual-section-banner">
                        <div class="manual-section-banner-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"></path>
                                <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7"></path>
                                <path d="M12 3v18"></path>
                                <path d="M8 8h1"></path>
                                <path d="M8 12h1"></path>
                                <path d="M15 8h1"></path>
                                <path d="M15 12h1"></path>
                            </svg>
                        </div>

                        <div class="manual-section-banner-content">
                            <span class="manual-section-kicker">Configuração manual</span>
                            <h2>As opções abaixo são ajustadas manualmente</h2>
                            <p>
                                Defina os limites, pesos, tipo de leitura e status de cada métrica conforme a realidade da operação do cliente.
                            </p>
                        </div>

                        <div class="manual-section-banner-badge manual-section-banner-actions">
                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="selecionarTodasMetricas()"
                                <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                Selecionar todos
                            </button>

                            <span class="badge badge-yellow">Edição manual</span>
                        </div>
                    </section>

                    <?php foreach ($categorias as $categoriaKey => $categoriaLabel): ?>
                        <section class="content-grid">
                            <div class="panel list-panel panel-metricas-categoria">
                                <div class="panel-header">
                                    <div>
                                        <h3><?= htmlspecialchars($categoriaLabel) ?></h3>
                                        <p class="panel-subtitle">
                                            Configure os limites manuais das métricas desta categoria.
                                        </p>
                                    </div>

                                    <div class="panel-actions">
                                        <span class="badge badge-blue"><?= htmlspecialchars($categoriaKey) ?></span>
                                    </div>
                                </div>

                                <div class="metricas-config-grid">
                                    <?php foreach ($metricas as $chave => $metrica): ?>
                                        <?php if (($metrica['categoria'] ?? '') !== $categoriaKey) continue; ?>

                                        <div class="metric-config-card" data-metrica="<?= htmlspecialchars($chave) ?>">
                                            <div class="metric-config-card-header">
                                                <div>
                                                    <h4><?= htmlspecialchars($metrica['label'] ?? $chave) ?></h4>
                                                    <p><?= htmlspecialchars($metrica['descricao'] ?? '') ?></p>
                                                </div>

                                                <div class="metric-config-toggle">
                                                    <label class="checkbox-inline">
                                                        <input
                                                            type="hidden"
                                                            name="metricas[<?= htmlspecialchars($chave) ?>][ativo]"
                                                            value="0">
                                                        <input
                                                            type="checkbox"
                                                            name="metricas[<?= htmlspecialchars($chave) ?>][ativo]"
                                                            value="1"
                                                            <?= !empty($metrica['ativo']) ? 'checked' : '' ?>
                                                            <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                        <span>Ativa</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <input type="hidden" name="metricas[<?= htmlspecialchars($chave) ?>][label]" value="<?= htmlspecialchars($metrica['label'] ?? '') ?>">
                                            <input type="hidden" name="metricas[<?= htmlspecialchars($chave) ?>][unit]" value="<?= htmlspecialchars($metrica['unit'] ?? '') ?>">
                                            <input type="hidden" name="metricas[<?= htmlspecialchars($chave) ?>][categoria]" value="<?= htmlspecialchars($metrica['categoria'] ?? '') ?>">
                                            <input type="hidden" name="metricas[<?= htmlspecialchars($chave) ?>][descricao]" value="<?= htmlspecialchars($metrica['descricao'] ?? '') ?>">

                                            <div class="metric-config-grid">
                                                <div class="field">
                                                    <label>Tipo de leitura</label>
                                                    <select
                                                        name="metricas[<?= htmlspecialchars($chave) ?>][tipo_leitura]"
                                                        class="input"
                                                        <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                        <option value="menor_melhor" <?= ($metrica['tipo_leitura'] ?? '') === 'menor_melhor' ? 'selected' : '' ?>>Menor é melhor</option>
                                                        <option value="maior_melhor" <?= ($metrica['tipo_leitura'] ?? '') === 'maior_melhor' ? 'selected' : '' ?>>Maior é melhor</option>
                                                        <option value="faixa_ideal" <?= ($metrica['tipo_leitura'] ?? '') === 'faixa_ideal' ? 'selected' : '' ?>>Faixa ideal</option>
                                                    </select>
                                                </div>

                                                <div class="field">
                                                    <label>Peso</label>
                                                    <input
                                                        type="number"
                                                        name="metricas[<?= htmlspecialchars($chave) ?>][peso]"
                                                        class="input"
                                                        value="<?= (int)($metrica['peso'] ?? 0) ?>"
                                                        <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                </div>

                                                <div class="field">
                                                    <label>Unidade</label>
                                                    <input
                                                        type="text"
                                                        class="input"
                                                        value="<?= htmlspecialchars($metrica['unit'] ?? '') ?>"
                                                        disabled>
                                                </div>

                                                <div class="field">
                                                    <label>Chave</label>
                                                    <input
                                                        type="text"
                                                        class="input"
                                                        value="<?= htmlspecialchars($chave) ?>"
                                                        disabled>
                                                </div>

                                                <div class="field">
                                                    <label>Crítico mín.</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="metricas[<?= htmlspecialchars($chave) ?>][critico_min]"
                                                        class="input"
                                                        value="<?= htmlspecialchars((string)($metrica['critico_min'] ?? 0)) ?>"
                                                        <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                </div>

                                                <div class="field">
                                                    <label>Alerta mín.</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="metricas[<?= htmlspecialchars($chave) ?>][alerta_min]"
                                                        class="input"
                                                        value="<?= htmlspecialchars((string)($metrica['alerta_min'] ?? 0)) ?>"
                                                        <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                </div>

                                                <div class="field">
                                                    <label>Ideal mín.</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="metricas[<?= htmlspecialchars($chave) ?>][ideal_min]"
                                                        class="input"
                                                        value="<?= htmlspecialchars((string)($metrica['ideal_min'] ?? 0)) ?>"
                                                        <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                </div>

                                                <div class="field">
                                                    <label>Ideal máx.</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="metricas[<?= htmlspecialchars($chave) ?>][ideal_max]"
                                                        class="input"
                                                        value="<?= htmlspecialchars((string)($metrica['ideal_max'] ?? 0)) ?>"
                                                        <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                </div>

                                                <div class="field">
                                                    <label>Alerta máx.</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="metricas[<?= htmlspecialchars($chave) ?>][alerta_max]"
                                                        class="input"
                                                        value="<?= htmlspecialchars((string)($metrica['alerta_max'] ?? 0)) ?>"
                                                        <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                </div>

                                                <div class="field">
                                                    <label>Crítico máx.</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="metricas[<?= htmlspecialchars($chave) ?>][critico_max]"
                                                        class="input"
                                                        value="<?= htmlspecialchars((string)($metrica['critico_max'] ?? 0)) ?>"
                                                        <?= $clienteId <= 0 ? 'disabled' : '' ?>>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>
                    <?php endforeach; ?>
            </form>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        function resetarJsonBase() {
            if (!confirm('Tem certeza que deseja resetar para o padrão? Isso irá sobrescrever as alterações atuais.')) {
                return;
            }

            const base = <?= json_encode(montarConfigPadraoParaCliente($metricasBase, buscarNomeCliente($clientes, $clienteId)), JSON_UNESCAPED_UNICODE) ?>;

            const empresaSelect = document.getElementById('cliente_id');
            if (empresaSelect && empresaSelect.selectedIndex >= 0) {
                const option = empresaSelect.options[empresaSelect.selectedIndex];
                if (option && option.value !== '') {
                    base.empresa = option.text;
                }
            }

            base.perfil = {
                segmento: document.querySelector('[name="perfil[segmento]"]')?.value || '',
                objetivo_principal: document.querySelector('[name="perfil[objetivo_principal]"]')?.value || '',
                tipo_operacao: document.querySelector('[name="perfil[tipo_operacao]"]')?.value || '',
                observacoes: document.querySelector('[name="perfil[observacoes]"]')?.value || ''
            };

            const jsonField = document.getElementById('json_preview');
            if (!jsonField) return;

            jsonField.value = JSON.stringify(base, null, 2);

            alert('JSON resetado para o padrão. Agora clique em salvar.');
        }

        function copiarTextoPorId(id) {
            const campo = document.getElementById(id);
            campo.select();
            campo.setSelectionRange(0, 999999);
            document.execCommand('copy');
        }

        function copiarJsonPreview() {
            copiarTextoPorId('json_preview');
        }

        function copiarPromptIa() {
            copiarTextoPorId('prompt_ia');
        }

        function selecionarTodasMetricas() {
            const checkboxes = document.querySelectorAll('.metric-config-card input[type="checkbox"][name$="[ativo]"]');

            checkboxes.forEach((checkbox) => {
                if (!checkbox.disabled) {
                    checkbox.checked = true;
                }
            });

            gerarJsonPreview();
        }

        function gerarJsonPreview() {
            const empresaSelect = document.getElementById('cliente_id');
            let empresaNome = 'Empresa';

            if (empresaSelect && empresaSelect.selectedIndex >= 0) {
                const option = empresaSelect.options[empresaSelect.selectedIndex];
                if (option && option.value !== '') {
                    empresaNome = option.text;
                }
            }

            const config = {
                empresa: empresaNome,
                perfil: {
                    segmento: document.querySelector('[name="perfil[segmento]"]')?.value || '',
                    objetivo_principal: document.querySelector('[name="perfil[objetivo_principal]"]')?.value || '',
                    tipo_operacao: document.querySelector('[name="perfil[tipo_operacao]"]')?.value || '',
                    observacoes: document.querySelector('[name="perfil[observacoes]"]')?.value || ''
                },
                categorias: <?= json_encode($categorias, JSON_UNESCAPED_UNICODE) ?>,
                metricas: {}
            };

            const metricBlocks = document.querySelectorAll('.metric-config-card');

            metricBlocks.forEach((card) => {
                const key = card.getAttribute('data-metrica');
                if (!key) return;

                const getValue = (selector) => {
                    const el = card.querySelector(selector);
                    return el ? el.value : '';
                };

                const getChecked = (selector) => {
                    const el = card.querySelector(selector);
                    return !!(el && el.checked);
                };

                const parseNumber = (value) => {
                    const normalized = (value || '0').toString().replace(',', '.');
                    const parsed = parseFloat(normalized);
                    return isNaN(parsed) ? 0 : parsed;
                };

                config.metricas[key] = {
                    label: getValue('[name="metricas[' + key + '][label]"]'),
                    unit: getValue('[name="metricas[' + key + '][unit]"]'),
                    categoria: getValue('[name="metricas[' + key + '][categoria]"]'),
                    tipo_leitura: getValue('[name="metricas[' + key + '][tipo_leitura]"]'),
                    peso: parseInt(getValue('[name="metricas[' + key + '][peso]"]') || '0', 10) || 0,
                    ativo: getChecked('input[type="checkbox"][name="metricas[' + key + '][ativo]"]'),
                    critico_min: parseNumber(getValue('[name="metricas[' + key + '][critico_min]"]')),
                    alerta_min: parseNumber(getValue('[name="metricas[' + key + '][alerta_min]"]')),
                    ideal_min: parseNumber(getValue('[name="metricas[' + key + '][ideal_min]"]')),
                    ideal_max: parseNumber(getValue('[name="metricas[' + key + '][ideal_max]"]')),
                    alerta_max: parseNumber(getValue('[name="metricas[' + key + '][alerta_max]"]')),
                    critico_max: parseNumber(getValue('[name="metricas[' + key + '][critico_max]"]')),
                    descricao: getValue('[name="metricas[' + key + '][descricao]"]')
                };
            });

            const jsonField = document.getElementById('json_preview');
            if (jsonField) {
                jsonField.value = JSON.stringify(config, null, 2);
            }

            const promptField = document.getElementById('prompt_ia');
            if (promptField && jsonField) {
                promptField.value =
                    `Analise o JSON abaixo e devolva somente um JSON válido, mantendo a mesma estrutura.
Objetivo:
- melhorar as faixas de ideal, alerta e crítico
- considerar o segmento da empresa
- considerar campanhas Meta Ads focadas em leads, WhatsApp, tráfego ou vendas
- manter coerência entre CPM, CTR, CPC, CPL, CPA, ROAS, frequência e taxa de conversão
- se uma métrica não fizer sentido para o contexto, mantenha "ativo" como false
- não explique nada fora do JSON

JSON atual:
${jsonField.value}`;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            gerarJsonPreview();
            lucide.createIcons();
        });

        document.addEventListener('input', function(event) {
            if (event.target && event.target.id === 'json_preview') {
                return;
            }
            gerarJsonPreview();
        });

        document.addEventListener('change', function(event) {
            if (event.target && event.target.id === 'json_preview') {
                return;
            }
            gerarJsonPreview();
        });
    </script>

    <script src="../assets/js/nav-config.js"></script>

</body>

</html>