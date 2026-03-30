<?php

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}


$paginaAtual = basename($_SERVER['PHP_SELF']);

if (!function_exists('alp_nav_active')) {
    function alp_nav_active(array $pages, string $currentPage): string
    {
        return in_array($currentPage, $pages, true) ? 'active' : '';
    }
}

$mostrarFiltros = isset($mostrarFiltrosSidebar) && $mostrarFiltrosSidebar === true;

$contas = $contas ?? [];
$campanhas = $campanhas ?? [];
$contaId = $contaId ?? '';

$campanha_id = $campanhaId ?? '';
$data_inicio = $dataInicio ?? '';
$data_fim = $dataFim ?? '';
$periodo = $periodo ?? '30';
$isConfiguracoes = $paginaAtual === 'configuracoes.php';
$isConfiguracoesGroup = in_array($paginaAtual, [
    'configuracoes.php',
    'clientes.php',
    'contas.php',
    'integracoes_meta.php'
], true);
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
            <div class="section-title">NAVEGAÇÃO</div>

            <a href="dashboard.php" class="nav-link <?= alp_nav_active(['dashboard.php'], $paginaAtual) ?>">
                <i data-lucide="layout-dashboard"></i>
                <span>Dashboard</span>
            </a>

            <a href="campanhas.php" class="nav-link <?= alp_nav_active(['campanhas.php'], $paginaAtual) ?>">
                <i data-lucide="megaphone"></i>
                <span>Campanhas</span>
            </a>

            <a href="relatorios.php" class="nav-link">
                <i data-lucide="scroll-text"></i>
                <span>Relatórios</span>
            </a>

            <a href="insights.php" class="nav-link">
                <i data-lucide="sparkles"></i>
                <span>Insights</span>
            </a>

            <a href="metricas.php" class="nav-link">
                <i data-lucide="sparkles"></i>
                <span>Métricas</span>
            </a>

            <div class="nav-group <?= $isConfiguracoesGroup ? 'open' : '' ?>">
                <button
                    type="button"
                    class="nav-link nav-link-group <?= $isConfiguracoesGroup ? 'active' : '' ?>"
                    data-nav-toggle="configuracoes"
                    aria-expanded="<?= $isConfiguracoesGroup ? 'true' : 'false' ?>">

                    <span class="nav-link-main">
                        <i data-lucide="settings-2"></i>
                        <span>Configurações</span>
                    </span>

                    <i data-lucide="chevron-down" class="nav-group-chevron"></i>
                </button>

                <div class="nav-submenu" id="nav-submenu-configuracoes">
                    <a href="clientes.php" class="nav-sublink <?= alp_nav_active(['clientes.php'], $paginaAtual) ?>">
                        <i data-lucide="users"></i>
                        <span>Clientes</span>
                    </a>

                    <a href="contas.php" class="nav-sublink <?= alp_nav_active(['contas.php'], $paginaAtual) ?>">
                        <i data-lucide="briefcase-business"></i>
                        <span>Contas</span>
                    </a>

                    <a href="integracoes_meta.php" class="nav-sublink <?= alp_nav_active(['integracoes_meta.php'], $paginaAtual) ?>">
                        <i data-lucide="plug-zap"></i>
                        <span>Integrações Meta</span>
                    </a>

                    <a href="conexoes.php" class="nav-sublink <?= alp_nav_active(['conexoes.php'], $paginaAtual) ?>">
                        <i data-lucide="blocks"></i>
                        <span>Conexões</span>
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
                                    value="<?= htmlspecialchars($conta['id']) ?>"
                                    <?= ((string)$contaId === (string)$conta['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($conta['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field field-select">
                        <label for="campanha_id">Campanha</label>
                        <select name="campanha_id" id="campanha_id">
                            <option value="">Todas</option>
                            <?php foreach ($campanhas as $campanha): ?>
                                <option
                                    value="<?= htmlspecialchars($campanha['id']) ?>"
                                    <?= ((string)$campanha_id === (string)$campanha['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($campanha['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="periodo">Período</label>

                        <div class="radio-list">
                            <label class="radio-item">
                                <input type="radio" name="periodo" value="1" <?= $periodo === '1' ? 'checked' : '' ?>>
                                <span>Hoje</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="7" <?= $periodo === '7' ? 'checked' : '' ?>>
                                <span>Últimos 7 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="15" <?= $periodo === '15' ? 'checked' : '' ?>>
                                <span>Últimos 15 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="30" <?= $periodo === '30' ? 'checked' : '' ?>>
                                <span>Últimos 30 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="90" <?= $periodo === '90' ? 'checked' : '' ?>>
                                <span>Últimos 90 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="365" <?= $periodo === '365' ? 'checked' : '' ?>>
                                <span>Últimos 365 dias</span>
                            </label>

                            <label class="radio-item">
                                <input type="radio" name="periodo" value="custom" <?= $periodo === 'custom' ? 'checked' : '' ?>>
                                <span>Personalizado</span>
                            </label>
                        </div>
                    </div>

                    <div class="field">
                        <label for="data_inicio">Data início</label>
                        <input
                            type="date"
                            name="data_inicio"
                            id="data_inicio"
                            value="<?= htmlspecialchars($data_inicio) ?>">
                    </div>

                    <div class="field">
                        <label for="data_fim">Data fim</label>
                        <input
                            type="date"
                            name="data_fim"
                            id="data_fim"
                            value="<?= htmlspecialchars($data_fim) ?>">
                    </div>

                    <div class="sidebar-buttons">
                        <a href="dashboard.php" class="btn btn-danger">Limpar filtros</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-bottom">
        <a href="#" class="settings-link">
            <div class="settings-icon">A</div>
            <div>
                <strong>Editar perfil</strong>
                <span>Conta do usuário</span>
            </div>
        </a>
    </div>
</aside>