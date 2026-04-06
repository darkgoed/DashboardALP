<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();
$db = new Database();
$conn = $db->connect();

$usuarioIdLogado = Auth::getUsuarioId();
$empresaId = Auth::getEmpresaId();

EmpresaAccessGuard::assertPodeOperar($conn);

if (!Permissao::podeGerenciarUsuarios($conn, $usuarioIdLogado, $empresaId)) {
    http_response_code(403);
    exit('Acesso negado.');
}

if (!Csrf::isValid()) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

try {
    $usuarioWriteService = new UsuarioWriteService($conn);
    $message = $usuarioWriteService->create($empresaId, $_POST);
    Flash::success($message);
} catch (Throwable $e) {
    Flash::error('Erro ao criar usuário: ' . $e->getMessage());
}

header('Location: ' . routeUrl('usuarios'));
exit;
