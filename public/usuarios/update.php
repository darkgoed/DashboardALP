<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);
$usuarioIdLogado = Auth::getUsuarioId();
$empresaId = Auth::getEmpresaId();

if (!Permissao::podeGerenciarUsuarios($conn, $usuarioIdLogado, $empresaId)) {
    http_response_code(403);
    exit('Acesso negado.');
}

if (!Csrf::isValid()) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

try {
    $usuarioWriteService = new UsuarioWriteService($conn);
    $message = $usuarioWriteService->update($empresaId, $_POST);
    Flash::success($message);
} catch (Throwable $e) {
    Flash::error('Erro ao atualizar usuário: ' . $e->getMessage());
    header('Location: ' . routeUrl('usuarios/edit') . '?id=' . $id);
    exit;
}

header('Location: ' . routeUrl('usuarios'));
exit;
