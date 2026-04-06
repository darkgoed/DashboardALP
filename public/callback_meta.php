<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();
$service = new MetaCallbackService($conn, $empresaId, $usuarioId);
$clienteId = 0;

try {
    $result = $service->handle($_GET, $_SESSION);
} catch (Throwable $e) {
    $result = $service->buildErrorRedirect($e, $_GET, $_SESSION, $clienteId);
}

if (($result['flash_success'] ?? '') !== '') {
    Flash::success((string) $result['flash_success']);
}

if (($result['flash_error'] ?? '') !== '') {
    Flash::error((string) $result['flash_error']);
}

header('Location: ' . $result['url']);
exit;
