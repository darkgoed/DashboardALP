<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);


$usuarioId = Auth::getUsuarioId();
$usuarioIdLogado = Auth::getUsuarioId();
$empresaId = Auth::getEmpresaId();

if (!Permissao::podeGerenciarUsuarios($conn, $usuarioIdLogado, $empresaId)) {
    http_response_code(403);
    exit('Acesso negado.');
}

$paginaAtual = 'usuarios.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['erro'] = 'Usuário inválido.';
    header('Location: ' . routeUrl('usuarios'));
    exit;
}

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.nome,
        u.email,
        u.telefone,
        u.status,
        ue.perfil,
        ue.is_principal
    FROM usuarios u
    INNER JOIN usuarios_empresas ue
        ON ue.usuario_id = u.id
       AND ue.empresa_id = :empresa_id
    WHERE u.id = :id
    LIMIT 1
");
$stmt->execute([
    ':empresa_id' => $empresaId,
    ':id' => $id,
]);

$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    $_SESSION['erro'] = 'Usuário não encontrado.';
    header('Location: ' . routeUrl('usuarios'));
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar usuário - Dashboard ALP</title>

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
<body class="page page-usuarios">

<div class="app">
    <?php require_once __DIR__ . '/../partials/menu_lateral.php'; ?>

    <main class="main">
        <section class="page-hero">
            <div class="page-hero-left">
                <div class="page-hero-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 20h9"></path>
                        <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z"></path>
                    </svg>
                </div>

                <div>
                    <span class="page-kicker">Acesso</span>
                    <h1 class="page-title">Editar usuário</h1>
                    <p class="page-subtitle">
                        Atualize os dados do usuário e ajuste o nível de acesso vinculado à empresa.
                    </p>
                </div>
            </div>

            <div class="page-hero-actions">
                <a href="<?= htmlspecialchars(routeUrl('usuarios')); ?>" class="btn btn-secondary">Voltar</a>
            </div>
        </section>

        <section class="content-grid-wide">
            <div class="panel form-panel">
                <div class="panel-header">
                    <div>
                        <h3>Dados do usuário</h3>
                        <p class="panel-subtitle">Edite nome, contato, status, perfil e senha.</p>
                    </div>
                </div>

                <form action="<?= htmlspecialchars(routeUrl('usuarios/update')); ?>" method="POST" class="form-stack">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">

                    <div class="content-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label for="nome">Nome</label>
                            <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                        </div>

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input type="email" name="email" id="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                        </div>

                        <div class="field">
                            <label for="telefone">Telefone</label>
                            <input type="text" name="telefone" id="telefone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>">
                        </div>

                        <div class="field">
                            <label for="senha">Nova senha</label>
                            <input type="password" name="senha" id="senha" placeholder="Deixe em branco para manter">
                        </div>

                        <div class="field field-select">
                            <label for="perfil">Perfil</label>
                            <select name="perfil" id="perfil" required <?= (int)$usuario['is_principal'] === 1 ? 'disabled' : '' ?>>
                                <?php
                                $perfis = ['owner', 'admin', 'gestor', 'analista', 'financeiro', 'visualizador'];
                                foreach ($perfis as $perfil):
                                ?>
                                    <option value="<?= $perfil ?>" <?= $usuario['perfil'] === $perfil ? 'selected' : '' ?>>
                                        <?= ucfirst($perfil) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ((int)$usuario['is_principal'] === 1): ?>
                                <input type="hidden" name="perfil" value="<?= htmlspecialchars($usuario['perfil']) ?>">
                            <?php endif; ?>
                        </div>

                        <div class="field field-select">
                            <label for="status">Status</label>
                            <select name="status" id="status" required>
                                <option value="ativo" <?= $usuario['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                <option value="pendente" <?= $usuario['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                <option value="inativo" <?= $usuario['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                <option value="bloqueado" <?= $usuario['status'] === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                        <a href="<?= htmlspecialchars(routeUrl('usuarios')); ?>" class="btn btn-ghost">Cancelar</a>
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
