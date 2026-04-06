<?php

class MetaCallbackService
{
    private PDO $conn;
    private int $empresaId;
    private int $usuarioId;
    private MetaAdsService $meta;
    private EmpresaLimiteService $limiteService;

    public function __construct(PDO $conn, int $empresaId, int $usuarioId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->usuarioId = $usuarioId;
        $this->meta = new MetaAdsService();
        $this->limiteService = new EmpresaLimiteService($conn);
    }

    public function handle(array $query, array &$session): array
    {
        $clienteId = 0;

        $this->debugLog('INICIO CALLBACK META', [
            'get' => $query,
            'session' => [
                'meta_cliente_id' => $session['meta_cliente_id'] ?? null,
                'meta_oauth_nonce' => $session['meta_oauth_nonce'] ?? null,
                'empresa_id' => $this->empresaId,
                'usuario_id' => $this->usuarioId,
            ],
            'url' => $_SERVER['REQUEST_URI'] ?? null,
        ]);

        if (($query['action'] ?? '') === 'login') {
            $clienteId = isset($query['cliente_id']) ? (int) $query['cliente_id'] : 0;

            if ($clienteId <= 0) {
                throw new RuntimeException('Cliente invalido para vincular a integracao.');
            }

            $this->assertClienteValido($clienteId);

            $nonce = bin2hex(random_bytes(16));
            $statePayload = [
                'cliente_id' => $clienteId,
                'empresa_id' => $this->empresaId,
                'nonce' => $nonce,
            ];
            $state = rtrim(strtr(base64_encode(json_encode($statePayload)), '+/', '-_'), '=');

            $session['meta_cliente_id'] = $clienteId;
            $session['meta_oauth_nonce'] = $nonce;

            $this->debugLog('REDIRECIONANDO PARA LOGIN META', [
                'cliente_id' => $clienteId,
                'state_payload' => $statePayload,
                'state' => $state,
                'login_url' => $this->meta->getLoginUrl($state),
            ]);

            return [
                'type' => 'redirect_external',
                'url' => $this->meta->getLoginUrl($state),
            ];
        }

        if (isset($query['error'])) {
            $this->debugLog('ERRO RETORNADO PELA META', $query);
            throw new RuntimeException((string) ($query['error_description'] ?? 'Autorizacao negada.'));
        }

        if (empty($query['code'])) {
            throw new RuntimeException('Codigo de autorizacao nao recebido.');
        }

        $state = (string) ($query['state'] ?? '');

        if ($state !== '') {
            $decodedState = base64_decode(strtr($state, '-_', '+/'));
            $stateData = json_decode((string) $decodedState, true);

            $this->debugLog('STATE RECEBIDO', [
                'state_raw' => $state,
                'state_decoded' => $decodedState,
                'state_data' => $stateData,
            ]);

            if (
                is_array($stateData) &&
                !empty($stateData['cliente_id']) &&
                !empty($stateData['empresa_id']) &&
                !empty($stateData['nonce'])
            ) {
                if (empty($session['meta_oauth_nonce'])) {
                    throw new RuntimeException('Sessao da integracao nao encontrada para validar o retorno.');
                }

                if (!hash_equals((string) $session['meta_oauth_nonce'], (string) $stateData['nonce'])) {
                    throw new RuntimeException('Falha na validacao de seguranca da integracao.');
                }

                if ((int) $stateData['empresa_id'] !== $this->empresaId) {
                    throw new RuntimeException('Empresa da integracao invalida.');
                }

                $clienteId = (int) $stateData['cliente_id'];
            }
        }

        if ($clienteId <= 0) {
            $clienteId = isset($session['meta_cliente_id']) ? (int) $session['meta_cliente_id'] : 0;
        }

        if ($clienteId <= 0) {
            throw new RuntimeException('Cliente da integracao nao encontrado na sessao.');
        }

        $this->assertClienteValido($clienteId);

        $tokenData = $this->meta->exchangeCodeForToken((string) $query['code']);
        $this->debugLog('TOKEN CURTO RETORNADO', $tokenData);

        $shortToken = $tokenData['access_token'] ?? null;
        if (!$shortToken) {
            throw new RuntimeException('Access token curto nao retornado.');
        }

        $longData = $this->meta->getLongLivedToken($shortToken);
        $this->debugLog('TOKEN LONGO RETORNADO', $longData);

        $accessToken = $longData['access_token'] ?? $shortToken;
        $expiresIn = $longData['expires_in'] ?? null;
        $me = $this->meta->getMe($accessToken);
        $this->debugLog('DADOS DO USUARIO META', $me);

        $expiresAt = null;
        if (!empty($expiresIn)) {
            $expiresAt = date('Y-m-d H:i:s', time() + (int) $expiresIn);
        }

        $accountsProcessed = [];

        $this->conn->beginTransaction();
        try {
            $this->upsertMetaToken($clienteId, $me, $accessToken, $longData, $expiresAt);

            $accounts = $this->meta->getAdAccounts($accessToken);
            $this->debugLog('CONTAS DE ANUNCIO RETORNADAS', $accounts);

            foreach (($accounts['data'] ?? []) as $account) {
                $metaAccountId = $account['account_id'] ?? null;

                if (!$metaAccountId) {
                    $this->debugLog('CONTA IGNORADA SEM account_id', $account);
                    continue;
                }

                $businessName = $account['business']['name'] ?? null;
                $this->upsertContaAds($clienteId, $metaAccountId, $account, $businessName);

                $accountsProcessed[] = [
                    'meta_account_id' => $metaAccountId,
                    'nome' => $account['name'] ?? null,
                    'status' => $account['account_status'] ?? null,
                    'business_name' => $businessName,
                ];
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        $this->debugLog('CALLBACK FINALIZADO COM SUCESSO', [
            'cliente_id' => $clienteId,
            'accounts_processed' => $accountsProcessed,
            'total_accounts' => count($accountsProcessed),
        ]);

        $this->enqueueInitialJobs($accountsProcessed);

        unset($session['meta_cliente_id'], $session['meta_oauth_nonce']);

        return [
            'type' => 'redirect_internal',
            'url' => $this->buildIntegrationUrl($clienteId),
            'flash_success' => 'Integracao Meta concluida com sucesso.',
        ];
    }

    public function buildErrorRedirect(Throwable $e, array $query, array &$session, int $clienteId = 0): array
    {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }

        $this->debugLog('ERRO NO CALLBACK META', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'get' => $query,
            'session' => [
                'meta_cliente_id' => $session['meta_cliente_id'] ?? null,
                'meta_oauth_nonce' => $session['meta_oauth_nonce'] ?? null,
                'empresa_id' => $this->empresaId,
                'usuario_id' => $this->usuarioId,
            ],
        ]);

        $clienteIdErro = 0;

        if ($clienteId > 0) {
            $clienteIdErro = $clienteId;
        } elseif (!empty($session['meta_cliente_id'])) {
            $clienteIdErro = (int) $session['meta_cliente_id'];
        } elseif (!empty($query['cliente_id'])) {
            $clienteIdErro = (int) $query['cliente_id'];
        }

        unset($session['meta_oauth_nonce'], $session['meta_cliente_id']);

        return [
            'type' => 'redirect_internal',
            'url' => $this->buildIntegrationUrl($clienteIdErro),
            'flash_error' => 'Nao foi possivel concluir a integracao com a Meta: ' . $e->getMessage(),
        ];
    }

