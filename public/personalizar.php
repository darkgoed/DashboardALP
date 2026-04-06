<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();
$pageData = (new PersonalizacaoPageService())->getPageData();
$paginaAtual = $pageData['pagina_atual'];
$temas = $pageData['temas'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personalizar</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body class="page page-configuracoes">
    <div class="app">
        <?php include __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <i data-lucide="palette"></i>
                    </div>

                    <div>
                        <span class="page-kicker">Configurações</span>
                        <h1 class="page-title">Personalizar</h1>
                        <p class="page-subtitle">
                            Ajuste a aparência do painel e escolha entre tema dark ou white.
                        </p>
                    </div>
                </div>
            </section>

            <section class="content-grid-wide">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Tema do sistema</h3>
                            <p class="panel-subtitle">Selecione o visual padrão do dashboard.</p>
                        </div>
                    </div>

                    <div class="form-stack">
                        <div class="field field-select">
                            <label for="theme-select">Tema</label>
                            <select id="theme-select" name="theme">
                                <?php foreach ($temas as $themeKey => $themeLabel): ?>
                                    <option value="<?= htmlspecialchars($themeKey) ?>"><?= htmlspecialchars($themeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>

    <script>
        const html = document.documentElement;
        const themeSelect = document.getElementById('theme-select');

        const savedTheme = localStorage.getItem('theme') || 'dark';
        html.setAttribute('data-theme', savedTheme);
        themeSelect.value = savedTheme;

        themeSelect.addEventListener("change", function() {
            const selectedTheme = this.value;

            document.documentElement.setAttribute("data-theme", selectedTheme);
            localStorage.setItem("theme", selectedTheme);

            window.dispatchEvent(new Event("themechange"));
        });
    </script>
    <script src="../assets/js/bootstrap.js"></script>
</body>

</html>
