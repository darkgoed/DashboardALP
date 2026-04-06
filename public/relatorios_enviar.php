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
$relatorioDeliveryService = new RelatorioDeliveryService($conn, $empresaId);

try {
    $result = $relatorioDeliveryService->send($_POST);
    $returnQuery = http_build_query($result['query']);
    $canal = (string) ($result['canal'] ?? 'email');

    if (!empty($result['resultado']['success'])) {
        if ($canal === 'whatsapp') {
            Flash::success('Relatório enviado para o WhatsApp ' . (string) ($result['destino_whatsapp'] ?? '') . '.');
        } else {
            Flash::success('Relatório enviado para ' . (string) ($result['destino_email'] ?? '') . '.');
        }
    } else {
        Flash::error('Falha ao enviar relatório: ' . (string) ($result['resultado']['message'] ?? 'erro desconhecido') . '.');
    }
} catch (Throwable $e) {
    $returnQuery = http_build_query([
        'cliente_id' => $_POST['cliente_id'] ?? '',
        'conta_id' => $_POST['conta_id'] ?? '',
        'campanha_id' => $_POST['campanha_id'] ?? '',
        'campanha_status' => $_POST['campanha_status'] ?? '',
        'periodo' => $_POST['periodo'] ?? '30',
        'data_inicio' => $_POST['data_inicio'] ?? '',
        'data_fim' => $_POST['data_fim'] ?? '',
    ]);
    Flash::error($e->getMessage());
}

header('Location: ' . routeUrl('relatorios') . ($returnQuery !== '' ? '?' . $returnQuery : ''));
exit;
