<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

$db = new Database();
$conn = $db->connect();

$syncJob = new SyncJob($conn);

$stmt = $conn->query("
    SELECT empresa_id, id AS conta_id, cliente_id
    FROM contas_ads
    WHERE ativo = 1
      AND meta_account_id IS NOT NULL
");

$contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$inicio = date('Y-m-d', strtotime('-2 days'));
$fim    = date('Y-m-d');

foreach ($contas as $conta) {
    $syncJob->enqueueIfNotExists([
        'empresa_id' => (int) $conta['empresa_id'],
        'cliente_id' => (int) $conta['cliente_id'],
        'conta_id' => (int) $conta['conta_id'],
        'tipo' => 'insights',
        'origem' => 'cron',
        'prioridade' => 5,
        'force_sync' => 0,
        'janela_inicio' => $inicio,
        'janela_fim' => $fim,
    ]);
}