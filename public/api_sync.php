<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

@set_time_limit(0);
@ini_set('memory_limit', '512M');
ignore_user_abort(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!Csrf::isValid()) {
    Flash::error('Token CSRF invalido.');
    header('Location: ' . routeUrl('api'));
    exit;
}

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$integracaoId = isset($_POST['integracao_id']) ? (int) $_POST['integracao_id'] : 0;
$modo = trim((string) ($_POST['modo_sync'] ?? 'incremental'));

if ($integracaoId <= 0) {
    Flash::error('Integracao invalida para sincronizacao.');
    header('Location: ' . routeUrl('api'));
    exit;
}

$service = new MercadoPhoneQueueService($conn);

try {
    $message = (new MercadoPhoneSyncActionService($conn))->enqueue($empresaId, $integracaoId, $modo);
    if (str_starts_with($message, 'Ja existe uma sync')) {
        Flash::info($message);
    } else {
        Flash::success($message);
    }
} catch (Throwable $e) {
    Flash::error('Erro na sync Mercado Phone: ' . $e->getMessage());
}

header('Location: ' . routeUrl('api') . '#integracao-' . $integracaoId);
exit;
