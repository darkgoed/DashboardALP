<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . routeUrl('perfil'));
    exit;
}

if (!Csrf::isValid()) {
    Flash::error('Token CSRF invalido.');
    header('Location: ' . routeUrl('perfil'));
    exit;
}

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);
$usuarioId = (int) Auth::getUsuarioId();

try {
    $message = (new PerfilUpdateService($conn))->update($usuarioId, $_POST, $_FILES);
    Flash::success($message);
} catch (FormValidationException $e) {
    $_SESSION['old'] = $e->getOld();
    $_SESSION['errors'] = $e->getErrors();
} catch (Throwable $e) {
    Flash::error('Erro ao atualizar perfil: ' . $e->getMessage());
}

header('Location: ' . routeUrl('perfil'));
exit;
