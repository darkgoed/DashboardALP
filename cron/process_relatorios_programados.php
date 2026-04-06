<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$limit = isset($argv[1]) ? max(1, min(200, (int) $argv[1])) : 50;

$db = new Database();
$conn = $db->connect();
(new RelatorioProgramacao($conn))->ensureTable();

$stmt = $conn->query("
    SELECT DISTINCT empresa_id
    FROM relatorios_programacoes
    WHERE ativo = 1
      AND proximo_envio_em <= NOW()
    ORDER BY empresa_id ASC
");

$empresaIds = array_map(
    static fn(array $row): int => (int) ($row['empresa_id'] ?? 0),
    $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
);

$totalProcessed = 0;
$totalSuccess = 0;
$totalErrors = 0;

echo 'PROCESSADOR RELATORIOS PROGRAMADOS - ' . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('=', 72) . PHP_EOL;

foreach ($empresaIds as $empresaId) {
    if ($empresaId <= 0) {
        continue;
    }

    $service = new RelatorioProgramacaoService($conn, $empresaId);
    $result = $service->processDue(new DateTimeImmutable('now'), $limit);

    $totalProcessed += (int) $result['processed'];
    $totalSuccess += (int) $result['success'];
    $totalErrors += (int) $result['errors'];

    echo 'Empresa ' . $empresaId
        . ' | processados: ' . (int) $result['processed']
        . ' | sucesso: ' . (int) $result['success']
        . ' | erro: ' . (int) $result['errors']
        . PHP_EOL;

    foreach ((array) $result['messages'] as $message) {
        echo '  - ' . $message . PHP_EOL;
    }
}

echo str_repeat('-', 72) . PHP_EOL;
echo 'TOTAL | processados: ' . $totalProcessed
    . ' | sucesso: ' . $totalSuccess
    . ' | erro: ' . $totalErrors
    . PHP_EOL;

exit($totalErrors > 0 ? 1 : 0);
