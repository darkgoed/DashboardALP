<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$jobId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
try {
    $pageData = (new SyncJobViewService($conn, $empresaId))->getPageData($jobId);
    $job = $pageData['job'];
    $conta = $pageData['conta'];
    $cliente = $pageData['cliente'];
    $logsRelacionados = $pageData['logs_relacionados'];
} catch (Throwable $e) {
    Flash::error($e->getMessage());
    header('Location: ' . routeUrl('sync_logs'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Job - Dashboard ALP</title>

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

<body class="page page-sync-job-view">
    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 8v4"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Monitoramento</span>
                        <h1 class="page-title">Detalhes do job #<?= (int) $job['id'] ?></h1>
                        <p class="page-subtitle">
                            Consulte o historico, os parametros e os logs relacionados deste processamento.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('sync_logs')); ?>" class="btn btn-secondary">Voltar</a>

                    <?php if (SyncJobViewHelper::canRequeue($job)): ?>
                        <form method="POST" action="<?= htmlspecialchars(routeUrl('sync_requeue')); ?>" onsubmit="return confirm('Deseja reenfileirar este job?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
                            <button type="submit" class="btn btn-primary">Reprocessar job</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Status</span>
                    <strong><?= htmlspecialchars((string) $job['status']) ?></strong>
                    <small><span class="badge <?= SyncJobViewHelper::badgeClass((string) $job['status']) ?>"><?= htmlspecialchars((string) $job['status']) ?></span></small>
                </div>

                <div class="metric-card">
                    <span>Tipo</span>
                    <strong><?= htmlspecialchars((string) $job['tipo']) ?></strong>
                    <small>Categoria da sincronização</small>
                </div>

                <div class="metric-card">
                    <span>Tentativas</span>
                    <strong><?= (int) $job['tentativas'] ?> / <?= (int) $job['max_tentativas'] ?></strong>
                    <small>Execuções consumidas</small>
                </div>

                <div class="metric-card">
                    <span>Conta</span>
                    <strong><?= htmlspecialchars((string) ($conta['nome'] ?? ('#' . (int) ($job['conta_id'] ?? 0)))) ?></strong>
                    <small><?= htmlspecialchars((string) ($conta['meta_account_id'] ?? 'Sem Meta Account ID')) ?></small>
                </div>

                <div class="metric-card">
                    <span>Cliente</span>
                    <strong><?= htmlspecialchars((string) ($cliente['nome'] ?? ('#' . (int) ($job['cliente_id'] ?? 0)))) ?></strong>
                    <small><?= !empty($cliente['id']) ? 'Cliente vinculado ao job' : 'Sem cliente vinculado' ?></small>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Dados do job</h3>
                            <p class="panel-subtitle">Resumo operacional do processamento.</p>
                        </div>
                    </div>

                    <div class="data-list">
                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Mensagem</div>
                                <div class="data-item-meta">
                                    <span><?= htmlspecialchars((string) ($job['mensagem'] ?? '-')) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Origem e prioridade</div>
                                <div class="data-item-meta">
                                    <span><strong>Origem:</strong> <?= htmlspecialchars((string) ($job['origem'] ?? '-')) ?></span>
                                    <span><strong>Prioridade:</strong> <?= (int) ($job['prioridade'] ?? 0) ?></span>
                                    <span><strong>Force sync:</strong> <?= !empty($job['force_sync']) ? 'Sim' : 'Não' ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Contexto de destino</div>
                                <div class="data-item-meta">
                                    <span><strong>Cliente:</strong> <?= htmlspecialchars((string) ($cliente['nome'] ?? '-')) ?></span>
                                    <span><strong>Conta:</strong> <?= htmlspecialchars((string) ($conta['nome'] ?? '-')) ?></span>
                                    <span><strong>Worker:</strong> <?= htmlspecialchars((string) ($job['worker_token'] ?? '-')) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Janela</div>
                                <div class="data-item-meta">
                                    <span><strong>Início:</strong> <?= htmlspecialchars((string) ($job['janela_inicio'] ?? '-')) ?></span>
                                    <span><strong>Fim:</strong> <?= htmlspecialchars((string) ($job['janela_fim'] ?? '-')) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="data-item">
                            <div class="data-item-left">
                                <div class="data-item-title">Datas de execução</div>
                                <div class="data-item-meta">
                                    <span><strong>Criado:</strong> <?= htmlspecialchars(SyncJobViewHelper::formatDate($job['criado_em'] ?? null)) ?></span>
                                    <span><strong>Iniciado:</strong> <?= htmlspecialchars(SyncJobViewHelper::formatDate($job['iniciado_em'] ?? null)) ?></span>
                                    <span><strong>Finalizado:</strong> <?= htmlspecialchars(SyncJobViewHelper::formatDate($job['finalizado_em'] ?? null)) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h3>Parâmetros</h3>
                            <p class="panel-subtitle">JSON original do job.</p>
                        </div>
                    </div>

                    <pre class="code-block"><?= htmlspecialchars(SyncJobViewHelper::prettyJson($job['parametros'] ?? $job['parametros_json'] ?? null)) ?></pre>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Logs relacionados</h3>
                        <p class="panel-subtitle">Eventos vinculados ao sync_job_id deste job.</p>
                    </div>
                </div>

                <?php if (empty($logsRelacionados)): ?>
                    <div class="data-item-empty">Nenhum log relacionado encontrado.</div>
                <?php else: ?>
                    <div class="data-list">
                        <?php foreach ($logsRelacionados as $log): ?>
                            <div class="data-item">
                                <div class="data-item-left">
                                    <div class="data-item-title">
                                        <?= htmlspecialchars((string) $log['tipo']) ?>
                                        <span class="badge <?= SyncJobViewHelper::badgeClass((string) ($log['status'] ?? '')) ?>">
                                            <?= htmlspecialchars((string) ($log['status'] ?? '-')) ?>
                                        </span>
                                    </div>

                                    <div class="data-item-meta">
                                        <span><strong>Mensagem:</strong> <?= htmlspecialchars((string) ($log['mensagem'] ?? '-')) ?></span>
                                        <span><strong>Data:</strong> <?= htmlspecialchars(SyncJobViewHelper::formatDate($log['created_at'] ?? null)) ?></span>
                                    </div>

                                    <?php if (!empty($log['detalhes'])): ?>
                                        <pre class="code-block" style="margin-top: 12px;"><?= htmlspecialchars(SyncJobViewHelper::prettyJson($log['detalhes'])) ?></pre>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php Flash::renderScript(); ?>
    <script src="../assets/js/bootstrap.js"></script>
</body>

</html>
