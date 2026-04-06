<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

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

    $destino = trim((string) ($_POST['destino_whatsapp_teste'] ?? ''));
    $mensagem = trim((string) ($_POST['mensagem_whatsapp_teste'] ?? ''));

    $db = new Database();
    $conn = $db->connect();

    $service = new WhatsAppBridgeProxyService($conn, $empresaId);
    JsonResponse::send($service->sendTestMessage($destino, $mensagem));
} catch (Throwable $e) {
    JsonResponse::send([
        'success' => false,
        'message' => $e->getMessage(),
    ], 400);
}
