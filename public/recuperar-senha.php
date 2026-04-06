<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::start();

$db = new Database();
$conn = $db->connect();

$pageService = new PasswordRecoveryPageService($conn);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$state = $pageService->getInitialState($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::isValid()) {
        $state['erro'] = 'Token CSRF invalido.';
    } else {
        $state = $pageService->handle($state, $_POST);
    }
}

$state = $pageService->loadResetValidation($state);
$erro = $state['erro'];
$sucesso = $state['sucesso'];
$modo = $state['modo'];
$tokenValido = $state['token_valido'];
$usuarioToken = $state['usuario_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha - Dashboard ALP</title>
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

            <div class="auth-center">
                <div class="auth-brand" aria-hidden="true">
                    <div class="auth-brand-ring"></div>
                </div>

                <header class="auth-header">
                    <?php if ($modo === 'reset' && $sucesso === ''): ?>
                        <h1>Definir nova senha</h1>
                        <p>Crie uma nova senha para voltar ao painel.</p>
                    <?php else: ?>
                        <h1>Recuperar senha</h1>
                        <p>Informe seu e-mail para receber o link de acesso.</p>
                    <?php endif; ?>
                </header>

                <?php if ($erro !== ''): ?>
                    <div class="auth-error"><?= htmlspecialchars($erro) ?></div>
                <?php endif; ?>

                <?php if ($sucesso !== ''): ?>
                    <div class="auth-error" style="border-color: rgba(34, 197, 94, 0.20); background: rgba(34, 197, 94, 0.08); color: #d8ffe4;">
                        <?= htmlspecialchars($sucesso) ?>
                    </div>

                    <form method="GET" action="<?= htmlspecialchars(routeUrl('login')); ?>" class="auth-form">
                        <button type="submit" class="btn-primary">Ir para login</button>
                    </form>
                <?php elseif ($modo === 'reset' && $tokenValido): ?>
                    <form method="POST" class="auth-form" autocomplete="off">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" value="<?= htmlspecialchars((string) ($usuarioToken['email'] ?? '')) ?>" readonly>
                        </div>

                        <div class="field">
                            <label for="senha">Nova senha</label>
                            <input type="password" id="senha" name="senha" placeholder="Crie uma nova senha" required>
                        </div>

                        <div class="field">
                            <label for="confirmar_senha">Confirmar senha</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a nova senha" required>
                        </div>

                        <button type="submit" class="btn-primary">Atualizar senha</button>
                    </form>
                <?php elseif ($modo === 'request'): ?>
                    <form method="POST" class="auth-form" autocomplete="on">
                        <?= Csrf::field() ?>

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Seu e-mail"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                required>
                        </div>

                        <button type="submit" class="btn-primary">Enviar link</button>

                        <div class="remember">
                            <p>Lembrou a senha? <a href="<?= htmlspecialchars(routeUrl('login')); ?>">Entrar no painel</a></p>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>

</html>
