<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::start();

$db = new Database();
$conn = $db->connect();

$service = new LoginPageService($conn);
$erro = '';
$politicaAcesso = $service->getAccessPolicy();

$initialRedirect = $service->resolveInitialRedirect();
if ($initialRedirect !== null) {
    header('Location: ' . $initialRedirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::isValid()) {
        $erro = 'Token CSRF invalido.';
    } else {
        $result = $service->authenticate($_POST);
        $erro = $result['erro'];

        if (($result['redirect'] ?? null) !== null) {
            header('Location: ' . $result['redirect']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard ALP</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body class="login-page">
    <main class="auth-shell">
        <section class="auth-stage">
            <div class="auth-center">
                <div class="auth-brand" aria-hidden="true">
                    <div class="auth-brand-ring"></div>
                </div>

                <header class="auth-header">
                    <h1>Bem-vindo de volta</h1>
                    <p>Acesse o painel da sua empresa com o e-mail convidado ou uma conta ja ativa.</p>
                </header>

                <?php if ($erro !== ''): ?>
                    <div class="auth-error"><?= htmlspecialchars($erro); ?></div>
                <?php endif; ?>

                <?php if ($politicaAcesso['acesso_por_convite']): ?>
                    <div class="auth-note">
                        <strong>Acesso controlado por convite</strong>
                        <span>Novas contas são ativadas apenas pelo link enviado ao responsável da empresa. Se você recebeu o convite, conclua o cadastro diretamente pelo e-mail.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="auth-form" autocomplete="on">
                    <?= Csrf::field() ?>

                    <div class="field">
                        <label for="email">E-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Seu e-mail"
                            value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')); ?>"
                            required>
                    </div>

                    <div class="field">
                        <label for="senha">Senha</label>
                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            placeholder="••••••••"
                            required>
                    </div>

                    <button type="submit" class="btn-primary">Entrar</button>
                    <a href="<?= htmlspecialchars(routeUrl('recuperar-senha')); ?>" class="magic-link">Esqueci minha senha</a>
                </form>

                <div class="remember">
                    <p>Primeiro acesso? Use o convite recebido por e-mail para ativar sua conta.</p>
                </div>

                <footer class="auth-footer">
                    <p>
                        Ao entrar, você concorda com nossos
                        <a href="<?= htmlspecialchars(routeUrl('termos')); ?>">Termos de Uso</a>
                        e
                        <a href="<?= htmlspecialchars(routeUrl('privacidade')); ?>">Política de Privacidade</a>.
                    </p>
                    <?php if (!$politicaAcesso['sso_ativo'] || !$politicaAcesso['cadastro_publico_ativo']): ?>
                        <p class="auth-footer-secondary">SSO e cadastro público permanecem desativados nesta operação.</p>
                    <?php endif; ?>
                </footer>
            </div>
        </section>
    </main>
</body>

</html>
