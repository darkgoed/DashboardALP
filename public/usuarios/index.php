<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

if (!Permissao::podeGerenciarUsuarios($conn, $usuarioId, $empresaId)) {
    http_response_code(403);
    exit('Acesso negado.');
}

$paginaAtual = 'usuarios.php';
$usuarioManagementService = new UsuarioManagementService($conn);
$pageData = $usuarioManagementService->getIndexData($empresaId);

$usuarios = $pageData['usuarios'];
$stats = $pageData['stats'];

$totalUsuarios = (int) ($stats['total_usuarios'] ?? 0);
$totalAtivos = (int) ($stats['total_ativos'] ?? 0);
$totalAdmins = (int) ($stats['total_admins'] ?? 0);
$totalPrincipais = (int) ($stats['total_principais'] ?? 0);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Dashboard ALP</title>

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
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <path d="M20 8v6"></path>
                            <path d="M23 11h-6"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Acesso</span>
                        <h1 class="page-title">Usuários</h1>
                        <p class="page-subtitle">
                            Gerencie os acessos da empresa, crie novos usuários e acompanhe os perfis
                            ativos vinculados ao ambiente atual.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('usuarios')); ?>" class="btn btn-secondary">Atualizar tela</a>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Total de usuários</span>
                    <strong><?= number_format($totalUsuarios, 0, ',', '.') ?></strong>
                    <small>Cadastros vinculados à empresa</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Usuários ativos</span>
                    <strong><?= number_format($totalAtivos, 0, ',', '.') ?></strong>
                    <small>Contas liberadas para acesso</small>
                </div>

                <div class="metric-card metric-green">
                    <span>Admins / Owners</span>
                    <strong><?= number_format($totalAdmins, 0, ',', '.') ?></strong>
                    <small>Perfis com gestão ampliada</small>
                </div>

                <div class="metric-card">
                    <span>Principais</span>
                    <strong><?= number_format($totalPrincipais, 0, ',', '.') ?></strong>
                    <small>Usuários marcados como principal</small>
                </div>
            </section>

            <section class="content-grid-wide">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Criar novo usuário</h3>
                            <p class="panel-subtitle">
                                Cadastre um novo acesso para esta empresa e defina o nível de permissão inicial.
                            </p>
                        </div>
                    </div>

                    <form action="<?= htmlspecialchars(routeUrl('usuarios/store')); ?>" method="POST" class="form-stack">
                        <?= Csrf::field() ?>
                        <div class="content-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                            <div class="field">
                                <label for="nome">Nome</label>
                                <input type="text" name="nome" id="nome" required>
                            </div>

                            <div class="field">
                                <label for="email">E-mail</label>
                                <input type="email" name="email" id="email" required>
                            </div>

                            <div class="field">
                                <label for="telefone">Telefone</label>
                                <input type="text" name="telefone" id="telefone">
                            </div>

                            <div class="field">
                                <label for="senha">Senha</label>
                                <input type="password" name="senha" id="senha" required>
                            </div>

                            <div class="field field-select">
                                <label for="perfil">Perfil</label>
                                <select name="perfil" id="perfil" required>
                                    <option value="admin">Admin</option>
                                    <option value="gestor">Gestor</option>
                                    <option value="analista">Analista</option>
                                    <option value="financeiro">Financeiro</option>
                                    <option value="visualizador">Visualizador</option>
                                </select>
                            </div>

                            <div class="field field-select">
                                <label for="status">Status</label>
                                <select name="status" id="status" required>
                                    <option value="ativo">Ativo</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="inativo">Inativo</option>
                                    <option value="bloqueado">Bloqueado</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Criar usuário</button>
                        </div>
                    </form>
                </div>

                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Usuários cadastrados</h3>
                            <p class="panel-subtitle">
                                Lista completa de usuários vinculados à empresa atual, com perfil, status e data de criação.
                            </p>
                        </div>

                        <div class="panel-actions">
                            <span class="badge badge-purple">
                                <?= number_format($totalUsuarios, 0, ',', '.') ?> usuários
                            </span>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Telefone</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                    <th>Principal</th>
                                    <th>Criado em</th>
                                    <th style="width: 180px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center;">Nenhum usuário encontrado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <?php
                                        $isPrincipal = (int) ($u['is_principal'] ?? 0) === 1;
                                        $isEu = (int) $u['id'] === (int) $usuarioId;
                                        $podeExcluir = !$isPrincipal && !$isEu;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($u['nome']) ?></td>
                                            <td><?= htmlspecialchars($u['email']) ?></td>
                                            <td><?= htmlspecialchars($u['telefone'] ?: '—') ?></td>
                                            <td><?= htmlspecialchars($u['perfil']) ?></td>
                                            <td><?= htmlspecialchars($u['status']) ?></td>
                                            <td><?= $isPrincipal ? 'Sim' : 'Não' ?></td>
                                            <td><?= htmlspecialchars($u['criado_em']) ?></td>
                                            <td>
                                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                    <a href="<?= htmlspecialchars(routeUrl('usuarios/edit') . '?id=' . (int) $u['id']); ?>" class="btn btn-secondary btn-sm">
                                                        Editar
                                                    </a>

                                                    <?php if ($podeExcluir): ?>
                                                        <form action="<?= htmlspecialchars(routeUrl('usuarios/delete')); ?>" method="POST" onsubmit="return confirm('Deseja realmente excluir este usuário?');" style="margin:0;">
                                                            <?= Csrf::field() ?>
                                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                            <button type="submit" class="btn btn-ghost btn-sm">
                                                                Excluir
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge">Protegido</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <?php Flash::renderScript(); ?>
    <script src="../assets/js/bootstrap.js"></script>
</body>

</html>
