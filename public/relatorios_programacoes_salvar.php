<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . routeUrl('relatorios'));
    exit;
}

if (!Csrf::isValid()) {
    Flash::error('Token CSRF inválido.');
    header('Location: ' . routeUrl('relatorios'));
    exit;
}

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = (int) Auth::getEmpresaId();
$service = new RelatorioProgramacaoService($conn, $empresaId);
$returnQuery = trim((string) ($_POST['return_query'] ?? ''));

try {
    $saved = $service->saveMany((array) ($_POST['programacoes'] ?? []));
    Flash::success($saved > 0
        ? 'Programações automáticas salvas: ' . $saved . '.'
        : 'Nenhuma programação ativa foi salva.');
} catch (Throwable $e) {
    Flash::error($e->getMessage());
}

$redirect = routeUrl('relatorios');
if ($returnQuery !== '') {
    $redirect .= '?' . $returnQuery;
}

header('Location: ' . $redirect . '#programacoes');
exit;
