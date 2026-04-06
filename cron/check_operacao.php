<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$jsonMode = in_array('--json', $argv, true);
$checks = [];
$hasError = false;
$hasWarning = false;

function envFilled(string $key): bool
{
    return trim((string) Env::get($key, '')) !== '';
}

function addCheck(array &$checks, string $status, string $label, string $message, bool &$hasError, bool &$hasWarning, bool $jsonMode): void
{
    $checks[] = [
        'status' => $status,
        'label' => $label,
        'message' => $message,
    ];

    if ($status === 'ERRO') {
        $hasError = true;
    } elseif ($status === 'WARN') {
        $hasWarning = true;
    }

    if (!$jsonMode) {
        echo str_pad('[' . $status . ']', 8) . ' ' . $label . ' - ' . $message . PHP_EOL;
    }
}

if (!$jsonMode) {
    echo 'CHECK OPERACIONAL - ' . date('Y-m-d H:i:s') . PHP_EOL;
    echo str_repeat('=', 72) . PHP_EOL;
}

$requiredScripts = [
    'Worker oficial' => BASE_PATH . '/cron/process_meta_sync_queue.php',
    'Enfileirador Mercado Phone' => BASE_PATH . '/cron/sync_mercado_phone.php',
    'Enqueue Meta insights' => BASE_PATH . '/cron/enqueue_meta_insights.php',
    'Enqueue Meta estrutura' => BASE_PATH . '/cron/enqueue_meta_structure.php',
    'Envio de relatorios programados' => BASE_PATH . '/cron/process_relatorios_programados.php',
    'Manutencao Meta' => BASE_PATH . '/cron/meta_maintenance.php',
    'Healthcheck operacional' => BASE_PATH . '/cron/check_operacao.php',
];

foreach ($requiredScripts as $label => $path) {
    if (is_file($path)) {
        addCheck($checks, 'OK', $label, $path, $hasError, $hasWarning, $jsonMode);
    } else {
        addCheck($checks, 'ERRO', $label, 'Arquivo nao encontrado: ' . $path, $hasError, $hasWarning, $jsonMode);
    }
}

$legacyScripts = [
    BASE_PATH . '/public/process_queue.php',
    BASE_PATH . '/cron/process_meta_queue.php',
    BASE_PATH . '/cron/sync_meta.php',
    BASE_PATH . '/cron/enqueue_meta_reconcile.php',
];

foreach ($legacyScripts as $legacyPath) {
    if (is_file($legacyPath)) {
        addCheck($checks, 'WARN', 'Script legado', 'Nao usar como entrypoint oficial: ' . $legacyPath, $hasError, $hasWarning, $jsonMode);
    }
}

$smtpGlobalKeys = [
    'MAIL_SMTP_HOST',
    'MAIL_SMTP_PORT',
    'MAIL_SMTP_USER',
    'MAIL_SMTP_PASS',
    'MAIL_FROM_EMAIL',
];
$smtpMissing = array_values(array_filter($smtpGlobalKeys, static fn(string $key): bool => !envFilled($key)));

if ($smtpMissing === []) {
    addCheck($checks, 'OK', 'SMTP global', 'Configuracao minima presente no .env.', $hasError, $hasWarning, $jsonMode);
} else {
    addCheck($checks, 'WARN', 'SMTP global', 'Variaveis ausentes: ' . implode(', ', $smtpMissing), $hasError, $hasWarning, $jsonMode);
}

$metaKeys = ['META_APP_ID', 'META_APP_SECRET', 'META_REDIRECT_URI'];
$metaMissing = array_values(array_filter($metaKeys, static fn(string $key): bool => !envFilled($key)));

if ($metaMissing === []) {
    addCheck($checks, 'OK', 'Meta OAuth', 'Configuracao principal presente no .env.', $hasError, $hasWarning, $jsonMode);
} else {
    addCheck($checks, 'WARN', 'Meta OAuth', 'Variaveis ausentes: ' . implode(', ', $metaMissing), $hasError, $hasWarning, $jsonMode);
}

$testFiles = [
    BASE_PATH . '/public/test_env.php',
    BASE_PATH . '/public/test_email.php',
    BASE_PATH . '/public/test_queue.php',
];

foreach ($testFiles as $testFile) {
    if (!is_file($testFile)) {
        addCheck($checks, 'WARN', 'Arquivo de teste', 'Nao encontrado: ' . $testFile, $hasError, $hasWarning, $jsonMode);
        continue;
    }

    $content = (string) file_get_contents($testFile);
    if (str_contains($content, 'http_response_code(404)')) {
        addCheck($checks, 'OK', 'Arquivo de teste', basename($testFile) . ' neutralizado com 404.', $hasError, $hasWarning, $jsonMode);
    } else {
        addCheck($checks, 'ERRO', 'Arquivo de teste', basename($testFile) . ' ainda nao esta neutralizado.', $hasError, $hasWarning, $jsonMode);
    }
}

