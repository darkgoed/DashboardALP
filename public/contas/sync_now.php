<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . routeUrl('contas'));
    exit;
}

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();

if (!Csrf::isValid()) {
    Flash::error('Token CSRF inválido.');
    header('Location: ' . routeUrl('contas'));
    exit;
}

$contaId   = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($contaId <= 0) {
    Flash::error('Conta inválida para sincronização.');
    header('Location: ' . routeUrl('contas'));
    exit;
}

try {
    $service = new ContaSyncActionService($conn);
    Flash::success($service->enqueueSyncNow($empresaId, $contaId, 7));
} catch (Throwable $e) {
    Flash::error('Erro ao enfileirar sync: ' . $e->getMessage());
}

header('Location: ' . routeUrl('contas'));
exit;
