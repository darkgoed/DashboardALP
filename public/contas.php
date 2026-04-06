<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();
$contaManagementService = new ContaManagementService($conn, $empresaId);
$contaWriteService = new ContaWriteService($conn, $empresaId);

$acao = $_GET['acao'] ?? '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::isValid()) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    $postAcao = $_POST['acao'] ?? '';

    try {
        if ($postAcao === 'criar') {
            Flash::success($contaWriteService->create($_POST));
        } elseif ($postAcao === 'atualizar') {
            Flash::success($contaWriteService->update($_POST));
        } elseif ($postAcao === 'excluir') {
            Flash::success($contaWriteService->delete($_POST));
        }
    } catch (Throwable $e) {
        Flash::error($e->getMessage());
    }

    header('Location: ' . routeUrl('contas'));
    exit;
}
$pageData = $contaManagementService->getPageData($acao, $id);
$clientes = $pageData['clientes'];
$contaEdicao = $pageData['conta_edicao'];
$lista = $pageData['lista'];
$syncStatus = $pageData['sync_status'];
$totalContas = (int) ($pageData['totais']['total_contas'] ?? 0);
$totalVinculadas = (int) ($pageData['totais']['total_vinculadas'] ?? 0);
$totalComMetaId = (int) ($pageData['totais']['total_com_meta_id'] ?? 0);
$totalComStatus = (int) ($pageData['totais']['total_com_status'] ?? 0);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas de Anúncio - Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/toast.css">
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body class="page page-contas">

    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                            <path d="M3 10h18"></path>
                            <path d="M7 15h2"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Estrutura</span>
                        <h1 class="page-title">Contas de anúncio</h1>
                        <p class="page-subtitle">
                            Organize as contas vinculadas aos clientes do sistema e mantenha a base pronta
                            para campanhas, relatórios e integrações futuras com a Meta.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('contas')); ?>" class="btn btn-secondary">Atualizar tela</a>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Total de contas</span>
                    <strong><?= $totalContas ?></strong>
                    <small>Contas cadastradas no sistema</small>
                </div>

                <div class="metric-card metric-green">
                    <span>Vinculadas a clientes</span>
                    <strong><?= $totalVinculadas ?></strong>
                    <small>Estrutura relacionada corretamente</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Com Meta Account ID</span>
                    <strong><?= $totalComMetaId ?></strong>
                    <small>Contas com identificação Meta salva</small>
                </div>

                <div class="metric-card">
                    <span>Com status preenchido</span>
                    <strong><?= $totalComStatus ?></strong>
                    <small>Contas com informação operacional</small>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3><?= $contaEdicao ? 'Editar conta' : 'Nova conta de anúncio' ?></h3>
                            <p class="panel-subtitle">
                                <?= $contaEdicao
                                    ? 'Atualize os dados da conta selecionada.'
                                    : 'Cadastre uma nova conta e vincule-a a um cliente do sistema.' ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" class="form-stack">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="acao" value="<?= $contaEdicao ? 'atualizar' : 'criar' ?>">
                        <input type="hidden" name="id" value="<?= (int) ($contaEdicao['id'] ?? 0) ?>">

                        <div class="field field-select">
                            <label for="cliente_id">Cliente</label>
                            <select id="cliente_id" name="cliente_id" required>
                                <option value="">Selecione o cliente</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($contaEdicao['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="nome">Nome da conta</label>
                            <input id="nome" type="text" name="nome" class="input"
                                placeholder="Ex: Conta Principal Meta Cell" required
                                value="<?= htmlspecialchars($contaEdicao['nome'] ?? '') ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?= $contaEdicao ? 'Atualizar conta' : 'Salvar conta' ?>
                            </button>

                            <?php if ($contaEdicao): ?>
                                <a href="<?= htmlspecialchars(routeUrl('contas')); ?>" class="btn btn-ghost">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Lista de contas</h3>
                            <p class="panel-subtitle">
                                Visualize as contas cadastradas e gerencie a estrutura de cada cliente.
                            </p>
                        </div>

                        <div class="panel-actions">
                            <span class="badge badge-green"><?= $totalContas ?> contas</span>
                        </div>
                    </div>

                    <?php if (empty($lista)): ?>
                        <div class="data-item-empty">
                            Nenhuma conta cadastrada até o momento.
                        </div>
                    <?php else: ?>
                        <div class="data-list">
                            <?php foreach ($lista as $c): ?>
                                <div class="data-item">
                                    <div class="data-item-left">
                                        <div class="data-item-title">
                                            <?= htmlspecialchars($c['nome']) ?>
                                        </div>

                                        <div class="data-item-meta">
                                            <span>
                                                <strong>Cliente:</strong>
                                                <?= !empty($c['cliente_nome']) ? htmlspecialchars($c['cliente_nome']) : 'Não vinculado' ?>
                                            </span>

                                            <span>
                                                <strong>Meta Account ID:</strong>
                                                <?= !empty($c['meta_account_id']) ? htmlspecialchars($c['meta_account_id']) : 'Não informado' ?>
                                            </span>

                                            <span>
                                                <strong>Status:</strong>
                                                <?= !empty($c['status']) ? htmlspecialchars($c['status']) : 'Não informado' ?>
                                            </span>

                                            <span>
                                                <strong>ID:</strong>
                                                #<?= (int) $c['id'] ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="data-item-right">
                                        <?php if (!empty($c['meta_account_id'])): ?>
                                            <span class="badge badge-green">Meta ID ok</span>
                                        <?php else: ?>
                                            <span class="badge badge-yellow">Sem Meta ID</span>
                                        <?php endif; ?>

                                        <?php if (!empty($c['status'])): ?>
                                            <span class="badge badge-blue"><?= htmlspecialchars($c['status']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Sem status</span>
                                        <?php endif; ?>


                                        <form method="POST" action="<?= htmlspecialchars(routeUrl('contas/sync_now')); ?>" style="display:inline;">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i data-lucide="refresh-cw"></i>
                                                <span>Sincronizar agora</span>
                                            </button>
                                        </form>

                                        <form method="POST" action="<?= htmlspecialchars(routeUrl('contas/reprocessar_7_dias')); ?>" style="display:inline;">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline">
                                                <i data-lucide="history"></i>
                                                <span>Reprocessar 7 dias</span>
                                            </button>
                                        </form>

                                        <form method="POST" action="<?= htmlspecialchars(routeUrl('contas/full_sync')); ?>" style="display:inline;"
                                            onsubmit="return confirm('Enfileirar Full Sync manual desta conta? Isso vai buscar toda a estrutura e os insights disponiveis da Meta para esta conta.')">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary">
                                                <i data-lucide="database"></i>
                                                <span>Full Sync</span>
                                            </button>
                                        </form>

                                        <a href="<?= htmlspecialchars(routeUrl('contas') . '?acao=editar&id=' . (int) $c['id']); ?>" class="btn btn-warning btn-sm">
                                            Editar
                                        </a>

                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirm('Tem certeza que deseja excluir esta conta? Todos os dados vinculados tambem serao removidos, incluindo campanhas, conjuntos, anuncios, insights e logs relacionados.')">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Exclui a conta e todos os dados vinculados">Excluir conta e dados</button>
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
