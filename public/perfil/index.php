<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$usuarioId = (int) Auth::getUsuarioId();
try {
    $pageData = (new PerfilPageService($conn))->getPageData($usuarioId);
    $usuario = $pageData['usuario'];
    $old = $pageData['old'];
    $errors = $pageData['errors'];
    $flashSuccess = $pageData['flash_success'];
    $flashError = $pageData['flash_error'];
    $fotoAtual = $pageData['foto_atual'];
    $iniciaisUsuario = $pageData['iniciais_usuario'];
} catch (Throwable $e) {
    Flash::error($e->getMessage());
    header('Location: ' . routeUrl('dashboard'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar perfil - Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/toast.css">
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body class="page page-usuarios">
<div class="app">
    <?php require_once __DIR__ . '/../partials/menu_lateral.php'; ?>

    <main class="main">
        <section class="page-hero">
            <div class="page-hero-left">
                <div class="page-hero-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"></path>
                        <path d="M4 20a8 8 0 0 1 16 0"></path>
                    </svg>
                </div>

                <div>
                    <span class="page-kicker">Conta</span>
                    <h1 class="page-title">Editar perfil</h1>
                    <p class="page-subtitle">
                        Atualize seus dados de acesso com confirmacao pela senha atual.
                    </p>
                </div>
            </div>

            <div class="page-hero-actions">
                <a href="<?= htmlspecialchars(routeUrl('dashboard')); ?>" class="btn btn-secondary">Voltar</a>
            </div>
        </section>

        <?php if ($flashSuccess): ?>
            <div class="panel" style="margin-bottom: 18px;">
                <div class="badge badge-green" style="margin-bottom: 10px;">Sucesso</div>
                <p style="margin:0;"><?= htmlspecialchars($flashSuccess); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="panel" style="margin-bottom: 18px;">
                <div class="badge badge-red" style="margin-bottom: 10px;">Erro</div>
                <p style="margin:0;"><?= htmlspecialchars($flashError); ?></p>
            </div>
        <?php endif; ?>

        <section class="content-grid-wide">
            <div class="panel form-panel">
                <div class="panel-header">
                    <div>
                        <h3>Dados do perfil</h3>
                        <p class="panel-subtitle">Nome, e-mail, telefone e troca opcional de senha.</p>
                    </div>
                </div>

                <form action="<?= htmlspecialchars(routeUrl('perfil/update')); ?>" method="POST" enctype="multipart/form-data" class="form-stack" autocomplete="off">
                    <?= Csrf::field() ?>

                    <div class="content-grid" style="grid-template-columns: minmax(220px, 280px) minmax(0, 1fr); align-items: start; margin-bottom: 8px;">
                        <div class="field">
                            <label>Foto do perfil</label>
                            <div class="profile-avatar-card">
                                <?php if ($fotoAtual !== ''): ?>
                                    <img src="<?= htmlspecialchars($fotoAtual); ?>" alt="Foto do usuario" class="profile-avatar-preview">
                                <?php else: ?>
                                    <div class="profile-avatar-fallback"><?= htmlspecialchars($iniciaisUsuario); ?></div>
                                <?php endif; ?>

                                <div class="profile-avatar-meta">
                                    <strong>Imagem do usuario</strong>
                                    <span>PNG, JPG ou WEBP com ate 3 MB.</span>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label for="foto">Alterar foto</label>
                            <input type="file" name="foto" id="foto" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                            <small>Envie uma imagem quadrada se quiser um resultado melhor no avatar.</small>
                            <?= PerfilViewHelper::errorHtml($errors, 'foto'); ?>

                            <?php if ($fotoAtual !== ''): ?>
                                <label style="display:flex;align-items:center;gap:10px;margin-top:12px;">
                                    <input type="checkbox" name="remover_foto" value="1">
                                    <span>Remover foto atual</span>
                                </label>
                            <?php endif; ?>

                            <div class="form-actions" style="margin-top: 14px;">
                                <button type="submit" name="acao" value="salvar_foto" class="btn btn-secondary">Salvar foto</button>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label for="nome">Nome</label>
                            <input type="text" name="nome" id="nome" value="<?= PerfilViewHelper::oldValue($old, $usuario, 'nome'); ?>" required>
                            <?= PerfilViewHelper::errorHtml($errors, 'nome'); ?>
                        </div>

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input type="email" name="email" id="email" value="<?= PerfilViewHelper::oldValue($old, $usuario, 'email'); ?>" required>
                            <?= PerfilViewHelper::errorHtml($errors, 'email'); ?>
                        </div>

                        <div class="field">
                            <label for="telefone">Telefone</label>
                            <input type="text" name="telefone" id="telefone" value="<?= PerfilViewHelper::oldValue($old, $usuario, 'telefone'); ?>">
                            <?= PerfilViewHelper::errorHtml($errors, 'telefone'); ?>
                        </div>

                        <div class="field">
                            <label for="senha_atual">Senha atual</label>
                            <input type="password" name="senha_atual" id="senha_atual" placeholder="Informe apenas se alterar dados ou senha" autocomplete="current-password">
                            <small>Exigida apenas para alterar nome, e-mail, telefone ou senha.</small>
                            <?= PerfilViewHelper::errorHtml($errors, 'senha_atual'); ?>
                        </div>

                        <div class="field">
                            <label for="nova_senha">Nova senha</label>
                            <input type="password" name="nova_senha" id="nova_senha" placeholder="Deixe em branco para manter" autocomplete="new-password">
                            <small>Use pelo menos 8 caracteres se quiser trocar a senha.</small>
                            <?= PerfilViewHelper::errorHtml($errors, 'nova_senha'); ?>
                        </div>

                        <div class="field">
                            <label for="confirmar_nova_senha">Confirmar nova senha</label>
                            <input type="password" name="confirmar_nova_senha" id="confirmar_nova_senha" placeholder="Repita a nova senha" autocomplete="new-password">
                            <?= PerfilViewHelper::errorHtml($errors, 'confirmar_nova_senha'); ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Salvar perfil</button>
                        <a href="<?= htmlspecialchars(routeUrl('dashboard')); ?>" class="btn btn-ghost">Cancelar</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>

<?php Flash::renderScript(); ?>
<script src="../assets/js/bootstrap.js"></script>
</body>
</html>
