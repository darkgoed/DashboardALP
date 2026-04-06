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

$db = new Database();
$conn = $db->connect();
$actionService = new EmpresaAdminActionService($conn);

if (!Csrf::isValid()) {
    Flash::error('Token CSRF inválido.');
    header('Location: ' . routeUrl('empresas'));
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

try {
    Flash::success($actionService->toggleBloqueio($id));
} catch (Throwable $e) {
    Flash::error($e->getMessage());
}

header('Location: ' . routeUrl('empresas'));
exit;
