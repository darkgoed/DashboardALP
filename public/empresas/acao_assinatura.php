<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if (!method_exists('Auth', 'isPlatformRoot') || !Auth::isPlatformRoot()) {
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

$acao = $_POST['acao'] ?? '';
$empresaId = (int) ($_POST['empresa_id'] ?? 0);

if ($acao !== 'reativar_renovar' || $empresaId <= 0) {
    Flash::error('Requisição inválida.');
    header('Location: ' . routeUrl('empresas'));
    exit;
}

try {
    Flash::success($actionService->reativarRenovar($empresaId));
} catch (Throwable $e) {
    Flash::error('Erro ao reativar conta: ' . $e->getMessage());
}

header('Location: ' . routeUrl('empresas/edit') . '?id=' . $empresaId);
exit;
