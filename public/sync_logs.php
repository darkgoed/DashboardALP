<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$syncMonitoringService = new SyncMonitoringService($conn, $empresaId);
$pageData = $syncMonitoringService->getLogsData($_GET);

$status = $pageData['filters']['status'];
$tipo = $pageData['filters']['tipo'];
$contaId = $pageData['filters']['conta_id'];
$contas = $pageData['contas'];
$jobs = $pageData['jobs'];

function syncLogsBadgeClass(string $status): string
{
    return match ($status) {
        'concluido' => 'badge-green',
        'erro' => 'badge-red',
        'processando' => 'badge-yellow',
        'cancelado' => 'badge-muted',
        default => 'badge-blue',
    };
}

function syncLogsFormatDate(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

function syncLogsCanRequeue(array $job): bool
{
    if (!in_array($job['status'] ?? '', ['erro', 'cancelado'], true)) {
        return false;
    }

    return !str_contains((string) ($job['mensagem'] ?? ''), 'Reenfileirado manualmente no job #');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Sync - Dashboard ALP</title>

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

<body class="page page-sync-logs">
    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 12h4l2-6 4 12 2-6h6"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Monitoramento</span>
                        <h1 class="page-title">Logs de sincronização</h1>
                        <p class="page-subtitle">
                            Filtre, inspecione e reprocessse jobs da fila de sincronização Meta.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('sync_dashboard')); ?>" class="btn btn-secondary">Visão geral</a>
                    <a href="<?= htmlspecialchars(routeUrl('sync_logs')); ?>" class="btn btn-primary">Limpar filtros</a>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Filtros</h3>
                        <p class="panel-subtitle">Refine a listagem por status, tipo e conta.</p>
                    </div>
                </div>

                <form method="GET" class="form-inline">
                    <div class="field field-select">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Todos</option>
                            <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="processando" <?= $status === 'processando' ? 'selected' : '' ?>>Processando</option>
                            <option value="concluido" <?= $status === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                            <option value="erro" <?= $status === 'erro' ? 'selected' : '' ?>>Erro</option>
                            <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>

                    <div class="field field-select">
                        <label for="tipo">Tipo</label>
                        <select name="tipo" id="tipo">
                            <option value="">Todos</option>
                            <option value="estrutura" <?= $tipo === 'estrutura' ? 'selected' : '' ?>>Estrutura</option>
                            <option value="insights" <?= $tipo === 'insights' ? 'selected' : '' ?>>Insights</option>
                            <option value="reconciliacao" <?= $tipo === 'reconciliacao' ? 'selected' : '' ?>>Reconciliação</option>
                            <option value="completo" <?= $tipo === 'completo' ? 'selected' : '' ?>>Completo</option>
                            <option value="manutencao" <?= $tipo === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                            <option value="mercado_phone" <?= $tipo === 'mercado_phone' ? 'selected' : '' ?>>Mercado Phone</option>
                        </select>
                    </div>

                    <div class="field field-select">
                        <label for="conta_id">Conta</label>
                        <select name="conta_id" id="conta_id">
                            <option value="">Todas</option>
                            <?php foreach ($contas as $conta): ?>
                                <option value="<?= (int) $conta['id'] ?>" <?= $contaId === (int) $conta['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $conta['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Jobs recentes</h3>
                        <p class="panel-subtitle">Últimos 100 registros conforme os filtros aplicados.</p>
                    </div>
                </div>

                <?php if (empty($jobs)): ?>
                    <div class="data-item-empty">Nenhum job encontrado com os filtros atuais.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Conta</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Mensagem</th>
                                <th>Datas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td>#<?= (int) $job['id'] ?></td>

                                    <td>
                                        <?= htmlspecialchars((string) ($job['conta_nome'] ?? '-')) ?><br>
                                        <small><?= htmlspecialchars((string) ($job['meta_account_id'] ?? '-')) ?></small>
                                    </td>

                                    <td><?= htmlspecialchars((string) $job['tipo']) ?></td>

                                    <td>
                                        <span class="badge <?= syncLogsBadgeClass((string) $job['status']) ?>">
                                            <?= htmlspecialchars((string) $job['status']) ?>
                                        </span>
                                    </td>

                                    <td><?= htmlspecialchars((string) ($job['mensagem'] ?? '-')) ?></td>

                                    <td>
                                        <strong>Criado:</strong> <?= htmlspecialchars(syncLogsFormatDate($job['criado_em'] ?? null)) ?><br>
                                        <strong>Finalizado:</strong> <?= htmlspecialchars(syncLogsFormatDate($job['finalizado_em'] ?? null)) ?>
                                    </td>

                                    <td>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                            <a href="<?= htmlspecialchars(routeUrl('sync_job_view') . '?id=' . (int) $job['id']); ?>" class="btn btn-secondary btn-sm">
                                                Ver detalhes
                                            </a>

                                            <?php if (syncLogsCanRequeue($job)): ?>
                                                <form method="POST" action="<?= htmlspecialchars(routeUrl('sync_requeue')); ?>" onsubmit="return confirm('Deseja reenfileirar este job?');">
                                                    <?= Csrf::field() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm">Reprocessar</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge badge-muted">Sem ação</span>
                                            <?php endif; ?>
                                        </div>
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
