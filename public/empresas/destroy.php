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

$empresaModel = new Empresa($conn);
$empresaDeletionService = new EmpresaDeletionService($conn);

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    Flash::error('Empresa inválida.');
    header('Location: ' . routeUrl('empresas'));
    exit;
}

$empresa = $empresaModel->findById($id);

if (!$empresa) {
    Flash::error('Empresa não encontrada.');
    header('Location: ' . routeUrl('empresas'));
    exit;
}

if ((int) ($empresa['is_root'] ?? 0) === 1) {
    Flash::error('A empresa root não pode ser excluída.');
    header('Location: ' . routeUrl('empresas'));
    exit;
}

try {
    $empresaDeletionService->deleteEmpresa($id);
    Flash::success('Empresa excluída com sucesso.');
} catch (Throwable $e) {
    Flash::error('Não foi possível excluir a empresa: ' . $e->getMessage());
}

header('Location: ' . routeUrl('empresas'));
exit;
