<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();
$pageData = (new IntegracaoMetaPageService($conn, $empresaId))->getPageData($_GET);
$clientes = $pageData['clientes'];
$clienteId = $pageData['cliente_id'];
$clienteSelecionado = $pageData['cliente_selecionado'];
$tokenMeta = $pageData['token_meta'];
$conectado = $pageData['conectado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integracoes Meta - Dashboard ALP</title>

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
<body class="page page-integracoes">

<div class="app">
    <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

    <main class="main">
        <section class="page-hero">
            <div class="page-hero-left">
                <div class="page-hero-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M21 16V8a2 2 0 0 0-2-2h-3"></path>
                        <path d="M3 8v8a2 2 0 0 0 2 2h3"></path>
                        <path d="M7 12h10"></path>
                        <path d="M12 7v10"></path>
                    </svg>
                </div>

                <div>
                    <span class="page-kicker">Integracao</span>
                    <h1 class="page-title">Meta Ads</h1>
                    <p class="page-subtitle">
                        Conecte contas do Meta Ads aos clientes do sistema para liberar sincronizacoes,
                        insights automaticos e analises completas dentro do dashboard.
                    </p>
                </div>
            </div>

            <div class="page-hero-actions">
                <a href="<?= htmlspecialchars(routeUrl('integracoes_meta')); ?>" class="btn btn-secondary">Atualizar</a>
            </div>
        </section>

        <section class="content-grid">
            <div class="panel form-panel">
                <div class="panel-header">
                    <div>
                        <h3>Selecionar cliente</h3>
                        <p class="panel-subtitle">
                            Escolha o cliente que voce deseja conectar com a Meta.
                        </p>
                    </div>
                </div>

                <?php if (empty($clientes)): ?>
                    <div class="data-item-empty">
                        Nenhum cliente cadastrado para esta empresa.
                    </div>
                <?php else: ?>
                    <form method="GET" class="form-stack">
                        <div class="field field-select">
                            <label for="cliente_id">Cliente</label>
                            <select name="cliente_id" id="cliente_id" onchange="this.form.submit()">
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= (int) $cliente['id'] ?>" <?= $clienteId === (int) $cliente['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cliente['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="panel list-panel">
                <div class="panel-header">
                    <div>
                        <h3>Status da integracao</h3>
                        <p class="panel-subtitle">
                            Conecte a conta Meta para comecar a coletar dados automaticamente.
                        </p>
                    </div>
                </div>

                <div class="data-list">
                    <div class="data-item">
                        <div class="data-item-left">
                            <div class="data-item-title">
                                <?= htmlspecialchars($clienteSelecionado['nome'] ?? 'Nenhum cliente') ?>
                            </div>

                            <div class="data-item-meta">
                                <span>
                                    <strong>Status:</strong>
                                    <?= $conectado ? 'Conectado' : 'Nao conectado' ?>
                                </span>

                                <span>
                                    <strong>Integracao:</strong> Meta Ads API
                                </span>

                                <span>
                                    <strong>Meta User ID:</strong>
                                    <?= !empty($tokenMeta['meta_user_id']) ? htmlspecialchars($tokenMeta['meta_user_id']) : 'Nao disponivel' ?>
                                </span>

                                <span>
                                    <strong>Expira em:</strong>
                                    <?= !empty($tokenMeta['expires_at']) ? htmlspecialchars($tokenMeta['expires_at']) : 'Nao informado' ?>
                                </span>
                            </div>
                        </div>

                        <div class="data-item-right">
                            <?php if ($conectado): ?>
                                <span class="badge badge-green">Conectado</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">Pendente</span>
                            <?php endif; ?>

                            <?php if (!empty($clienteSelecionado)): ?>
                                <a
                                    href="<?= htmlspecialchars(routeUrl('callback_meta') . '?action=login&cliente_id=' . (int) $clienteId) ?>"
                                    class="btn btn-primary btn-sm"
                                >
                                    <?= $conectado ? 'Reconectar Meta' : 'Conectar Meta' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="data-item-empty" style="margin-top: 12px;">
                    Apos conectar, voce podera sincronizar campanhas, contas e insights automaticamente.
                </div>
            </div>
        </section>
    </main>
</div>

<?php Flash::renderScript(); ?>
<script src="../assets/js/bootstrap.js"></script>
</body>
</html>
