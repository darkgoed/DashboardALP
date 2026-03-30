<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$empresaId = Auth::getEmpresaId();

$db = new Database();
$conn = $db->connect();

$clienteModel = new Cliente($conn, $empresaId);
$meta = new MetaAdsService();

try {
    if (isset($_GET['action']) && $_GET['action'] === 'login') {
        $clienteId = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0;

        if ($clienteId <= 0) {
            throw new Exception('Cliente inválido para vincular a integração.');
        }

        $_SESSION['meta_cliente_id'] = $clienteId;

        header('Location: ' . $meta->getLoginUrl());
        exit;
    }

    if (isset($_GET['error'])) {
        throw new Exception($_GET['error_description'] ?? 'Autorização negada.');
    }

    if (empty($_GET['code'])) {
        throw new Exception('Código de autorização não recebido.');
    }

    $clienteId = isset($_SESSION['meta_cliente_id']) ? (int) $_SESSION['meta_cliente_id'] : 0;

    if ($clienteId <= 0) {
        throw new Exception('Cliente da integração não encontrado na sessão.');
    }

    $checkCliente = $conn->prepare("
        SELECT id
        FROM clientes
        WHERE empresa_id = :empresa_id
          AND id = :cliente_id
        LIMIT 1
    ");
    $checkCliente->execute([
        ':empresa_id' => $empresaId,
        ':cliente_id' => $clienteId
    ]);

    if (!$checkCliente->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Cliente inválido para esta empresa.');
    }

    $tokenData = $meta->exchangeCodeForToken($_GET['code']);
    $shortToken = $tokenData['access_token'] ?? null;

    if (!$shortToken) {
        throw new Exception('Access token curto não retornado.');
    }

    $longData = $meta->getLongLivedToken($shortToken);
    $accessToken = $longData['access_token'] ?? $shortToken;
    $expiresIn = $longData['expires_in'] ?? null;

    $me = $meta->getMe($accessToken);

    $expiresAt = null;
    if ($expiresIn) {
        $expiresAt = date('Y-m-d H:i:s', time() + (int) $expiresIn);
    }

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO meta_tokens (
            empresa_id,
            cliente_id,
            meta_user_id,
            access_token,
            token_type,
            expires_at,
            scopes
        )
        VALUES (
            :empresa_id,
            :cliente_id,
            :meta_user_id,
            :access_token,
            :token_type,
            :expires_at,
            :scopes
        )
    ");
    $stmt->execute([
        ':empresa_id'   => $empresaId,
        ':cliente_id'   => $clienteId,
        ':meta_user_id' => $me['id'] ?? null,
        ':access_token' => $accessToken,
        ':token_type'   => $longData['token_type'] ?? 'bearer',
        ':expires_at'   => $expiresAt,
        ':scopes'       => null
    ]);

    $accounts = $meta->getAdAccounts($accessToken);

    foreach (($accounts['data'] ?? []) as $account) {
        $metaAccountId = $account['account_id'] ?? null;

        if (!$metaAccountId) {
            continue;
        }

        $businessName = $account['business']['name'] ?? null;

        $check = $conn->prepare("
            SELECT id, cliente_id
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND meta_account_id = :meta_account_id
            LIMIT 1
        ");
        $check->execute([
            ':empresa_id' => $empresaId,
            ':meta_account_id' => $metaAccountId
        ]);

        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $conn->prepare("
                UPDATE contas_ads
                SET cliente_id = :cliente_id,
                    nome = :nome,
                    business_name = :business_name,
                    moeda = :moeda,
                    timezone_name = :timezone_name,
                    status = :status,
                    ativo = 1
                WHERE empresa_id = :empresa_id
                  AND meta_account_id = :meta_account_id
            ");

            $update->execute([
                ':empresa_id'      => $empresaId,
                ':cliente_id'      => $clienteId,
                ':nome'            => $account['name'] ?? 'Conta sem nome',
                ':business_name'   => $businessName,
                ':moeda'           => $account['currency'] ?? null,
                ':timezone_name'   => $account['timezone_name'] ?? null,
                ':status'          => (string) ($account['account_status'] ?? null),
                ':meta_account_id' => $metaAccountId
            ]);
        } else {
            $insert = $conn->prepare("
                INSERT INTO contas_ads (
                    empresa_id,
                    cliente_id,
                    meta_account_id,
                    nome,
                    business_name,
                    moeda,
                    timezone_name,
                    status,
                    ativo
                )
                VALUES (
                    :empresa_id,
                    :cliente_id,
                    :meta_account_id,
                    :nome,
                    :business_name,
                    :moeda,
                    :timezone_name,
                    :status,
                    1
                )
            ");

            $insert->execute([
                ':empresa_id'      => $empresaId,
                ':cliente_id'      => $clienteId,
                ':meta_account_id' => $metaAccountId,
                ':nome'            => $account['name'] ?? 'Conta sem nome',
                ':business_name'   => $businessName,
                ':moeda'           => $account['currency'] ?? null,
                ':timezone_name'   => $account['timezone_name'] ?? null,
                ':status'          => (string) ($account['account_status'] ?? null)
            ]);
        }
    }

    $conn->commit();

    unset($_SESSION['meta_cliente_id']);

    echo '<h1>Integração concluída</h1>';
    echo '<p>Usuário Meta conectado com sucesso.</p>';
    echo '<p>Cliente vinculado: <strong>' . (int) $clienteId . '</strong></p>';
    echo '<p>Contas importadas/atualizadas na tabela <strong>contas_ads</strong>.</p>';

    echo '<pre>';
    print_r($accounts);
    echo '</pre>';
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);
    echo '<h1>Erro na integração</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}