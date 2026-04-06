<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$db = new Database();
$conn = $db->connect();

$service = new ConviteAcceptanceService($conn);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$pageState = $service->getPageData($token);
$erro = $pageState['erro'];
$sucesso = $pageState['sucesso'];
$convite = $pageState['convite'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    if (!Csrf::isValid()) {
        $erro = 'Token CSRF inválido.';
    } else {
        $result = $service->accept($token, $_POST);
        $erro = $result['erro'];
        $sucesso = $result['sucesso'];
        $convite = $result['convite'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta - Dashboard ALP</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css">

    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body class="login-page">
    <main class="auth-shell">
        <section class="auth-stage">

            <a href="<?= htmlspecialchars(routeUrl('login')); ?>" class="auth-back" aria-label="Ir para login">
                <span class="auth-back-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </span>
                <span>Voltar ao login</span>
            </a>

            <div class="auth-center">
                <div class="auth-brand" aria-hidden="true">
                    <div class="auth-brand-ring"></div>
                </div>

                <header class="auth-header">
                    <h1>Criar sua conta</h1>
                    <p>Finalize o convite e crie o usuário administrador da empresa.</p>
                </header>

                <?php if ($erro !== ''): ?>
                    <div class="auth-error"><?= htmlspecialchars($erro); ?></div>
                <?php endif; ?>

                <?php if ($sucesso !== ''): ?>
                    <div class="auth-error"
                        style="border-color: rgba(34, 197, 94, 0.20); background: rgba(34, 197, 94, 0.08); color: #d8ffe4;">
                        <?= htmlspecialchars($sucesso); ?>
                    </div>

                    <form class="auth-form" action="<?= htmlspecialchars(routeUrl('login')); ?>" method="GET">
                        <button type="submit" class="btn-primary">Ir para login</button>
                    </form>
                <?php endif; ?>

                <?php if ($convite): ?>
                    <form method="POST" class="auth-form" autocomplete="off" novalidate>
                        <?= Csrf::field() ?>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">

                        <div class="field">
                            <label for="empresa">Empresa</label>
                            <input type="text" id="empresa" value="<?= htmlspecialchars(ConviteAcceptanceService::empresaNome($convite)); ?>"
                                readonly>
                        </div>

                        <div class="field">
                            <label for="email_convite">E-mail</label>
                            <input type="email" id="email_convite"
                                value="<?= htmlspecialchars((string) $convite['email']); ?>" readonly>
                        </div>

                        <div class="field">
                            <label for="nome">Seu nome</label>
                            <input type="text" id="nome" name="nome" placeholder="Seu nome completo"
                                value="<?= htmlspecialchars($_POST['nome'] ?? (string) ($convite['nome'] ?? '')); ?>"
                                required>
                        </div>

                        <div class="field">
                            <label for="senha">Senha</label>
                            <input type="password" id="senha" name="senha" placeholder="Crie uma senha" required>
                        </div>

                        <div class="field">
                            <label for="confirmar_senha">Confirmar senha</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha"
                                placeholder="Confirme sua senha" required>
                        </div>

                        <button type="submit" class="btn-primary">Criar conta</button>

                        <div class="remember">
                            <p>Já tem acesso? <a href="<?= htmlspecialchars(routeUrl('login')); ?>">Entrar no painel</a></p>
                        </div>

                        <footer class="auth-footer">
                            <p>
                                Este convite é individual e vinculado ao e-mail
                                <strong><?= htmlspecialchars((string) $convite['email']); ?></strong>.
                            </p>
                        </footer>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>
</body>

</html>
