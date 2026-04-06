<?php

class PasswordResetService
{
    private const TOKEN_TTL = 3600;

    private PDO $conn;
    private Usuario $usuarioModel;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->usuarioModel = new Usuario($conn);
    }

    public function enviarLink(string $email): void
    {
        $usuario = $this->usuarioModel->findByEmail($email);

        if (!$usuario || ($usuario['status'] ?? '') !== 'ativo') {
            $this->logReset('usuario_nao_encontrado_ou_inativo', [
                'email' => $email,
            ]);
            return;
        }

        $config = $this->getGlobalSmtpConfig();

        if (!$config) {
            $this->logReset('smtp_global_nao_configurado', [
                'usuario_id' => (int) $usuario['id'],
                'email' => (string) $usuario['email'],
            ]);
            return;
        }

        $token = $this->generateToken($usuario);
        $resetUrl = appUrl('recuperar-senha?token=' . rawurlencode($token));
        $loginUrl = appUrl('login');
        $nome = trim((string) ($usuario['nome'] ?? ''));

        $resultado = EmailChannelService::enviar($config, [
            'to_email' => (string) $usuario['email'],
            'to_name' => $nome,
            'subject' => 'Recuperacao de senha - Dashboard ALP',
            'html' => $this->buildHtml($nome, $resetUrl, $loginUrl),
            'text' => $this->buildText($resetUrl, $loginUrl),
            'success_message' => 'Email de recuperacao enviado com sucesso.'
        ]);

        if (empty($resultado['success'])) {
            $this->logReset('falha_no_envio_email', [
                'usuario_id' => (int) $usuario['id'],
                'email' => (string) $usuario['email'],
                'erro' => (string) ($resultado['message'] ?? 'erro_desconhecido'),
            ]);
            return;
        }

        $this->logReset('email_enviado', [
            'usuario_id' => (int) $usuario['id'],
            'email' => (string) $usuario['email'],
        ]);
    }

    public function validarToken(string $token): array
    {
        $token = trim($token);

        if ($token === '') {
            return ['ok' => false, 'message' => 'Token nao informado.'];
        }

        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return ['ok' => false, 'message' => 'Link de recuperacao invalido.'];
        }

        [$payloadEncoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $payloadEncoded, $this->getSecret());

        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'message' => 'Link de recuperacao invalido ou adulterado.'];
        }

        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!is_array($payload)) {
            return ['ok' => false, 'message' => 'Link de recuperacao invalido.'];
        }

        $usuarioId = (int) ($payload['uid'] ?? 0);
        $email = trim((string) ($payload['email'] ?? ''));
        $expiraEm = (int) ($payload['exp'] ?? 0);
        $passwordFingerprint = (string) ($payload['pwd'] ?? '');

        if ($usuarioId <= 0 || $email === '' || $expiraEm <= time()) {
            $this->logReset('token_expirado_ou_invalido', [
                'usuario_id' => $usuarioId,
                'email' => $email,
            ]);
            return ['ok' => false, 'message' => 'O link de recuperacao expirou.'];
        }

        $usuario = $this->usuarioModel->findById($usuarioId);

        if (!$usuario || mb_strtolower((string) $usuario['email']) !== mb_strtolower($email)) {
            $this->logReset('usuario_do_token_nao_encontrado', [
                'usuario_id' => $usuarioId,
                'email' => $email,
            ]);
            return ['ok' => false, 'message' => 'Usuario nao encontrado para este link.'];
        }

        if (($usuario['status'] ?? '') !== 'ativo') {
            $this->logReset('usuario_do_token_inativo', [
                'usuario_id' => (int) $usuario['id'],
                'email' => (string) $usuario['email'],
                'status' => (string) ($usuario['status'] ?? ''),
            ]);
            return ['ok' => false, 'message' => 'Este usuario nao pode redefinir a senha no momento.'];
        }

        if (!hash_equals($this->passwordFingerprint((string) $usuario['senha_hash']), $passwordFingerprint)) {
            $this->logReset('token_invalidado_por_troca_de_senha', [
                'usuario_id' => (int) $usuario['id'],
                'email' => (string) $usuario['email'],
            ]);
            return ['ok' => false, 'message' => 'Este link ja nao e mais valido. Solicite uma nova recuperacao.'];
        }

        return [
            'ok' => true,
            'usuario' => $usuario,
            'expires_at' => $expiraEm,
        ];
    }

    public function redefinirSenha(string $token, string $novaSenha): array
    {
        $validacao = $this->validarToken($token);

        if (empty($validacao['ok'])) {
            return $validacao;
        }

        $usuario = $validacao['usuario'];
        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $salvo = $this->usuarioModel->updatePassword((int) $usuario['id'], $senhaHash);

        if (!$salvo) {
            $this->logReset('falha_ao_atualizar_senha', [
                'usuario_id' => (int) $usuario['id'],
                'email' => (string) $usuario['email'],
            ]);
            return ['ok' => false, 'message' => 'Nao foi possivel atualizar a senha.'];
        }

        $this->logReset('senha_atualizada', [
            'usuario_id' => (int) $usuario['id'],
            'email' => (string) $usuario['email'],
        ]);

        return ['ok' => true, 'message' => 'Senha atualizada com sucesso.'];
    }

    private function generateToken(array $usuario): string
    {
        $payload = [
            'uid' => (int) $usuario['id'],
            'email' => mb_strtolower(trim((string) $usuario['email'])),
            'exp' => time() + self::TOKEN_TTL,
            'pwd' => $this->passwordFingerprint((string) $usuario['senha_hash']),
        ];

        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $payloadEncoded, $this->getSecret());

        return $payloadEncoded . '.' . $signature;
    }

    private function passwordFingerprint(string $senhaHash): string
    {
        return hash('sha256', $senhaHash);
    }

    private function getSecret(): string
    {
        $parts = [
            Env::get('APP_URL', ''),
            Env::get('SESSION_NAME', ''),
            Env::get('DB_HOST', ''),
            Env::get('DB_NAME', ''),
            Env::get('DB_USER', ''),
            Env::get('DB_PASS', ''),
        ];

        return hash('sha256', implode('|', $parts));
    }

    private function getGlobalSmtpConfig(): ?array
    {
        return EmailChannelService::globalConfigFromEnv();
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'));
    }

    private function buildHtml(string $nome, string $resetUrl, string $loginUrl): string
    {
        $titulo = $nome !== '' ? 'Ola, ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '.' : 'Ola.';
        $resetUrlEscaped = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $loginUrlEscaped = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        return '
            <div style="font-family: Inter, Arial, sans-serif; font-size:14px; line-height:1.6; color:#111827;">
                <p style="margin:0 0 16px;">' . $titulo . '</p>
                <p style="margin:0 0 16px;">Recebemos um pedido para redefinir sua senha no Dashboard ALP.</p>
                <p style="margin:0 0 24px;">
                    <a href="' . $resetUrlEscaped . '" style="display:inline-block;padding:12px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;">Redefinir senha</a>
                </p>
                <p style="margin:0 0 12px;">Este link expira em 1 hora. Se voce nao solicitou a alteracao, ignore este email.</p>
                <p style="margin:0 0 8px;">Se o botao nao funcionar, use este link:</p>
                <p style="margin:0 0 16px;word-break:break-all;"><a href="' . $resetUrlEscaped . '">' . $resetUrlEscaped . '</a></p>
                <p style="margin:0;">Login: <a href="' . $loginUrlEscaped . '">' . $loginUrlEscaped . '</a></p>
            </div>
        ';
    }

    private function buildText(string $resetUrl, string $loginUrl): string
    {
        return "Recebemos um pedido para redefinir sua senha no Dashboard ALP.\n\n"
            . "Use o link abaixo para criar uma nova senha:\n"
            . $resetUrl . "\n\n"
            . "Este link expira em 1 hora. Se voce nao solicitou a alteracao, ignore este email.\n\n"
            . "Login: " . $loginUrl;
    }

    private function logReset(string $evento, array $context = []): void
    {
        $payload = [
            'evento' => $evento,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'email' => isset($context['email']) ? $this->maskEmail((string) $context['email']) : null,
            'usuario_id' => $context['usuario_id'] ?? null,
            'status' => $context['status'] ?? null,
            'erro' => $context['erro'] ?? null,
        ];

        error_log('[password_reset] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function maskEmail(string $email): string
    {
        $email = trim(mb_strtolower($email));

        if ($email === '' || strpos($email, '@') === false) {
            return 'indefinido';
        }

        [$local, $domain] = explode('@', $email, 2);
        $prefixo = mb_substr($local, 0, 2);

        return $prefixo . '***@' . $domain;
    }
}
