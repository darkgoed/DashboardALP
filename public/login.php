<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::start();

// if (Auth::check()) {
//     header('Location: dashboard.php');
//     exit;
// }

$db = new Database();
$conn = $db->connect();

$usuarioModel = new Usuario($conn);

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } else {
        $auth = $usuarioModel->autenticar($email, $senha);

        if (!$auth) {
            $erro = 'E-mail, senha ou vínculo com empresa inválido.';
        } else {
            Auth::login($auth['usuario'], $auth['empresa_id']);
            header('Location: dashboard.php');
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

            <!-- VOLTAR -->
            <!-- <a href="index.php" class="auth-back" aria-label="Voltar">
                <span class="auth-back-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <span>Voltar</span>
            </a> -->

            <div class="auth-center">
                
                <div class="auth-brand" aria-hidden="true">
                    <div class="auth-brand-ring"></div>
                </div>

                <header class="auth-header">
                    <h1>Bem-vindo de volta</h1>
                    <p>Acesse o painel da sua empresa</p>
                </header>

                <?php if ($erro): ?>
                    <div class="auth-error"><?= htmlspecialchars($erro) ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form" autocomplete="on">
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

                    <a href="recuperar-senha.php" class="magic-link">Esqueci minha senha</a>

                    <div class="divider">
                        <span>ou</span>
                    </div>

                    <a href="#" class="btn-secondary">Single sign-on (SSO)</a>
                </form>

                <div class="remember">
                    <p>Primeira vez aqui? <a href="#">Crie sua conta</a></p>
                </div>

                <footer class="auth-footer">
                    <p>
                        Ao entrar, você concorda com nossos
                        <a href="#">Termos de Uso</a>
                        e
                        <a href="#">Política de Privacidade</a>.
                    </p>
                </footer>
            </div>
        </section>
    </main>
</body>

</html>