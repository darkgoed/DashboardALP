<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . routeUrl('sync_logs'));
    exit;
}

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();

if (!Csrf::isValid()) {
    Flash::error('Token CSRF inválido.');
    header('Location: ' . routeUrl('sync_logs'));
    exit;
}

$jobId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($jobId <= 0) {
    Flash::error('Job inválido para reprocessamento.');
    header('Location: ' . routeUrl('sync_logs'));
    exit;
}

try {
    $syncJobActionService = new SyncJobActionService($conn, $empresaId);
    $message = $syncJobActionService->requeue($jobId);

    if ($message === 'Já existe um job semelhante pendente ou processando.') {
        Flash::info($message);
    } else {
        Flash::success($message);
    }
} catch (Throwable $e) {
    Flash::error('Erro ao reenfileirar job: ' . $e->getMessage());
}

header('Location: ' . routeUrl('sync_logs'));
exit;
