<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$limit = isset($argv[1]) ? (int) $argv[1] : 100;
$empresaId = isset($argv[2]) ? (int) $argv[2] : null;

try {
    $db = new Database();
    $conn = $db->connect();

    $service = new MercadoPhoneQueueService($conn);
    $resultado = $service->enqueueActiveIntegrations($empresaId && $empresaId > 0 ? $empresaId : null, $limit);

    echo '[' . date('Y-m-d H:i:s') . '] MERCADO PHONE ENQUEUE' . "\n";
    echo 'Integracoes lidas: ' . (int) $resultado['integracoes_lidas'] . "\n";
    echo 'Jobs enfileirados: ' . (int) $resultado['jobs_enfileirados'] . "\n";
    echo 'Jobs ignorados: ' . (int) $resultado['jobs_ignorados'] . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO AO ENFILEIRAR MERCADO PHONE: " . $e->getMessage() . "\n");
    exit(1);
}
