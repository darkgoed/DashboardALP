<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

$db = new Database();
$conn = $db->connect();

$queue = new MetaSyncQueueService($conn);
$workerToken = bin2hex(random_bytes(16));

$maxJobs = 20;
$processados = 0;

while ($processados < $maxJobs) {
    $job = $queue->getNextJob();

    if (!$job) {
        echo "Sem jobs.\n";
        break;
    }

    echo "Processando {$job['id']}...\n";

    try {
        $queue->processJob($job, $workerToken);
        echo "OK\n";
    } catch (Throwable $e) {
        echo "ERRO: " . $e->getMessage() . "\n";
    }

    $processados++;
}

echo "FINALIZADO: {$processados}\n";