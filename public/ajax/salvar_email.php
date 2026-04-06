<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';
require_once __DIR__ . '/../../app/models/CanalEmail.php';

ob_start();
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

Auth::requireLogin();

try {
    if (!Csrf::isValid()) {
        throw new RuntimeException('Token CSRF invalido.');
    }

    $empresaId = (int) Auth::getEmpresaId();

    if ($empresaId <= 0) {
        throw new RuntimeException('Empresa invalida.');
    }

    $db = new Database();
    $conn = $db->connect();
    $service = new EmailChannelAjaxService($conn, $empresaId);
    JsonResponse::send($service->save($_POST));
} catch (Throwable $e) {
    JsonResponse::send([
        'success' => false,
        'message' => $e->getMessage(),
    ], 400);
}
