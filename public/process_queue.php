<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

// =========================
// CONFIG
// =========================

// Proteção simples (opcional mas recomendado)
$token = $_GET['token'] ?? null;
$expectedToken = 'alp_secret_123'; // troca isso depois

if ($token !== $expectedToken) {
    http_response_code(403);
    echo 'Acesso negado';
    exit;
}

// Quantidade de jobs por execução
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 1;
$limit = max(1, min($limit, 20));

// =========================
// INIT
// =========================

$db = new Database();
$conn = $db->connect();

$queue = new MetaSyncQueueService($conn);

$workerToken = bin2hex(random_bytes(8));

echo '<pre>';
echo "WORKER: {$workerToken}\n";
echo "LIMIT: {$limit}\n\n";

$processed = 0;

// =========================
// LOOP
// =========================

for ($i = 0; $i < $limit; $i++) {

    try {
        $job = $queue->getNextJob();

        if (!$job) {
            echo "Sem jobs.\n";
            break;
        }

        echo "Processando job #{$job['id']} | tipo: {$job['tipo']}\n";

        $queue->processJob($job, $workerToken);

        echo "OK\n\n";

        $processed++;

    } catch (Throwable $e) {
        echo "ERRO: " . $e->getMessage() . "\n\n";
    }
}

echo "FINALIZADO: {$processed}\n";
echo '</pre>';