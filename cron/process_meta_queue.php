<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "PROCESSADOR DE FILA INICIADO\n";

$db = new Database();
$conn = $db->connect();

$queue = new MetaSyncQueueService($conn);

$workerToken = uniqid('worker_', true);

$processados = 0;

while (true) {

    $job = $queue->getNextJob();

    if (!$job) {
        echo "Nenhum job pendente.\n";
        break;
    }

    try {

        echo "Processando job ID {$job['id']}...\n";

        $queue->processJob($job, $workerToken);

        echo "OK job {$job['id']}\n";

        $processados++;

    } catch (Throwable $e) {

        echo "ERRO job {$job['id']}: " . $e->getMessage() . "\n";
    }

    // evita loop infinito agressivo
    usleep(300000); // 0.3s
}

echo "FINALIZADO. Jobs processados: {$processados}\n";