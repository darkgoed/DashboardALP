<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if (!method_exists('Auth', 'isPlatformRoot') || !Auth::isPlatformRoot()) {
    header('Location: ' . routeUrl('dashboard'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . routeUrl('empresas/create'));
    exit;
}

if (!Csrf::isValid()) {
    $_SESSION['errors'] = ['geral' => 'Token CSRF inválido.'];
    header('Location: ' . routeUrl('empresas/create'));
    exit;
}

$db = new Database();
$conn = $db->connect();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function redirectWithErrors(array $errors, array $old): void
{
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = $old;

    header('Location: ' . routeUrl('empresas/create'));
    exit;
}

try {
    $empresaWriteService = new EmpresaWriteService($conn);
    $result = $empresaWriteService->create($_POST);

    $_SESSION['flash_success'] = 'Empresa criada com sucesso.';
    $_SESSION['flash_convite_admin'] = [
        'empresa_id' => $result['empresa_id'],
        'empresa_nome' => $result['empresa_nome'],
        'nome' => $result['responsavel_nome'],
        'email' => $result['responsavel_email'],
        'link' => $result['convite_link'],
        'expires_at' => $result['convite_expires_at'],
        'email_sent' => !empty($result['email_result']['success']),
        'email_message' => (string) ($result['email_result']['message'] ?? ''),
    ];

    if (!empty($result['email_result']['success'])) {
        Flash::success('Convite enviado automaticamente para ' . $result['responsavel_email'] . '.');
    } else {
        Flash::warning('Convite gerado, mas o envio automatico falhou: ' . (string) ($result['email_result']['message'] ?? 'erro desconhecido') . '.');
    }

    header('Location: ' . routeUrl('empresas/edit') . '?id=' . $result['empresa_id']);
    exit;
} catch (FormValidationException $e) {
    redirectWithErrors($e->getErrors(), $e->getOld());
} catch (Throwable $e) {
    redirectWithErrors(['geral' => $e->getMessage()], []);
}