    private function assertClienteValido(int $clienteId): void
    {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM clientes
            WHERE empresa_id = :empresa_id
              AND id = :cliente_id
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
        ]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Cliente invalido para esta empresa.');
        }
    }

    private function upsertMetaToken(int $clienteId, array $me, string $accessToken, array $longData, ?string $expiresAt): void
    {
        $checkToken = $this->conn->prepare("
            SELECT id
            FROM meta_tokens
            WHERE empresa_id = :empresa_id
              AND cliente_id = :cliente_id
            LIMIT 1
        ");
        $checkToken->execute([
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
        ]);

        $params = [
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
            ':meta_user_id' => $me['id'] ?? null,
            ':access_token' => $accessToken,
            ':token_type' => $longData['token_type'] ?? 'bearer',
            ':expires_at' => $expiresAt,
            ':scopes' => isset($longData['granted_scopes']) ? json_encode($longData['granted_scopes'], JSON_UNESCAPED_UNICODE) : null,
        ];

        if ($checkToken->fetch(PDO::FETCH_ASSOC)) {
            $stmt = $this->conn->prepare("
                UPDATE meta_tokens
                SET meta_user_id = :meta_user_id,
                    access_token = :access_token,
                    token_type = :token_type,
                    expires_at = :expires_at,
                    scopes = :scopes
                WHERE empresa_id = :empresa_id
                  AND cliente_id = :cliente_id
            ");
        } else {
            $stmt = $this->conn->prepare("
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
        }

        $stmt->execute($params);
    }

    private function upsertContaAds(int $clienteId, string $metaAccountId, array $account, ?string $businessName): void
    {
        $check = $this->conn->prepare("
            SELECT id
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND meta_account_id = :meta_account_id
            LIMIT 1
        ");
        $check->execute([
            ':empresa_id' => $this->empresaId,
            ':meta_account_id' => $metaAccountId,
        ]);

        $params = [
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
            ':nome' => $account['name'] ?? 'Conta sem nome',
            ':business_name' => $businessName,
            ':moeda' => $account['currency'] ?? null,
            ':timezone_name' => $account['timezone_name'] ?? null,
            ':status' => (string) ($account['account_status'] ?? null),
            ':meta_account_id' => $metaAccountId,
        ];

        if ($check->fetch(PDO::FETCH_ASSOC)) {
            $stmt = $this->conn->prepare("
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
        } else {
            $this->limiteService->validarNovaContaAds($this->empresaId);
            $stmt = $this->conn->prepare("
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
        }

        $stmt->execute($params);
    }

    private function enqueueInitialJobs(array $accountsProcessed): void
    {
        $syncJob = new SyncJob($this->conn);
        $inicioInsights = date('Y-m-d', strtotime('-7 days'));
        $fimInsights = date('Y-m-d');

        foreach ($accountsProcessed as $acc) {
            $stmtConta = $this->conn->prepare("
                SELECT id, cliente_id
                FROM contas_ads
                WHERE empresa_id = :empresa_id
                  AND meta_account_id = :meta_account_id
                LIMIT 1
            ");
            $stmtConta->execute([
                ':empresa_id' => $this->empresaId,
                ':meta_account_id' => $acc['meta_account_id'],
            ]);

            $contaRow = $stmtConta->fetch(PDO::FETCH_ASSOC);
            if (!$contaRow) {
                continue;
            }

            $contaIdFila = (int) $contaRow['id'];
            $clienteIdFila = (int) $contaRow['cliente_id'];

            $syncJob->enqueueIfNotExists([
                'empresa_id' => $this->empresaId,
                'cliente_id' => $clienteIdFila,
                'conta_id' => $contaIdFila,
                'tipo' => 'estrutura',
                'origem' => 'manual',
                'prioridade' => 3,
                'force_sync' => 1,
                'mensagem' => 'Job automatico gerado apos conexao Meta.',
            ]);

            $syncJob->enqueueIfNotExists([
                'empresa_id' => $this->empresaId,
                'cliente_id' => $clienteIdFila,
                'conta_id' => $contaIdFila,
                'tipo' => 'insights',
                'origem' => 'manual',
                'prioridade' => 4,
                'force_sync' => 1,
                'janela_inicio' => $inicioInsights,
                'janela_fim' => $fimInsights,
                'mensagem' => 'Job automatico de insights gerado apos conexao Meta.',
            ]);
        }
    }

    private function buildIntegrationUrl(int $clienteId = 0): string
    {
        $url = routeUrl('integracoes_meta');

        if ($clienteId > 0) {
            $url .= '?cliente_id=' . $clienteId;
        }

        return $url;
    }

    private function debugEnabled(): bool
    {
        return Env::get('META_DEBUG_LOG', 'false') === 'true';
    }

    private function maskString(string $value): string
    {
        $length = strlen($value);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', max(4, $length - 8)) . substr($value, -4);
    }

    private function sanitizeLogData($data)
    {
        if (is_array($data)) {
            $sanitized = [];
            $sensitiveKeys = [
                'access_token',
                'authorization',
                'code',
                'state',
                'client_secret',
                'token',
                'api_token',
                'meta_oauth_nonce',
                'http_x_csrf_token',
                'csrf_token',
            ];

            foreach ($data as $key => $value) {
                $normalizedKey = is_string($key) ? strtolower($key) : '';

                if ($normalizedKey !== '' && in_array($normalizedKey, $sensitiveKeys, true)) {
                    $sanitized[$key] = is_scalar($value) ? $this->maskString((string) $value) : '[redacted]';
                    continue;
                }

                $sanitized[$key] = $this->sanitizeLogData($value);
            }

            return $sanitized;
        }

        if (is_object($data)) {
            return $this->sanitizeLogData((array) $data);
        }

        return $data;
    }

    private function debugLog(string $title, $data = null): void
    {
        if (!$this->debugEnabled()) {
            return;
        }

        $logDir = dirname(__DIR__) . '/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/meta_callback_debug.log';
        $content = "\n============================================================\n";
        $content .= '[' . date('Y-m-d H:i:s') . '] ' . $title . "\n";

        if ($data !== null) {
            $data = $this->sanitizeLogData($data);

            if (is_array($data) || is_object($data)) {
                $content .= print_r($data, true) . "\n";
            } else {
                $content .= (string) $data . "\n";
            }
        }

        $content .= "============================================================\n";
        @file_put_contents($logFile, $content, FILE_APPEND);
    }
}
