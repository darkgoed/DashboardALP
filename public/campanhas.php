<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

$managementService = new CampanhaManagementService($conn, $empresaId);
$writeService = new CampanhaWriteService($conn, $empresaId);

$acao = $_GET['acao'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::isValid()) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    $postAcao = $_POST['acao'] ?? '';

    try {
        if ($postAcao === 'criar') {
            Flash::success($writeService->create($_POST));
        } elseif ($postAcao === 'atualizar') {
            Flash::success($writeService->update($_POST));
        } elseif ($postAcao === 'excluir') {
            Flash::success($writeService->delete($_POST));
        }
    } catch (Throwable $e) {
        Flash::error($e->getMessage());
    }

    header('Location: ' . routeUrl('campanhas'));
    exit;
}
$pageData = $managementService->getPageData($acao, $id);
$contas = $pageData['contas'];
$campanhaEdicao = $pageData['campanha_edicao'];
$lista = $pageData['lista'];
$totalCampanhas = $pageData['totais']['total_campanhas'];
$totalComObjetivo = $pageData['totais']['total_com_objetivo'];
$totalComMetaId = $pageData['totais']['total_com_meta_id'];
$totalComStatus = $pageData['totais']['total_com_status'];

if ($acao === 'editar' && $id > 0 && !$campanhaEdicao) {
    Flash::error('Campanha não encontrada.');
    header('Location: ' . routeUrl('campanhas'));
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campanhas - Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/toast.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body class="page page-campanhas">

    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 11l18-8v18l-18-8z"></path>
                            <path d="M11 13v6"></path>
                            <path d="M8 19h6"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Performance</span>
                        <h1 class="page-title">Campanhas</h1>
                        <p class="page-subtitle">
                            Cadastre e organize as campanhas vinculadas às contas de anúncio.
                            Essa estrutura vai alimentar análises, filtros, relatórios e futuras automações do dashboard.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('campanhas')); ?>" class="btn btn-secondary">Atualizar tela</a>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Total de campanhas</span>
                    <strong><?= $totalCampanhas ?></strong>
                    <small>Campanhas cadastradas no sistema</small>
                </div>

                <div class="metric-card metric-green">
                    <span>Com objetivo definido</span>
                    <strong><?= $totalComObjetivo ?></strong>
                    <small>Campanhas mais organizadas para análise</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Com Meta Campaign ID</span>
                    <strong><?= $totalComMetaId ?></strong>
                    <small>Campanhas vinculadas à estrutura Meta</small>
                </div>

                <div class="metric-card">
                    <span>Com status preenchido</span>
                    <strong><?= $totalComStatus ?></strong>
                    <small>Campanhas com informação operacional</small>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3><?= $campanhaEdicao ? 'Editar campanha' : 'Nova campanha' ?></h3>
                            <p class="panel-subtitle">
                                <?= $campanhaEdicao
                                    ? 'Atualize os dados da campanha selecionada.'
                                    : 'Cadastre uma nova campanha e vincule-a a uma conta existente.' ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" class="form-stack">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="acao" value="<?= $campanhaEdicao ? 'atualizar' : 'criar' ?>">
                        <input type="hidden" name="id" value="<?= (int)($campanhaEdicao['id'] ?? 0) ?>">

                        <div class="field field-select">
                            <label for="conta_id">Conta de anúncio</label>
                            <select id="conta_id" name="conta_id" required>
                                <option value="">Selecione a conta</option>
                                <?php foreach ($contas as $c): ?>
                                    <option
                                        value="<?= (int)$c['id'] ?>"
                                        <?= (int)($campanhaEdicao['conta_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="nome">Nome da campanha</label>
                            <input
                                id="nome"
                                type="text"
                                name="nome"
                                class="input"
                                placeholder="Ex: Campanha Leads WhatsApp"
                                required
                                value="<?= htmlspecialchars($campanhaEdicao['nome'] ?? '') ?>">
                        </div>

                        <div class="field">
                            <label for="objetivo">Objetivo</label>
                            <input
                                id="objetivo"
                                type="text"
                                name="objetivo"
                                class="input"
                                placeholder="Ex: Leads, Tráfego, Conversão"
                                value="<?= htmlspecialchars($campanhaEdicao['objetivo'] ?? '') ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?= $campanhaEdicao ? 'Atualizar campanha' : 'Salvar campanha' ?>
                            </button>

                            <?php if ($campanhaEdicao): ?>
                                <a href="<?= htmlspecialchars(routeUrl('campanhas')); ?>" class="btn btn-ghost">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Lista de campanhas</h3>
                            <p class="panel-subtitle">
                                Visualize as campanhas cadastradas e mantenha a operação organizada por conta e objetivo.
                            </p>
                        </div>

                        <div class="panel-actions">
                            <span class="badge badge-yellow"><?= $totalCampanhas ?> campanhas</span>
                        </div>
                    </div>

                    <?php if (empty($lista)): ?>
                        <div class="data-item-empty">
                            Nenhuma campanha cadastrada até o momento.
                        </div>
                    <?php else: ?>
                        <div class="data-list">
                            <?php foreach ($lista as $c): ?>
                                <div class="data-item">
                                    <div class="data-item-left">
                                        <div class="data-item-title">
                                            <?= htmlspecialchars(CampanhaViewHelper::displayName($c)) ?>
                                        </div>

                                        <div class="data-item-meta">
                                            <span>
                                                <strong>Conta:</strong>
                                                <?= !empty($c['conta_nome']) ? htmlspecialchars($c['conta_nome']) : 'Não vinculada' ?>
                                            </span>

                                            <span>
                                                <strong>Objetivo:</strong>
                                                <?= !empty($c['objetivo']) ? htmlspecialchars(CampanhaViewHelper::goalLabel($c['objetivo'])) : 'Não informado' ?>
                                            </span>

                                            <span>
                                                <strong>Meta Campaign ID:</strong>
                                                <?= !empty($c['meta_campaign_id']) ? htmlspecialchars($c['meta_campaign_id']) : 'Não informado' ?>
                                            </span>

                                            <span>
                                                <strong>Status:</strong>
                                                <?= !empty($c['status']) ? htmlspecialchars($c['status']) : 'Não informado' ?>
                                            </span>

                                            <span>
                                                <strong>ID:</strong>
                                                #<?= (int)$c['id'] ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="data-item-right">
                                        <?php if (!empty($c['objetivo'])): ?>
                                            <span class="badge badge-blue"><?= htmlspecialchars(CampanhaViewHelper::goalLabel($c['objetivo'])) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Sem objetivo</span>
                                        <?php endif; ?>

                                        <?php if (!empty($c['meta_campaign_id'])): ?>
                                            <span class="badge badge-green">Meta ID ok</span>
                                        <?php else: ?>
                                            <span class="badge badge-yellow">Sem Meta ID</span>
                                        <?php endif; ?>

                                        <?php if (!empty($c['status'])): ?>
                                            <span class="badge badge-purple"><?= htmlspecialchars($c['status']) ?></span>
                                        <?php endif; ?>

                                        <a href="<?= htmlspecialchars(routeUrl('campanhas') . '?acao=editar&id=' . (int) $c['id']); ?>" class="btn btn-warning btn-sm">
                                            Editar
                                        </a>

                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta campanha?')">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <?php Flash::renderScript(); ?>
    <script src="../assets/js/bootstrap.js"></script>

</body>

</html>
