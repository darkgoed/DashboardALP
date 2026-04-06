<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if (!method_exists('Auth', 'isPlatformRoot') || !Auth::isPlatformRoot()) {
    header('Location: ' . routeUrl('dashboard'));
    exit;
}

$db = new Database();
$conn = $db->connect();

$empresaManagementService = new EmpresaManagementService($conn);
$pageData = $empresaManagementService->getIndexData($_GET);

$linhas = $pageData['linhas'];
$busca = $pageData['filters']['busca'];
$statusFiltro = $pageData['filters']['status'];
$planoFiltro = $pageData['filters']['plano'];
$totalEmpresas = (int) ($pageData['totais']['total_empresas'] ?? 0);
$totalAtivas = (int) ($pageData['totais']['total_ativas'] ?? 0);
$totalBloqueadas = (int) ($pageData['totais']['total_bloqueadas'] ?? 0);
$totalTrial = (int) ($pageData['totais']['total_trial'] ?? 0);
$totalRoot = (int) ($pageData['totais']['total_root'] ?? 0);

function formatarDataPainel(?string $data): string
{
    if (empty($data)) {
        return '—';
    }

    try {
        return (new DateTime($data))->format('d/m/Y');
    } catch (Throwable $e) {
        return '—';
    }
}

function badgeClasseStatusLicenca(string $status): string
{
    return match ($status) {
        'ativa' => 'badge badge-green',
        'trial' => 'badge badge-purple',
        'em_tolerancia' => 'badge badge-orange',
        'vencida' => 'badge badge-orange',
        'bloqueada' => 'badge badge-red',
        default => 'badge',
    };
}

function badgeClasseStatusEmpresa(string $status): string
{
    return match ($status) {
        'ativa' => 'badge badge-green',
        'inativa' => 'badge',
        'suspensa' => 'badge badge-red',
        'cancelada' => 'badge badge-red',
        default => 'badge',
    };
}

