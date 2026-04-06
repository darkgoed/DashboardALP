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
    $_SESSION['flash_error'] = 'Token CSRF inválido.';
    header('Location: ' . routeUrl('empresas'));
    exit;
}

$db = new Database();
$conn = $db->connect();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function redirectBackToEdit(int $empresaId, array $errors = [], array $old = [], ?string $flashError = null): void
{
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = $old;

    if ($flashError !== null) {
        $_SESSION['flash_error'] = $flashError;
    }

    header('Location: ' . routeUrl('empresas/edit') . '?id=' . $empresaId);
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Empresa inválida.';
    header('Location: ' . routeUrl('empresas'));
    exit;
}

try {
    $empresaWriteService = new EmpresaWriteService($conn);
    $empresaWriteService->update($id, $_POST);
    $_SESSION['flash_success'] = 'Empresa atualizada com sucesso.';
    header('Location: ' . routeUrl('empresas/edit') . '?id=' . $id);
    exit;
} catch (FormValidationException $e) {
    redirectBackToEdit($id, $e->getErrors(), $e->getOld());
} catch (Throwable $e) {
    redirectBackToEdit($id, [], [], $e->getMessage());
}
