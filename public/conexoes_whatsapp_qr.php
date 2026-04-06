<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

header('Content-Type: text/html; charset=utf-8');

try {
    $empresaId = (int) Auth::getEmpresaId();
    $service = new WhatsAppBridgeProxyService($conn, $empresaId);
    echo $service->getQrViewHtml();
} catch (Throwable $e) {
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Erro ao carregar QR</title>
    </head>
    <body>
        <h1>Erro ao carregar QR</h1>
        <p><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'); ?></p>
    </body>
    </html>
    <?php
}
