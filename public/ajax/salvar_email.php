<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';
require_once __DIR__ . '/../../app/models/CanalEmail.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireLogin();

try {
    $empresaId = (int) Auth::getEmpresaId();

    if ($empresaId <= 0) {
        throw new Exception('Empresa inválida.');
    }

    $db = new Database();
    $conn = $db->connect();

    $canalEmail = new CanalEmail($conn, $empresaId);

    $nomeRemetente  = trim($_POST['nome_remetente'] ?? '');
    $emailRemetente = trim($_POST['email_remetente'] ?? '');
    $emailReplyTo   = trim($_POST['email_reply_to'] ?? '');
    $smtpHost       = trim($_POST['smtp_host'] ?? '');
    $smtpPort       = (int) ($_POST['smtp_port'] ?? 587);
    $smtpSecure     = trim($_POST['smtp_secure'] ?? 'tls');
    $smtpUser       = trim($_POST['smtp_user'] ?? '');
    $smtpPass       = trim($_POST['smtp_pass'] ?? '');

    if ($nomeRemetente === '') {
        throw new Exception('Informe o nome do remetente.');
    }

    if ($emailRemetente === '' || !filter_var($emailRemetente, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Informe um email remetente válido.');
    }

    if ($emailReplyTo !== '' && !filter_var($emailReplyTo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Informe um email de resposta válido.');
    }

    if ($smtpHost === '') {
        throw new Exception('Informe o host SMTP.');
    }

    if ($smtpPort <= 0) {
        throw new Exception('Informe uma porta SMTP válida.');
    }

    if (!in_array($smtpSecure, ['tls', 'ssl', 'none'], true)) {
        throw new Exception('Tipo de criptografia SMTP inválido.');
    }

    if ($smtpUser === '') {
        throw new Exception('Informe o usuário SMTP.');
    }

    $atual = $canalEmail->get();

    if ($smtpPass === '') {
        if ($atual && !empty($atual['smtp_pass'])) {
            $smtpPass = $atual['smtp_pass'];
        } else {
            throw new Exception('Informe a senha SMTP.');
        }
    }

    $ok = $canalEmail->save([
        'nome_remetente'  => $nomeRemetente,
        'email_remetente' => $emailRemetente,
        'email_reply_to'  => $emailReplyTo !== '' ? $emailReplyTo : null,
        'smtp_host'       => $smtpHost,
        'smtp_port'       => $smtpPort,
        'smtp_secure'     => $smtpSecure,
        'smtp_user'       => $smtpUser,
        'smtp_pass'       => $smtpPass,
    ]);

    if (!$ok) {
        throw new Exception('Não foi possível salvar a configuração de email.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Configuração de email salva com sucesso.'
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