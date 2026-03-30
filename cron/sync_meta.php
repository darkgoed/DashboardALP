<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script deve ser executado via CLI.\n");
}

try {
    $empresaId = null;
    $contaId = null;

    foreach ($argv as $arg) {
        if (strpos($arg, '--empresa_id=') === 0) {
            $empresaId = (int) substr($arg, 13);
        }

        if (strpos($arg, '--conta_id=') === 0) {
            $contaId = (int) substr($arg, 11);
        }
    }

    $db = new Database();
    $conn = $db->connect();

    $sync = new MetaSyncService($conn);
    $resultados = $sync->syncAll($empresaId, $contaId);

    echo "SYNC META FINALIZADO\n";
    print_r($resultados);
} catch (Throwable $e) {
    echo "ERRO NO SYNC: " . $e->getMessage() . "\n";
    exit(1);
}