$phpMyAdminPath = BASE_PATH . '/public/phpmyadmin';
if (is_link($phpMyAdminPath) || is_dir($phpMyAdminPath)) {
    addCheck($checks, 'ERRO', 'phpMyAdmin publico', 'Remover do docroot ou bloquear no servidor web: ' . $phpMyAdminPath, $hasError, $hasWarning, $jsonMode);
} else {
    addCheck($checks, 'OK', 'phpMyAdmin publico', 'Nenhuma exposicao de phpMyAdmin encontrada no docroot.', $hasError, $hasWarning, $jsonMode);
}

$uploadsPath = BASE_PATH . '/public/uploads';
$uploadsHtaccessPath = $uploadsPath . '/.htaccess';
if (is_dir($uploadsPath) && !is_file($uploadsHtaccessPath)) {
    addCheck($checks, 'WARN', 'Uploads publicos', 'Diretorio existe sem .htaccess dedicado: ' . $uploadsHtaccessPath, $hasError, $hasWarning, $jsonMode);
} elseif (is_dir($uploadsPath)) {
    $uploadsHtaccess = (string) file_get_contents($uploadsHtaccessPath);
    if (stripos($uploadsHtaccess, 'Require all denied') !== false || stripos($uploadsHtaccess, 'Deny from all') !== false) {
        addCheck($checks, 'OK', 'Uploads publicos', 'Execucao de scripts bloqueada em public/uploads.', $hasError, $hasWarning, $jsonMode);
    } else {
        addCheck($checks, 'WARN', 'Uploads publicos', 'Validar bloqueio de execucao em ' . $uploadsHtaccessPath, $hasError, $hasWarning, $jsonMode);
    }
}

$rootDumpPath = BASE_PATH . '/u232253492_dashboard_alp.sql';
$archivedDumpPath = BASE_PATH . '/storage/backups/legacy/u232253492_dashboard_alp.sql';

if (is_file($rootDumpPath)) {
    addCheck($checks, 'WARN', 'Dump SQL', 'Arquivo ainda presente na raiz do projeto: ' . $rootDumpPath, $hasError, $hasWarning, $jsonMode);
} elseif (is_file($archivedDumpPath)) {
    addCheck($checks, 'WARN', 'Dump SQL', 'Arquivo arquivado fora da raiz operacional: ' . $archivedDumpPath, $hasError, $hasWarning, $jsonMode);
} else {
    addCheck($checks, 'OK', 'Dump SQL', 'Nenhum dump SQL encontrado na raiz operacional.', $hasError, $hasWarning, $jsonMode);
}

try {
    $db = new Database();
    $conn = $db->connect();
    addCheck($checks, 'OK', 'Banco', 'Conexao PDO estabelecida com sucesso.', $hasError, $hasWarning, $jsonMode);

    $stmt = $conn->query("SELECT status, COUNT(*) AS total FROM sync_jobs GROUP BY status");
    $statusMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statusMap[] = ($row['status'] ?? '-') . ':' . (int) ($row['total'] ?? 0);
    }
    addCheck($checks, 'OK', 'Fila sync_jobs', $statusMap !== [] ? implode(' | ', $statusMap) : 'Sem jobs registrados.', $hasError, $hasWarning, $jsonMode);

    $stmt = $conn->query("
        SELECT COUNT(*) AS total
        FROM mercado_phone_integracoes
        WHERE ativo = 1
          AND api_token IS NOT NULL
          AND TRIM(api_token) <> ''
    ");
    $integracoesAtivas = (int) $stmt->fetchColumn();
    addCheck($checks, 'OK', 'Mercado Phone', 'Integracoes ativas com token: ' . $integracoesAtivas, $hasError, $hasWarning, $jsonMode);

    $stmt = $conn->query("
        SELECT COUNT(*) AS total
        FROM canais_email
        WHERE TRIM(COALESCE(smtp_host, '')) <> ''
          AND TRIM(COALESCE(smtp_user, '')) <> ''
          AND TRIM(COALESCE(smtp_pass, '')) <> ''
    ");
    $smtpEmpresas = (int) $stmt->fetchColumn();
    addCheck($checks, 'OK', 'SMTP por empresa', 'Empresas com canal minimamente configurado: ' . $smtpEmpresas, $hasError, $hasWarning, $jsonMode);
} catch (Throwable $e) {
    addCheck($checks, 'ERRO', 'Banco', $e->getMessage(), $hasError, $hasWarning, $jsonMode);
}

$result = $hasError ? 'ERRO' : ($hasWarning ? 'ALERTA' : 'OK');
$exitCode = $hasError ? 1 : ($hasWarning ? 2 : 0);

if ($jsonMode) {
    echo json_encode([
        'generated_at' => date('c'),
        'result' => $result,
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($exitCode);
}

echo str_repeat('-', 72) . PHP_EOL;
echo 'RESULTADO FINAL: ' . $result . PHP_EOL;
exit($exitCode);