function percentualConsumo(int $usado, int $limite): int
{
    if ($limite <= 0) {
        return 0;
    }

    return min(100, (int) round(($usado / $limite) * 100));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresas - Dashboard ALP</title>

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

<body class="page page-insights">
    <div class="app">
        <?php require_once __DIR__ . '/../partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 21V7"></path>
                            <path d="M21 21V3"></path>
                            <path d="M9 21V11"></path>
                            <path d="M15 21v-6"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Administração</span>
                        <h1 class="page-title">Empresas</h1>
                        <p class="page-subtitle">
                            Gerencie as empresas do SaaS, acompanhe limites de usuários e contas,
                            situação da licença e status operacional em um único painel.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('empresas/create')); ?>" class="btn btn-primary">Nova empresa</a>
                    <a href="<?= htmlspecialchars(routeUrl('dashboard')); ?>" class="btn btn-secondary">Voltar ao dashboard</a>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Total de empresas</span>
                    <strong><?= number_format($totalEmpresas, 0, ',', '.') ?></strong>
                    <small>Empresas listadas após filtros</small>
                </div>

                <div class="metric-card metric-green">
                    <span>Operando</span>
                    <strong><?= number_format($totalAtivas, 0, ',', '.') ?></strong>
                    <small>Licença liberada para uso</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Bloqueadas</span>
                    <strong><?= number_format($totalBloqueadas, 0, ',', '.') ?></strong>
                    <small>Sem operação liberada</small>
                </div>

                <div class="metric-card">
                    <span>Empresas root</span>
                    <strong><?= number_format($totalRoot, 0, ',', '.') ?></strong>
                    <small>Contas com acesso especial</small>
                </div>
            </section>

            <section class="content-grid-wide">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Filtros</h3>
                            <p class="panel-subtitle">
                                Busque por nome, razão social, documento ou e-mail e refine por status e plano.
                            </p>
                        </div>
                    </div>

                    <form method="GET" class="form-stack">
                        <div class="content-grid" style="grid-template-columns: 1.6fr 1fr 1fr auto;">
                            <div class="field">
                                <label for="busca">Buscar empresa</label>
                                <input type="text" id="busca" name="busca"
                                    placeholder="Nome, razão social, documento ou e-mail"
                                    value="<?= htmlspecialchars($busca); ?>">
                            </div>

                            <div class="field field-select">
                                <label for="status">Status cadastral</label>
                                <select id="status" name="status">
                                    <option value="">Todos</option>
                                    <option value="ativa" <?= $statusFiltro === 'ativa' ? 'selected' : ''; ?>>Ativa
                                    </option>
                                    <option value="inativa" <?= $statusFiltro === 'inativa' ? 'selected' : ''; ?>>Inativa
                                    </option>
                                    <option value="suspensa" <?= $statusFiltro === 'suspensa' ? 'selected' : ''; ?>>
                                        Suspensa</option>
                                    <option value="cancelada" <?= $statusFiltro === 'cancelada' ? 'selected' : ''; ?>>
                                        Cancelada</option>
                                </select>
                            </div>

                            <div class="field field-select">
                                <label for="plano">Plano</label>
                                <select id="plano" name="plano">
                                    <option value="">Todos</option>
                                    <option value="trial" <?= $planoFiltro === 'trial' ? 'selected' : ''; ?>>Trial</option>
                                    <option value="basic" <?= $planoFiltro === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                    <option value="pro" <?= $planoFiltro === 'pro' ? 'selected' : ''; ?>>Pro</option>
                                    <option value="enterprise" <?= $planoFiltro === 'enterprise' ? 'selected' : ''; ?>>
                                        Enterprise</option>
                                </select>
                            </div>

                            <div class="form-actions" style="align-items: end;">
                                <button type="submit" class="btn btn-neutral">Filtrar</button>
                                <a href="<?= htmlspecialchars(routeUrl('empresas')); ?>" class="btn btn-danger">Limpar</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Empresas cadastradas</h3>
                            <p class="panel-subtitle">
                                Visualização consolidada com plano, licença, vencimento e consumo dos limites.
                            </p>
                        </div>

                        <div class="panel-actions">
                            <span class="badge badge-purple"><?= number_format($totalEmpresas, 0, ',', '.') ?>
                                registros</span>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Plano</th>
                                    <th>Status cadastro</th>
                                    <th>Status licença</th>
                                    <th>Vencimento</th>
                                    <th>Usuários</th>
                                    <th>Contas ads</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($linhas)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center;">Nenhuma empresa encontrada com os filtros
                                            atuais.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($linhas as $item): ?>
                                        <?php
                                        $empresa = $item['empresa'];
                                        $licenca = $item['licenca'];
                                        $consumo = $item['consumo'];

                                        $usuariosUsados = (int) ($consumo['usuarios']['usados'] ?? 0);
                                        $usuariosLimiteReal = (int) ($consumo['usuarios']['limite'] ?? 0);
                                        $usuariosDisponivel = (int) ($consumo['usuarios']['disponivel'] ?? 0);
                                        $usuariosPercentual = percentualConsumo($usuariosUsados, $usuariosLimiteReal);

                                        $contasUsadas = (int) ($consumo['contas_ads']['usadas'] ?? 0);
                                        $contasLimiteReal = (int) ($consumo['contas_ads']['limite'] ?? 0);
                                        $contasDisponivel = (int) ($consumo['contas_ads']['disponivel'] ?? 0);
                                        $contasPercentual = percentualConsumo($contasUsadas, $contasLimiteReal);
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="display:flex; flex-direction:column; gap:6px;">
                                                    <strong><?= htmlspecialchars($empresa['nome_fantasia'] ?? '—'); ?></strong>
                                                    <span><?= htmlspecialchars($empresa['email'] ?? 'Sem e-mail'); ?></span>
                                                    <span><?= htmlspecialchars($empresa['documento'] ?? 'Sem documento'); ?></span>

                                                    <?php if ((int) ($empresa['is_root'] ?? 0) === 1): ?>
                                                        <span class="badge badge-purple">Root</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="badge">
                                                    <?= htmlspecialchars($empresa['plano'] ?? '—'); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span
                                                    class="<?= badgeClasseStatusEmpresa((string) ($empresa['status'] ?? '')); ?>">
                                                    <?= htmlspecialchars($empresa['status'] ?? '—'); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div style="display:flex; flex-direction:column; gap:6px;">
                                                    <span
                                                        class="<?= badgeClasseStatusLicenca((string) ($licenca['status_assinatura'] ?? '')); ?>">
                                                        <?= htmlspecialchars($licenca['status_assinatura'] ?? '—'); ?>
                                                    </span>

                                                    <?php if (!empty($licenca['motivo']) && ($licenca['bloqueada'] ?? false)): ?>
                                                        <small><?= htmlspecialchars($licenca['motivo']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td>
                                                <div style="display:flex; flex-direction:column; gap:6px;">
                                                    <strong><?= formatarDataPainel($licenca['data_vencimento'] ?? null); ?></strong>
                                                    <small>
                                                        <?php
                                                        if (($empresa['is_root'] ?? 0) == 1) {
                                                            echo 'Sem restrição';
                                                        } elseif (($licenca['em_tolerancia'] ?? false) === true) {
                                                            echo 'Em tolerância';
                                                        } elseif (($licenca['bloqueada'] ?? false) === true) {
                                                            echo 'Bloqueada';
                                                        } else {
                                                            $dias = $licenca['dias_restantes'] ?? null;
                                                            echo $dias !== null ? $dias . ' dia(s) restantes' : '—';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </td>

                                            <td>
                                                <div style="display:flex; flex-direction:column; gap:8px; min-width:160px;">
                                                    <div style="display:flex; justify-content:space-between; gap:10px;">
                                                        <strong><?= $usuariosUsados; ?>/<?= $usuariosLimiteReal; ?></strong>
                                                        <small><?= $usuariosDisponivel; ?> livre(s)</small>
                                                    </div>

                                                    <div
                                                        style="width:100%; height:8px; border-radius:999px; overflow:hidden; background:var(--border-color, rgba(255,255,255,.08));">
                                                        <div
                                                            style="width: <?= $usuariosPercentual; ?>%; height:100%; border-radius:999px; background: var(--primary);">
                                                        </div>
                                                    </div>

                                                    <small>Usuários ativos</small>
                                                </div>
                                            </td>

                                            <td>
                                                <div style="display:flex; flex-direction:column; gap:8px; min-width:160px;">
                                                    <div style="display:flex; justify-content:space-between; gap:10px;">
                                                        <strong><?= $contasUsadas; ?>/<?= $contasLimiteReal; ?></strong>
                                                        <small><?= $contasDisponivel; ?> livre(s)</small>
                                                    </div>

                                                    <div
                                                        style="width:100%; height:8px; border-radius:999px; overflow:hidden; background:var(--border-color, rgba(255,255,255,.08));">
                                                        <div
                                                            style="width: <?= $contasPercentual; ?>%; height:100%; border-radius:999px; background: var(--primary);">
                                                        </div>
                                                    </div>

                                                    <small>Contas ativas</small>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="actions-grid-2x2">
                                                    <a href="<?= htmlspecialchars(routeUrl('empresas/edit') . '?id=' . (int) $empresa['id']); ?>"
                                                        class="btn btn-warning btn-sm" title="Editar">Editar</a>

                                                    <a href="<?= htmlspecialchars(routeUrl('empresas/view') . '?id=' . (int) $empresa['id']); ?>"
                                                        class="btn btn-neutral btn-sm" title="Visualizar">Ver</a>

                                                    <?php if ((int) ($empresa['is_root'] ?? 0) !== 1): ?>

                                                        <?php
                                                        $estaBloqueada = ($licenca['bloqueada'] ?? false) === true;
                                                        ?>

                                                        <form method="POST" action="<?= htmlspecialchars(routeUrl('empresas/toggle_bloqueio')); ?>" style="display:inline;">
                                                            <?= Csrf::field() ?>
                                                            <input type="hidden" name="id" value="<?= (int) $empresa['id']; ?>">
                                                            <button type="submit"
                                                                class="btn btn-sm <?= $estaBloqueada ? 'btn-success' : 'btn-danger'; ?>"
                                                                title="<?= $estaBloqueada ? 'Reativar empresa' : 'Bloquear empresa'; ?>">
                                                                <?= $estaBloqueada ? 'Reativar' : 'Bloquear'; ?>
                                                            </button>
                                                        </form>

                                                        <a href="<?= htmlspecialchars(routeUrl('empresas/delete') . '?id=' . (int) $empresa['id']); ?>"
                                                            class="btn btn-danger btn-sm" title="Excluir empresa">
                                                            Excluir
                                                        </a>

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
