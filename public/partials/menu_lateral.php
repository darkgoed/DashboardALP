<?php

if (!class_exists('Auth') || !class_exists('Database') || !class_exists('Permissao')) {
    require_once __DIR__ . '/../../app/config/bootstrap.php';
}

require_once __DIR__ . '/../../app/support/MenuSidebarHelper.php';

if (!Auth::check()) {
    header('Location: ' . routeUrl('login'));
    exit;
}

if (!isset($conn) || !($conn instanceof PDO)) {
    $db = new Database();
    $conn = $db->connect();
}

$menuData = (new MenuSidebarService($conn))->getViewData([
    'mostrarFiltrosSidebar' => $mostrarFiltrosSidebar ?? false,
    'contas' => $contas ?? [],
    'campanhas' => $campanhas ?? [],
    'contaId' => $contaId ?? null,
    'campanhaId' => $campanhaId ?? null,
    'campanhaStatus' => $campanhaStatus ?? null,
    'dataInicio' => $dataInicio ?? null,
    'dataFim' => $dataFim ?? null,
    'periodo' => $periodo ?? null,
]);

$podeGerenciarUsuarios = $menuData['podeGerenciarUsuarios'];
$podeGerenciarEmpresas = $menuData['podeGerenciarEmpresas'];
$mostrarFiltros = $menuData['mostrarFiltros'];
$contas = $menuData['contas'];
$campanhas = $menuData['campanhas'];
$contaId = $menuData['contaId'];
$campanhaId = $menuData['campanhaId'];
$campanhaStatus = $menuData['campanhaStatus'];
$dataInicio = $menuData['dataInicio'];
$dataFim = $menuData['dataFim'];
$periodo = $menuData['periodo'];
$requestPath = $menuData['requestPath'];
$relatoriosHref = $menuData['relatoriosHref'];
$isConfiguracoesGroup = $menuData['isConfiguracoesGroup'];
$isMonitoramentoGroup = $menuData['isMonitoramentoGroup'];
$paginaLimparFiltros = $menuData['paginaLimparFiltros'];
$usuarioNome = $menuData['usuarioNome'];
$usuarioEmail = $menuData['usuarioEmail'];
$usuarioFoto = $menuData['usuarioFoto'];
?>

