<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

$db = new Database();
$conn = $db->connect();

$cliente = new Cliente($conn, $empresaId);

$acao = $_GET['acao'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$clienteEdicao = null;

if ($acao === 'editar' && $id > 0) {
    $clienteEdicao = $cliente->getById($id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAcao = $_POST['acao'] ?? 'criar';

    if ($postAcao === 'criar') {
        $cliente->create(
            trim($_POST['nome']),
            trim($_POST['email'] ?? ''),
            trim($_POST['whatsapp'] ?? '')
        );
    }

    if ($postAcao === 'atualizar') {
        $cliente->update(
            (int)$_POST['id'],
            trim($_POST['nome']),
            trim($_POST['email'] ?? ''),
            trim($_POST['whatsapp'] ?? '')
        );
    }

    header('Location: clientes.php');
    exit;
}

if ($acao === 'excluir' && $id > 0) {
    $cliente->delete($id);
    header('Location: clientes.php');
    exit;
}

$lista = $cliente->getAll();

$totalClientes = count($lista);
$totalComEmail = 0;
$totalComWhatsapp = 0;

foreach ($lista as $item) {
    if (!empty(trim($item['email'] ?? ''))) {
        $totalComEmail++;
    }

    if (!empty(trim($item['whatsapp'] ?? ''))) {
        $totalComWhatsapp++;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/global.css">
</head>

<body class="page page-clientes">

    <div class="app">

        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9.5" cy="7" r="4"></circle>
                            <path d="M20 8v6"></path>
                            <path d="M23 11h-6"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Gestão</span>
                        <h1 class="page-title">Clientes</h1>
                        <p class="page-subtitle">
                            Cadastre, edite e organize os clientes do Dashboard ALP.
                            Aqui fica a base principal para vincular contas, campanhas e integrações.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="clientes.php" class="btn btn-secondary">Atualizar tela</a>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Total de clientes</span>
                    <strong><?= $totalClientes ?></strong>
                    <small>Base cadastrada no sistema</small>
                </div>

                <div class="metric-card metric-green">
                    <span>Com e-mail</span>
                    <strong><?= $totalComEmail ?></strong>
                    <small>Clientes com contato de e-mail</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Com WhatsApp</span>
                    <strong><?= $totalComWhatsapp ?></strong>
                    <small>Clientes com contato rápido</small>
                </div>

                <div class="metric-card">
                    <span>Sem WhatsApp</span>
                    <strong><?= max(0, $totalClientes - $totalComWhatsapp) ?></strong>
                    <small>Possível melhoria de cadastro</small>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3><?= $clienteEdicao ? 'Editar cliente' : 'Novo cliente' ?></h3>
                            <p class="panel-subtitle">
                                <?= $clienteEdicao
                                    ? 'Atualize os dados do cliente selecionado.'
                                    : 'Preencha as informações para adicionar um novo cliente ao sistema.' ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" class="form-stack">
                        <input type="hidden" name="acao" value="<?= $clienteEdicao ? 'atualizar' : 'criar' ?>">
                        <input type="hidden" name="id" value="<?= (int)($clienteEdicao['id'] ?? 0) ?>">

                        <div class="field">
                            <label for="nome">Nome do cliente</label>
                            <input
                                id="nome"
                                type="text"
                                name="nome"
                                class="input"
                                placeholder="Ex: Meta Cell POA"
                                required
                                value="<?= htmlspecialchars($clienteEdicao['nome'] ?? '') ?>">
                        </div>

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                class="input"
                                placeholder="Ex: contato@empresa.com"
                                value="<?= htmlspecialchars($clienteEdicao['email'] ?? '') ?>">
                        </div>

                        <div class="field">
                            <label for="whatsapp">WhatsApp</label>
                            <input
                                id="whatsapp"
                                type="text"
                                name="whatsapp"
                                class="input"
                                placeholder="Ex: (51) 99999-9999"
                                value="<?= htmlspecialchars($clienteEdicao['whatsapp'] ?? '') ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?= $clienteEdicao ? 'Atualizar cliente' : 'Salvar cliente' ?>
                            </button>

                            <?php if ($clienteEdicao): ?>
                                <a href="clientes.php" class="btn btn-ghost">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Lista de clientes</h3>
                            <p class="panel-subtitle">
                                Visualize os clientes cadastrados e acesse rapidamente as ações principais.
                            </p>
                        </div>

                        <div class="panel-actions">
                            <span class="badge badge-blue"><?= $totalClientes ?> cadastrados</span>
                        </div>
                    </div>

                    <?php if (empty($lista)): ?>
                        <div class="data-item-empty">
                            Nenhum cliente cadastrado até o momento.
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
                                                <strong>E-mail:</strong>
                                                <?= !empty($c['email']) ? htmlspecialchars($c['email']) : 'Não informado' ?>
                                            </span>

                                            <span>
                                                <strong>WhatsApp:</strong>
                                                <?= !empty($c['whatsapp']) ? htmlspecialchars($c['whatsapp']) : 'Não informado' ?>
                                            </span>

                                            <span>
                                                <strong>ID:</strong>
                                                #<?= (int)$c['id'] ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="data-item-right">
                                        <?php if (!empty($c['email'])): ?>
                                            <span class="badge badge-green">E-mail ok</span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Sem e-mail</span>
                                        <?php endif; ?>

                                        <?php if (!empty($c['whatsapp'])): ?>
                                            <span class="badge badge-blue">WhatsApp ok</span>
                                        <?php else: ?>
                                            <span class="badge badge-yellow">Sem WhatsApp</span>
                                        <?php endif; ?>

                                        <a href="clientes.php?acao=editar&id=<?= (int)$c['id'] ?>" class="btn btn-warning btn-sm">
                                            Editar
                                        </a>

                                        <a
                                            href="clientes.php?acao=excluir&id=<?= (int)$c['id'] ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Tem certeza que deseja excluir este cliente?')">
                                            Excluir
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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