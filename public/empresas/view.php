<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if (!method_exists('Auth', 'isPlatformRoot') || !Auth::isPlatformRoot()) {
    header('Location: ' . routeUrl('dashboard'));
    exit;
}

$db = new Database();
$conn = $db->connect();
$pageService = new EmpresaPageService($conn);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
try {
    $pageData = $pageService->getViewData($id);
    $empresa = $pageData['empresa'];
    $assinatura = $pageData['assinatura'];
    $statusLicenca = $pageData['status_licenca'];
    $consumo = $pageData['consumo'];
    $usuarios = $pageData['usuarios'];
    $contasAds = $pageData['contas_ads'];
    $usuariosUsados = $pageData['usuarios_usados'];
    $usuariosPercentual = $pageData['usuarios_percentual'];
    $contasUsadas = $pageData['contas_usadas'];
    $contasPercentual = $pageData['contas_percentual'];
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
    <title>Visualizar empresa - Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">

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
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Administração</span>
                        <h1 class="page-title">Visualizar empresa</h1>
                        <p class="page-subtitle">
                            Acompanhe o cadastro, assinatura, limites e estrutura operacional
                            da empresa dentro do Dashboard ALP.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('empresas/edit') . '?id=' . (int) $empresa['id']); ?>" class="btn btn-primary">Editar empresa</a>
                    <a href="<?= htmlspecialchars(routeUrl('empresas')); ?>" class="btn btn-secondary">Voltar</a>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Empresa</span>
                    <strong><?= htmlspecialchars($empresa['nome_fantasia'] ?? '—'); ?></strong>
                    <small>ID #<?= (int) $empresa['id']; ?> · Slug <?= htmlspecialchars($empresa['slug'] ?? '—'); ?></small>
                </div>

                <div class="metric-card">
                    <span>Licença</span>
                    <strong><?= htmlspecialchars($statusLicenca['status_assinatura'] ?? '—'); ?></strong>
                    <small><?= !empty($statusLicenca['motivo']) ? htmlspecialchars($statusLicenca['motivo']) : 'Sem alertas críticos.'; ?></small>
                </div>

                <div class="metric-card metric-green">
                    <span>Usuários</span>
                    <strong><?= $usuariosUsados; ?>/<?= (int) ($consumo['usuarios']['limite'] ?? 0); ?></strong>
                    <small><?= (int) ($consumo['usuarios']['disponivel'] ?? 0); ?> disponível(is)</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Contas ads</span>
                    <strong><?= $contasUsadas; ?>/<?= (int) ($consumo['contas_ads']['limite'] ?? 0); ?></strong>
                    <small><?= (int) ($consumo['contas_ads']['disponivel'] ?? 0); ?> disponível(is)</small>
                </div>
            </section>

            <section class="panel list-panel">
                <div class="panel-header">
                    <div>
                        <h3>Dados da empresa</h3>
                        <p class="panel-subtitle">Identificação principal, status cadastral e dados de contato.</p>
                    </div>
                </div>

                <div class="content-grid content-grid-2">
                    <div class="field">
                        <label>Nome fantasia</label>
                        <input type="text" value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '—'); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Razão social</label>
                        <input type="text" value="<?= htmlspecialchars($empresa['razao_social'] ?? '—'); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Documento</label>
                        <input type="text" value="<?= htmlspecialchars($empresa['documento'] ?? '—'); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>E-mail</label>
                        <input type="text" value="<?= htmlspecialchars($empresa['email'] ?? '—'); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Telefone</label>
                        <input type="text" value="<?= htmlspecialchars($empresa['telefone'] ?? '—'); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>UUID</label>
                        <input type="text" value="<?= htmlspecialchars($empresa['uuid'] ?? '—'); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Plano textual</label>
                        <div class="field-static">
                            <span class="badge"><?= htmlspecialchars($empresa['plano'] ?? '—'); ?></span>
                        </div>
                    </div>

                    <div class="field">
                        <label>Status cadastral</label>
                        <div class="field-static">
                            <span class="<?= EmpresaPageHelper::badgeClasseView((string) ($empresa['status'] ?? '')); ?>">
                                <?= htmlspecialchars($empresa['status'] ?? '—'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="field">
                        <label>Empresa root</label>
                        <input type="text" value="<?= !empty($empresa['is_root']) ? 'Sim' : 'Não'; ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Criada em</label>
                        <input type="text" value="<?= EmpresaPageHelper::formatarDataView($empresa['criado_em'] ?? null, true); ?>" disabled>
                    </div>
                </div>
            </section>

            <section class="panel list-panel">
                <div class="panel-header">
                    <div>
                        <h3>Assinatura e licença</h3>
                        <p class="panel-subtitle">Situação comercial, vigência, tolerância e bloqueio manual.</p>
                    </div>
                </div>

                <div class="content-grid content-grid-2">
                    <div class="field">
                        <label>Status da assinatura</label>
                        <div class="field-static">
                            <span class="<?= EmpresaPageHelper::badgeClasseView((string) ($statusLicenca['status_assinatura'] ?? '')); ?>">
                                <?= htmlspecialchars($statusLicenca['status_assinatura'] ?? '—'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="field">
                        <label>Status de acesso</label>
                        <div class="field-static">
                            <span class="<?= EmpresaPageHelper::badgeClasseView((string) ($statusLicenca['status_acesso'] ?? '')); ?>">
                                <?= htmlspecialchars($statusLicenca['status_acesso'] ?? '—'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="field">
                        <label>Tipo de cobrança</label>
                        <input type="text" value="<?= htmlspecialchars($assinatura['tipo_cobranca'] ?? '—'); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Plano estruturado (ID)</label>
                        <input type="text" value="<?= htmlspecialchars((string) ($assinatura['plano_id'] ?? '—')); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Data de início</label>
                        <input type="text" value="<?= EmpresaPageHelper::formatarDataView($assinatura['data_inicio'] ?? null, true); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Vencimento</label>
                        <input type="text" value="<?= EmpresaPageHelper::formatarDataView($statusLicenca['data_vencimento'] ?? null, true); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Limite da tolerância</label>
                        <input type="text" value="<?= EmpresaPageHelper::formatarDataView($statusLicenca['data_limite_tolerancia'] ?? null, true); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Dias restantes</label>
                        <input type="text" value="<?= htmlspecialchars((string) ($statusLicenca['dias_restantes'] ?? '—')); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Dias de tolerância</label>
                        <input type="text" value="<?= htmlspecialchars((string) ($assinatura['dias_tolerancia'] ?? '0')); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Bloqueio manual</label>
                        <input type="text" value="<?= !empty($assinatura['bloqueio_manual']) ? 'Sim' : 'Não'; ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Motivo do bloqueio</label>
                        <input type="text" value="<?= htmlspecialchars($assinatura['bloqueio_manual_motivo'] ?? ($statusLicenca['motivo'] ?? '—')); ?>" disabled>
                    </div>

                    <div class="field">
                        <label>Valor cobrado</label>
                        <input type="text" value="<?= isset($assinatura['valor_cobrado']) && $assinatura['valor_cobrado'] !== null ? 'R$ ' . number_format((float) $assinatura['valor_cobrado'], 2, ',', '.') : '—'; ?>" disabled>
                    </div>
                </div>
            </section>

            <section class="panel list-panel">
                <div class="panel-header">
                    <div>
                        <h3>Limites e consumo</h3>
                        <p class="panel-subtitle">Uso atual da estrutura permitida pelo plano da empresa.</p>
                    </div>
                </div>

                <div class="content-grid content-grid-2">
                    <div class="metric-card">
                        <span>Usuários</span>
                        <strong><?= $usuariosUsados; ?>/<?= (int) ($consumo['usuarios']['limite'] ?? 0); ?></strong>
                        <small><?= (int) ($consumo['usuarios']['disponivel'] ?? 0); ?> disponível(is)</small>
                        <div style="width:100%; height:8px; border-radius:999px; overflow:hidden; background:var(--border-color, rgba(255,255,255,.08)); margin-top:10px;">
                            <div style="width: <?= $usuariosPercentual; ?>%; height:100%; border-radius:999px; background: var(--primary);"></div>
                        </div>
                        <small style="margin-top:8px; display:block;">
                            <?= !empty($consumo['usuarios']['atingido']) ? 'Limite atingido' : 'Dentro do limite'; ?>
                        </small>
                    </div>

                    <div class="metric-card">
                        <span>Contas ads</span>
                        <strong><?= $contasUsadas; ?>/<?= (int) ($consumo['contas_ads']['limite'] ?? 0); ?></strong>
                        <small><?= (int) ($consumo['contas_ads']['disponivel'] ?? 0); ?> disponível(is)</small>
                        <div style="width:100%; height:8px; border-radius:999px; overflow:hidden; background:var(--border-color, rgba(255,255,255,.08)); margin-top:10px;">
                            <div style="width: <?= $contasPercentual; ?>%; height:100%; border-radius:999px; background: var(--primary);"></div>
                        </div>
                        <small style="margin-top:8px; display:block;">
                            <?= !empty($consumo['contas_ads']['atingido']) ? 'Limite atingido' : 'Dentro do limite'; ?>
                        </small>
                    </div>
                </div>
            </section>

            <section class="panel list-panel">
                <div class="panel-header">
                    <div>
                        <h3>Usuários vinculados</h3>
                        <p class="panel-subtitle">Lista de usuários associados a esta empresa.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <?php if (empty($usuarios)): ?>
                        <table class="table">
                            <tbody>
                                <tr>
                                    <td style="text-align:center;">Nenhum usuário vinculado a esta empresa.</td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Perfil</th>
                                    <th>Status vínculo</th>
                                    <th>Status usuário</th>
                                    <th>Último login</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; flex-direction:column; gap:4px;">
                                                <strong><?= htmlspecialchars($usuario['nome'] ?? '—'); ?></strong>
                                                <span><?= htmlspecialchars($usuario['email'] ?? '—'); ?></span>
                                                <?php if (!empty($usuario['is_principal'])): ?>
                                                    <small>Usuário principal</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge"><?= htmlspecialchars($usuario['perfil'] ?? '—'); ?></span>
                                        </td>

                                        <td>
                                            <span class="<?= EmpresaPageHelper::badgeClasseView((string) ($usuario['vinculo_status'] ?? '')); ?>">
                                                <?= htmlspecialchars($usuario['vinculo_status'] ?? '—'); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="<?= EmpresaPageHelper::badgeClasseView((string) ($usuario['usuario_status'] ?? '')); ?>">
                                                <?= htmlspecialchars($usuario['usuario_status'] ?? '—'); ?>
                                            </span>
                                        </td>

                                        <td><?= EmpresaPageHelper::formatarDataView($usuario['ultimo_login_em'] ?? null, true); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel list-panel">
                <div class="panel-header">
                    <div>
                        <h3>Contas de anúncio</h3>
                        <p class="panel-subtitle">Estrutura de contas ads vinculadas à empresa.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <?php if (empty($contasAds)): ?>
                        <table class="table">
                            <tbody>
                                <tr>
                                    <td style="text-align:center;">Nenhuma conta ads cadastrada para esta empresa.</td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Conta</th>
                                    <th>ID Meta</th>
                                    <th>Status</th>
                                    <th>Sync</th>
                                    <th>Última atividade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contasAds as $conta): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; flex-direction:column; gap:4px;">
                                                <strong><?= htmlspecialchars($conta['nome'] ?? '—'); ?></strong>
                                                <span><?= htmlspecialchars($conta['business_name'] ?? '—'); ?></span>
                                                <small>
                                                    <?= htmlspecialchars($conta['moeda'] ?? '—'); ?>
                                                    ·
                                                    <?= htmlspecialchars($conta['timezone_name'] ?? '—'); ?>
                                                </small>
                                            </div>
                                        </td>

                                        <td><?= htmlspecialchars($conta['meta_account_id'] ?? '—'); ?></td>

                                        <td>
                                            <div style="display:flex; flex-direction:column; gap:6px;">
                                                <span class="<?= EmpresaPageHelper::badgeClasseView(!empty($conta['ativo']) ? 'ativo' : 'inativo'); ?>">
                                                    <?= !empty($conta['ativo']) ? 'ativo' : 'inativo'; ?>
                                                </span>
                                                <span class="<?= EmpresaPageHelper::badgeClasseView((string) ($conta['status'] ?? '')); ?>">
                                                    <?= htmlspecialchars($conta['status'] ?? '—'); ?>
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="<?= EmpresaPageHelper::badgeClasseView((string) ($conta['status_sync'] ?? '')); ?>">
                                                <?= htmlspecialchars($conta['status_sync'] ?? '—'); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div style="display:flex; flex-direction:column; gap:4px;">
                                                <small>Estrutura: <?= EmpresaPageHelper::formatarDataView($conta['ultima_sync_estrutura_em'] ?? null, true); ?></small>
                                                <small>Insights: <?= EmpresaPageHelper::formatarDataView($conta['ultima_sync_insights_em'] ?? null, true); ?></small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>

    <script src="../assets/js/bootstrap.js"></script>

</body>
</html>
