<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$maxJobs = isset($argv[1]) ? (int) $argv[1] : 20;
$maxJobs = max(1, min($maxJobs, 100));
$tipo = isset($argv[2]) && $argv[2] !== '' ? (string) $argv[2] : null;
$stuckMinutes = isset($argv[3]) ? (int) $argv[3] : 30;
$stuckMinutes = max(5, min($stuckMinutes, 240));

try {
    $db = new Database();
    $conn = $db->connect();

    $queue = new MetaSyncQueueService($conn);
    $syncJobModel = new SyncJob($conn);
    $workerToken = bin2hex(random_bytes(16));
    $processados = 0;
    $falhas = 0;
    $reencaminhados = $syncJobModel->requeueStuckJobs($stuckMinutes);

    echo "WORKER: {$workerToken}\n";
    echo "TIPO: " . ($tipo ?? 'todos') . "\n";
    echo "LIMIT: {$maxJobs}\n";
    echo "REQUEUE_STUCK: {$reencaminhados}\n";

    while ($processados < $maxJobs) {
        $job = $queue->claimNextJob($workerToken, $tipo);

        if (!$job) {
            echo "Sem jobs.\n";
            break;
        }

        echo "Processando {$job['id']} ({$job['tipo']})...\n";

        try {
            $queue->processJob($job, $workerToken);
            echo "OK\n";
        } catch (Throwable $e) {
            $falhas++;
            echo "ERRO: " . $e->getMessage() . "\n";
        }

        $processados++;
    }

    echo "FINALIZADO: {$processados} | FALHAS: {$falhas}\n";
    exit($falhas > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO AO INICIAR WORKER: " . $e->getMessage() . "\n");
    exit(1);
}
