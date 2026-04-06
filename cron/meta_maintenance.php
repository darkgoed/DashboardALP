<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script deve ser executado via CLI.\n");
}

try {
    $db = new Database();
    $conn = $db->connect();

    $sync = new MetaSyncService($conn);
    $resultado = $sync->syncManutencao();

    echo "MANUTENCAO FINALIZADA\n";
    print_r($resultado);
} catch (Throwable $e) {
    echo "ERRO NA MANUTENCAO: " . $e->getMessage() . "\n";
    exit(1);
}