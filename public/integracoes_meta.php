<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

$db = new Database();
$conn = $db->connect();

$clienteModel = new Cliente($conn, $empresaId);

$stmt = $conn->query("SELECT id, nome FROM clientes ORDER BY nome ASC");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : (int)($clientes[0]['id'] ?? 0);

$clienteSelecionado = null;
foreach ($clientes as $c) {
    if ((int)$c['id'] === $clienteId) {
        $clienteSelecionado = $c;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrações Meta - Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- IMPORTANTE -->
    <link rel="stylesheet" href="../assets/css/global.css">
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
                    <span class="page-kicker">Integração</span>
                    <h1 class="page-title">Meta Ads</h1>
                    <p class="page-subtitle">
                        Conecte contas do Meta Ads aos clientes do sistema para liberar sincronizações,
                        insights automáticos e análises completas dentro do dashboard.
                    </p>
                </div>
            </div>

            <div class="page-hero-actions">
                <a href="integracoes_meta.php" class="btn btn-secondary">Atualizar</a>
            </div>
        </section>

        <section class="content-grid">
            <div class="panel form-panel">
                <div class="panel-header">
                    <div>
                        <h3>Selecionar cliente</h3>
                        <p class="panel-subtitle">
                            Escolha o cliente que você deseja conectar com a Meta.
                        </p>
                    </div>
                </div>

                <form method="GET" class="form-stack">
                    <div class="field field-select">
                        <label for="cliente_id">Cliente</label>
                        <select name="cliente_id" id="cliente_id" onchange="this.form.submit()">
                            <?php foreach ($clientes as $cliente): ?>
                                <option
                                    value="<?= (int)$cliente['id'] ?>"
                                    <?= $clienteId === (int)$cliente['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($cliente['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="panel list-panel">
                <div class="panel-header">
                    <div>
                        <h3>Status da integração</h3>
                        <p class="panel-subtitle">
                            Conecte a conta Meta para começar a coletar dados automaticamente.
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
                                    <strong>Status:</strong> Não conectado
                                </span>

                                <span>
                                    <strong>Integração:</strong> Meta Ads API
                                </span>
                            </div>
                        </div>

                        <div class="data-item-right">
                            <span class="badge badge-yellow">Pendente</span>

                            <a
                                href="callback_meta.php?action=login&cliente_id=<?= (int)$clienteId ?>"
                                class="btn btn-primary btn-sm"
                            >
                                Conectar Meta
                            </a>
                        </div>
                    </div>
                </div>

                <div class="data-item-empty" style="margin-top: 12px;">
                    Após conectar, você poderá sincronizar campanhas, contas e insights automaticamente.
                </div>
            </div>
        </section>
    </main>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
</script>

<script src="../assets/js/nav-config.js"></script>
</body>
</html>