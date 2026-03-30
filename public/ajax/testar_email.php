<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';
require_once __DIR__ . '/../../app/models/CanalEmail.php';
require_once __DIR__ . '/../../app/services/EmailChannelService.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireLogin();

try {
    $empresaId = (int) Auth::getEmpresaId();

    if ($empresaId <= 0) {
        throw new Exception('Empresa inválida.');
    }

    $destinoTeste = trim($_POST['email_teste'] ?? '');

    if ($destinoTeste === '' || !filter_var($destinoTeste, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Informe um email de teste válido.');
    }

    $db = new Database();
    $conn = $db->connect();

    $canalEmail = new CanalEmail($conn, $empresaId);
    $config = $canalEmail->get();

    if (!$config) {
        throw new Exception('Nenhuma configuração SMTP foi encontrada para esta empresa.');
    }

    $resultado = EmailChannelService::testar($config, $destinoTeste);

    if ($resultado['success']) {
        $canalEmail->updateStatus('ativo', null);

        echo json_encode([
            'success' => true,
            'message' => $resultado['message']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $canalEmail->updateStatus('erro', $resultado['message']);

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $resultado['message']
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}