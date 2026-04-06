<?php

class StaticContentPageRenderer
{
    public static function render(array $page): void
    {
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) $page['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="login-page">
    <main class="auth-shell">
        <section class="auth-stage">
            <a href="<?= htmlspecialchars(routeUrl('login')); ?>" class="auth-back" aria-label="Voltar ao login">
                <span class="auth-back-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <span>Voltar ao login</span>
            </a>

            <div class="auth-center" style="max-width: 720px; text-align: left; align-items: stretch;">
                <header class="auth-header" style="text-align: left;">
                    <h1><?= htmlspecialchars((string) $page['heading']); ?></h1>
                    <p><?= htmlspecialchars((string) $page['subtitle']); ?></p>
                </header>

                <?php foreach (($page['sections'] ?? []) as $index => $section): ?>
                    <div class="auth-note"<?= $index === 0 ? ' style="margin-top: 22px;"' : ''; ?>>
                        <strong><?= htmlspecialchars((string) ($section['title'] ?? '')); ?></strong>
                        <span><?= htmlspecialchars((string) ($section['text'] ?? '')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</body>
</html>
<?php
    }
}
