<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$syncMonitoringService = new SyncMonitoringService($conn, $empresaId);
$pageData = $syncMonitoringService->getDashboardData();

$statusCount = $pageData['status_count'];
$concluidosHoje = $pageData['concluidos_hoje'];
$tiposFila = $pageData['tipos_fila'];
$origensFila = $pageData['origens_fila'];
$ultimaExecucao = $pageData['ultima_execucao'];
$falhas = $pageData['falhas'];
$jobs = $pageData['jobs'];
$totalJobs = $pageData['total_jobs'];

function syncDashboardBadgeClass(string $status): string
{
    return match ($status) {
        'concluido' => 'badge-green',
        'erro' => 'badge-red',
        'processando' => 'badge-yellow',
        'cancelado' => 'badge-muted',
        default => 'badge-blue',
    };
}

function syncDashboardFormatDate(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

function syncDashboardCanRequeue(array $job): bool
{
    return !str_contains((string) ($job['mensagem'] ?? ''), 'Reenfileirado manualmente no job #');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Sync - Dashboard ALP</title>

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

<body class="page page-sync-dashboard">
    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M4 19h16"></path>
                            <path d="M5 15l4-4 3 3 7-7"></path>
                            <path d="M15 7h4v4"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Monitoramento</span>
                        <h1 class="page-title">Saúde da sincronização</h1>
                        <p class="page-subtitle">
                            Acompanhe o volume da fila, o ritmo de processamento e os últimos erros operacionais das sincronizações Meta e Mercado Phone.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('sync_logs')); ?>" class="btn btn-secondary">Ver logs</a>
                    <a href="<?= htmlspecialchars(routeUrl('sync_dashboard')); ?>" class="btn btn-primary">Atualizar painel</a>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Total de jobs</span>
                    <strong><?= $totalJobs ?></strong>
                    <small>Volume total registrado</small>
                </div>

                <div class="metric-card">
                    <span>Pendentes</span>
                    <strong><?= $statusCount['pendente'] ?></strong>
                    <small>Aguardando processamento</small>
                </div>

                <div class="metric-card metric-accent">
                    <span>Processando</span>
                    <strong><?= $statusCount['processando'] ?></strong>
                    <small>Em execução agora</small>
                </div>

                <div class="metric-card metric-green">
                    <span>Concluídos hoje</span>
                    <strong><?= $concluidosHoje ?></strong>
                    <small>Finalizados hoje</small>
                </div>

                <div class="metric-card metric-red">
                    <span>Com erro</span>
                    <strong><?= $statusCount['erro'] ?></strong>
                    <small>Precisam de atenção</small>
                </div>

                <div class="metric-card">
                    <span>Última execução</span>
                    <strong><?= htmlspecialchars(syncDashboardFormatDate($ultimaExecucao)) ?></strong>
                    <small>Último job finalizado</small>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Composição por tipo</h3>
                            <p class="panel-subtitle">Distribuição atual da fila entre Meta, Mercado Phone e manutenção.</p>
                        </div>
                    </div>

                    <?php if (empty($tiposFila)): ?>
                        <div class="data-item-empty">Nenhum tipo registrado na fila.</div>
                    <?php else: ?>
                        <div class="data-list">
                            <?php foreach ($tiposFila as $tipoFila): ?>
                                <div class="data-item">
                                    <div class="data-item-left">
                                        <div class="data-item-title"><?= htmlspecialchars((string) ($tipoFila['tipo'] ?? '-')) ?></div>
                                        <div class="data-item-meta">
                                            <span><strong>Total:</strong> <?= (int) ($tipoFila['total'] ?? 0) ?></span>
                                        </div>
                                    </div>
                                    <div class="data-item-right">
                                        <a href="<?= htmlspecialchars(routeUrl('sync_logs') . '?tipo=' . rawurlencode((string) ($tipoFila['tipo'] ?? ''))); ?>" class="btn btn-secondary btn-sm">Filtrar</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Composição por origem</h3>
                            <p class="panel-subtitle">Separação entre disparos manuais, cron e demais origens registradas.</p>
                        </div>
                    </div>

                    <?php if (empty($origensFila)): ?>
                        <div class="data-item-empty">Nenhuma origem registrada na fila.</div>
                    <?php else: ?>
                        <div class="data-list">
                            <?php foreach ($origensFila as $origemFila): ?>
                                <div class="data-item">
                                    <div class="data-item-left">
                                        <div class="data-item-title"><?= htmlspecialchars((string) ($origemFila['origem'] ?? '-')) ?></div>
                                        <div class="data-item-meta">
                                            <span><strong>Total:</strong> <?= (int) ($origemFila['total'] ?? 0) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Comandos oficiais</h3>
                            <p class="panel-subtitle">Entrypoints de operação recomendados para cron e homologação.</p>
                        </div>
                    </div>

                    <div class="data-list">
                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Worker da fila</div>
                                <pre class="code-block">php cron/process_meta_sync_queue.php 20</pre>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Enfileirador Mercado Phone</div>
                                <pre class="code-block">php cron/sync_mercado_phone.php 100</pre>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Healthcheck operacional</div>
                                <pre class="code-block">php cron/check_operacao.php</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Críticos para liberar produção</h3>
                            <p class="panel-subtitle">Checklist mínimo antes de considerar a operação homologada.</p>
                        </div>
                    </div>

                    <div class="data-list">
                        <div class="data-item"><div class="data-item-left"><div class="data-item-title">Banco acessível para worker e cron</div></div></div>
                        <div class="data-item"><div class="data-item-left"><div class="data-item-title">SMTP global configurado para onboarding por convite</div></div></div>
                        <div class="data-item"><div class="data-item-left"><div class="data-item-title">SMTP por empresa configurado para relatórios</div></div></div>
                        <div class="data-item"><div class="data-item-left"><div class="data-item-title">Crons oficiais registrados no servidor</div></div></div>
                        <div class="data-item"><div class="data-item-left"><div class="data-item-title">Arquivos públicos de teste neutralizados e dump SQL removido do diretório de aplicação</div></div></div>
                    </div>
                </div>
            </section>

            <?php if ($statusCount['erro'] > 0): ?>
                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Atenção operacional</h3>
                            <p class="panel-subtitle">
                                Existem jobs com erro na fila. Revise as falhas abaixo ou acesse a listagem completa de logs.
                            </p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Últimas falhas</h3>
                        <p class="panel-subtitle">Jobs finalizados com erro recentemente.</p>
                    </div>

                    <a href="<?= htmlspecialchars(routeUrl('sync_logs') . '?status=erro'); ?>" class="btn btn-secondary btn-sm">Abrir erros</a>
                </div>

                <?php if (empty($falhas)): ?>
                    <div class="data-item-empty">Nenhuma falha recente encontrada.</div>
                <?php else: ?>
                    <div class="data-list">
                        <?php foreach ($falhas as $falha): ?>
                            <div class="data-item">
                                <div class="data-item-left">
                                    <div class="data-item-title">
                                        Job #<?= (int) $falha['id'] ?> · <?= htmlspecialchars((string) $falha['tipo']) ?>
                                    </div>

                                    <div class="data-item-meta">
                                        <span><strong>Conta:</strong> <?= htmlspecialchars((string) ($falha['conta_nome'] ?? '-')) ?></span>
                                        <span><strong>Data:</strong> <?= htmlspecialchars(syncDashboardFormatDate($falha['finalizado_em'] ?: $falha['criado_em'])) ?></span>
                                    </div>

                                    <div class="data-item-meta">
                                        <span><strong>Mensagem:</strong> <?= htmlspecialchars((string) ($falha['mensagem'] ?? '-')) ?></span>
                                    </div>
                                </div>

                                <div class="data-item-right">
                                    <a href="<?= htmlspecialchars(routeUrl('sync_job_view') . '?id=' . (int) $falha['id']); ?>" class="btn btn-secondary btn-sm">Ver</a>

                                    <?php if (syncDashboardCanRequeue($falha)): ?>
                                        <form method="POST" action="<?= htmlspecialchars(routeUrl('sync_requeue')); ?>" onsubmit="return confirm('Deseja reenfileirar este job?');">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $falha['id'] ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">Reprocessar</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Já reenfileirado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Últimos jobs</h3>
                        <p class="panel-subtitle">Visão resumida dos últimos processamentos disparados.</p>
                    </div>
                </div>

                <?php if (empty($jobs)): ?>
                    <div class="data-item-empty">Nenhum job encontrado para esta empresa.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Conta</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td>#<?= (int) $job['id'] ?></td>
                                    <td><?= htmlspecialchars((string) ($job['conta_nome'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) $job['tipo']) ?></td>
                                    <td>
                                        <span class="badge <?= syncDashboardBadgeClass((string) $job['status']) ?>">
                                            <?= htmlspecialchars((string) $job['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(syncDashboardFormatDate($job['finalizado_em'] ?: $job['criado_em'])) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars(routeUrl('sync_job_view') . '?id=' . (int) $job['id']); ?>" class="btn btn-secondary btn-sm">Detalhes</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php Flash::renderScript(); ?>
    <script src="../assets/js/bootstrap.js"></script>
</body>

</html>