<aside class="sidebar">
    <div class="sidebar-top">
        <div class="brand">
            <div class="brand-logo">
                <i data-lucide="bar-chart-3"></i>
            </div>
            <div class="brand-text">
                <strong>Meta Ads</strong>
                <span>Dashboard</span>
            </div>
        </div>

        <div class="sidebar-section">
            <div class="section-title">PAINEL</div>

            <a href="<?= htmlspecialchars(routeUrl('dashboard')) ?>"
                class="nav-link <?= alp_nav_active(['dashboard'], $requestPath) ?>">
                <i data-lucide="layout-dashboard"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="section-title">ANALISE</div>

            <a href="<?= htmlspecialchars(routeUrl('campanhas')) ?>"
                class="nav-link <?= alp_nav_active(['campanhas'], $requestPath) ?>">
                <i data-lucide="megaphone"></i>
                <span>Campanhas</span>
            </a>

            <a href="<?= htmlspecialchars($relatoriosHref) ?>"
                class="nav-link <?= alp_nav_active(['relatorios'], $requestPath) ?>">
                <i data-lucide="scroll-text"></i>
                <span>Relatorios</span>
            </a>

            <a href="<?= htmlspecialchars(routeUrl('metricas')) ?>"
                class="nav-link <?= alp_nav_active(['metricas'], $requestPath) ?>">
                <i data-lucide="bar-chart"></i>
                <span>Metricas</span>
            </a>

            <a href="<?= htmlspecialchars(routeUrl('insights')) ?>"
                class="nav-link <?= alp_nav_active(['insights'], $requestPath) ?>">
                <i data-lucide="sparkles"></i>
                <span>Insights</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="section-title">MONITORAMENTO</div>

            <a href="<?= htmlspecialchars(routeUrl('sync_dashboard')) ?>"
                class="nav-link <?= alp_nav_active(['sync_dashboard'], $requestPath) ?>">
                <i data-lucide="activity-square"></i>
                <span>Dashboard Sync</span>
            </a>

            <a href="<?= htmlspecialchars(routeUrl('sync_logs')) ?>"
                class="nav-link <?= $isMonitoramentoGroup ? 'active' : '' ?>">
                <i data-lucide="activity"></i>
                <span>Logs de Sync</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="section-title">ADMINISTRACAO</div>

            <div class="nav-group <?= $isConfiguracoesGroup ? 'open' : '' ?>">
                <button
                    type="button"
                    class="nav-link nav-link-group <?= $isConfiguracoesGroup ? 'active' : '' ?>"
                    data-nav-toggle="configuracoes"
                    aria-expanded="<?= $isConfiguracoesGroup ? 'true' : 'false' ?>">

                    <span class="nav-link-main">
                        <i data-lucide="settings-2"></i>
                        <span>Configuracoes</span>
                    </span>

                    <i data-lucide="chevron-down" class="nav-group-chevron"></i>
                </button>

                <div class="nav-submenu" id="nav-submenu-configuracoes">
                    <?php if ($podeGerenciarEmpresas): ?>
                        <a href="<?= htmlspecialchars(routeUrl('empresas')) ?>"
                            class="nav-sublink <?= alp_nav_active(['empresas'], $requestPath) ?>">
                            <i data-lucide="building-2"></i>
                            <span>Empresas</span>
                            <?= alp_nav_root_badge(); ?>
                        </a>
                    <?php endif; ?>

                    <a href="<?= htmlspecialchars(routeUrl('clientes')) ?>"
                        class="nav-sublink <?= alp_nav_active(['clientes'], $requestPath) ?>">
                        <i data-lucide="users"></i>
                        <span>Clientes</span>
                    </a>

                    <a href="<?= htmlspecialchars(routeUrl('contas')) ?>"
                        class="nav-sublink <?= alp_nav_active(['contas'], $requestPath) ?>">
                        <i data-lucide="briefcase-business"></i>
                        <span>Contas</span>
                    </a>

                    <?php if ($podeGerenciarUsuarios): ?>
                        <a href="<?= htmlspecialchars(routeUrl('usuarios')) ?>"
                            class="nav-sublink <?= alp_nav_active(['usuarios'], $requestPath) ?>">
                            <i data-lucide="user-cog"></i>
                            <span>Usuarios</span>
                        </a>
                    <?php endif; ?>

                    <a href="<?= htmlspecialchars(routeUrl('integracoes_meta')) ?>"
                        class="nav-sublink <?= alp_nav_active(['integracoes_meta'], $requestPath) ?>">
                        <i data-lucide="plug-zap"></i>
                        <span>Integracoes Meta</span>
                    </a>

                    <a href="<?= htmlspecialchars(routeUrl('api')) ?>"
                        class="nav-sublink <?= alp_nav_active(['api'], $requestPath) ?>">
                        <i data-lucide="key-round"></i>
                        <span>API</span>
                    </a>

                    <a href="<?= htmlspecialchars(routeUrl('conexoes')) ?>"
                        class="nav-sublink <?= alp_nav_active(['conexoes'], $requestPath) ?>">
                        <i data-lucide="blocks"></i>
                        <span>Conexoes</span>
                    </a>

                    <a href="<?= htmlspecialchars(routeUrl('personalizar')) ?>"
                        class="nav-sublink <?= alp_nav_active(['personalizar'], $requestPath) ?>">
                        <i data-lucide="palette"></i>
                        <span>Personalizar</span>
                    </a>
                </div>
            </div>
        </div>

        <?php if ($mostrarFiltros): ?>
            <div class="sidebar-section">
                <div class="section-title">FILTROS</div>

                <form method="GET" class="sidebar-form" id="filtersForm">
                    <div class="field field-select">
                        <label for="conta_id">Conta</label>
                        <select name="conta_id" id="conta_id">
                            <option value="">Todas</option>
                            <?php foreach ($contas as $conta): ?>
                                <option
                                    value="<?= htmlspecialchars((string) $conta['id']) ?>"
                                    <?= ((string) $contaId === (string) $conta['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $conta['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field field-select">
                        <label for="campanha_id">Campanha</label>
                        <select name="campanha_id" id="campanha_id">
                            <option value="">Todas</option>
                            <?php foreach ($campanhas as $campanha): ?>
                                <?php
                                $campanhaLabel = alp_campaign_display_name($campanha);
                                $objetivoLabel = alp_campaign_goal_label($campanha['objetivo'] ?? '');
                                if ($objetivoLabel !== '') {
                                    $campanhaLabel .= ' - ' . $objetivoLabel;
                                }
                                ?>
                                <option
                                    value="<?= htmlspecialchars((string) $campanha['id']) ?>"
                                    <?= ((string) $campanhaId === (string) $campanha['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($campanhaLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field field-select">
                        <label for="campanha_status">Status da campanha</label>
                        <select name="campanha_status" id="campanha_status">
                            <option value="">Todos</option>
                            <option value="ACTIVE" <?= $campanhaStatus === 'ACTIVE' ? 'selected' : '' ?>>Ativas</option>
                            <option value="PAUSED" <?= $campanhaStatus === 'PAUSED' ? 'selected' : '' ?>>Pausadas</option>
                            <option value="DELETED" <?= $campanhaStatus === 'DELETED' ? 'selected' : '' ?>>Deletadas</option>
                            <option value="ARCHIVED" <?= $campanhaStatus === 'ARCHIVED' ? 'selected' : '' ?>>Arquivadas</option>
                            <option value="WITH_ISSUES" <?= $campanhaStatus === 'WITH_ISSUES' ? 'selected' : '' ?>>Com problemas</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="periodo">Periodo</label>

                        <div class="radio-list">
                            <label class="radio-item">
                                <input type="radio" name="periodo" value="1" <?= $periodo === '1' ? 'checked' : '' ?>>
                                <span>Hoje</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="7" <?= $periodo === '7' ? 'checked' : '' ?>>
                                <span>Ultimos 7 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="15" <?= $periodo === '15' ? 'checked' : '' ?>>
                                <span>Ultimos 15 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="30" <?= $periodo === '30' ? 'checked' : '' ?>>
                                <span>Ultimos 30 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="90" <?= $periodo === '90' ? 'checked' : '' ?>>
                                <span>Ultimos 90 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="365" <?= $periodo === '365' ? 'checked' : '' ?>>
                                <span>Ultimos 365 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="custom" <?= $periodo === 'custom' ? 'checked' : '' ?>>
                                <span>Personalizado</span>
                            </label>
                        </div>
                    </div>

                    <div class="field">
                        <label for="data_inicio">Data inicio</label>
                        <input
                            type="date"
                            name="data_inicio"
                            id="data_inicio"
                            value="<?= htmlspecialchars((string) $dataInicio) ?>">
                    </div>

                    <div class="field">
                        <label for="data_fim">Data fim</label>
                        <input
                            type="date"
                            name="data_fim"
                            id="data_fim"
                            value="<?= htmlspecialchars((string) $dataFim) ?>">
                    </div>

                    <div class="sidebar-buttons">
                        <a href="<?= $paginaLimparFiltros ?>" class="btn btn-danger">Limpar filtros</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-bottom">
        <div class="sidebar-user-card">
            <a href="<?= htmlspecialchars(routeUrl('perfil')); ?>" class="settings-link <?= alp_nav_active(['perfil'], $requestPath) ?>">
                <?php if ($usuarioFoto !== ''): ?>
                    <img src="<?= htmlspecialchars($usuarioFoto); ?>" alt="Foto do usuario" class="settings-avatar">
                <?php else: ?>
                    <div class="settings-icon"><?= htmlspecialchars(alp_usuario_iniciais($usuarioNome)); ?></div>
                <?php endif; ?>

                <div class="settings-meta">
                    <strong><?= htmlspecialchars($usuarioNome !== '' ? $usuarioNome : 'Usuario'); ?></strong>
                    <span><?= htmlspecialchars($usuarioEmail !== '' ? $usuarioEmail : 'Editar perfil'); ?></span>
                </div>
            </a>

            <div class="sidebar-user-actions">
                <a href="<?= htmlspecialchars(routeUrl('perfil')); ?>" class="sidebar-user-link <?= alp_nav_active(['perfil'], $requestPath) ?>">
                    <i data-lucide="user-round"></i>
                    <span>Perfil</span>
                </a>

                <form method="POST" action="<?= htmlspecialchars(routeUrl('logout')); ?>" class="sidebar-logout-form">
                    <?= Csrf::field() ?>
                    <button type="submit" class="sidebar-user-link sidebar-user-link-danger">
                        <i data-lucide="log-out"></i>
                        <span>Sair</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
