<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if (!Auth::isPlatformRoot()) {
    header('Location: ' . routeUrl('dashboard'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . routeUrl('empresas'));
    exit;
}

if (!Csrf::isValid()) {
    Flash::error('Token CSRF inválido.');
    header('Location: ' . routeUrl('empresas'));
    exit;
}

$db = new Database();
$conn = $db->connect();
$actionService = new EmpresaAdminActionService($conn);

$empresaId = (int) ($_POST['empresa_id'] ?? 0);

try {
    Flash::success($actionService->reativar($empresaId));
} catch (Throwable $e) {
    Flash::error($e->getMessage());
}

header('Location: ' . routeUrl('empresas/edit') . '?id=' . $empresaId);
exit;
