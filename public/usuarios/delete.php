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
    $message = $usuarioWriteService->delete($empresaId, $usuarioIdLogado, $id);
    Flash::success($message);
} catch (Throwable $e) {
    Flash::error('Erro ao excluir usuário: ' . $e->getMessage());
}

header('Location: ' . routeUrl('usuarios'));
exit;
