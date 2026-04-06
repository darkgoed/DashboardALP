<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if (!method_exists('Auth', 'isPlatformRoot') || !Auth::isPlatformRoot()) {
    header('Location: ' . routeUrl('dashboard'));
    exit;
}

$db = new Database();
$conn = $db->connect();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
try {
    $pageData = (new EmpresaPageService($conn))->getDeleteData($id);
    $empresa = $pageData['empresa'];
    $paginaAtual = $pageData['pagina_atual'];
} catch (Throwable $e) {
    Flash::error($e->getMessage());
    header('Location: ' . routeUrl('empresas'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir empresa - Dashboard ALP</title>

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

<body class="page page-insights">
    <div class="app">
        <?php require_once __DIR__ . '/../partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 6h18"></path>
                            <path d="M8 6V4h8v2"></path>
                            <path d="M19 6l-1 14H6L5 6"></path>
                            <path d="M10 11v6"></path>
                            <path d="M14 11v6"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Administração</span>
                        <h1 class="page-title">Confirmar exclusão</h1>
                        <p class="page-subtitle">
                            Revise os dados abaixo antes de remover a empresa do sistema.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('empresas')); ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            </section>

            <section class="content-grid-wide">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Empresa selecionada</h3>
                            <p class="panel-subtitle">
                                Esta ação é crítica e deve ser executada apenas quando tiver certeza.
                            </p>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label>Nome fantasia</label>
                            <input type="text" value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '—'); ?>" disabled>
                        </div>

                        <div class="field">
                            <label>Responsável</label>
                            <input type="text" value="<?= htmlspecialchars($empresa['razao_social'] ?? '—'); ?>" disabled>
                        </div>

                        <div class="field">
                            <label>E-mail do responsável</label>
                            <input type="text" value="<?= htmlspecialchars($empresa['email'] ?? '—'); ?>" disabled>
                        </div>

                        <div class="field">
                            <label>Documento</label>
                            <input type="text" value="<?= htmlspecialchars($empresa['documento'] ?? '—'); ?>" disabled>
                        </div>
                    </div>

                    <div class="panel" style="margin-top: 18px; border: 1px solid rgba(239, 68, 68, .25);">
                        <div class="panel-header">
                            <div>
                                <h3 style="color: #ef4444;">Atenção</h3>
                                <p class="panel-subtitle">
                                    Ao excluir esta empresa, os vínculos e dados relacionados podem ser removidos também,
                                    dependendo das suas foreign keys e regras do banco.
                                </p>
                            </div>
                        </div>

                        <form action="<?= htmlspecialchars(routeUrl('empresas/destroy')); ?>" method="POST" class="form-stack">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int) $empresa['id']; ?>">

                            <div class="form-actions" style="justify-content:flex-start;">
                                <a href="<?= htmlspecialchars(routeUrl('empresas')); ?>" class="btn btn-secondary">Voltar</a>
                                <button type="submit" class="btn btn-danger">
                                    Confirmar exclusão
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <?php Flash::renderScript(); ?>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>

    <script src="../assets/js/bootstrap.js"></script>

</body>

</html>
