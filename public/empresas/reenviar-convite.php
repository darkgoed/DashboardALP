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
    Flash::error('Token CSRF invalido.');
    header('Location: ' . routeUrl('empresas'));
    exit;
}

$db = new Database();
$conn = $db->connect();
$actionService = new EmpresaAdminActionService($conn);

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

try {
    $result = $actionService->reenviarConvite($id, $_POST);
    $_SESSION['flash_convite_admin'] = $result['flash_convite_admin'];

    if (($result['flash_type'] ?? '') === 'success') {
        Flash::success((string) $result['flash_message']);
    } else {
        Flash::warning((string) $result['flash_message']);
    }
} catch (Throwable $e) {
    Flash::error('Não foi possível gerar o convite: ' . $e->getMessage());
}

header('Location: ' . routeUrl('empresas/edit') . '?id=' . $id);
exit;
