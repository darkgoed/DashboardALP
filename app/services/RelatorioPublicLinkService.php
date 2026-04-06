<?php

class RelatorioPublicLinkService
{
    private const TOKEN_TTL = 2592000;

    public function generateUrl(int $empresaId, array $query): string
    {
        $token = $this->generateToken($empresaId, $query);

        return appUrl('relatorio_view?token=' . rawurlencode($token));
    }

    public function validateToken(string $token): array
    {
        $token = trim($token);

        if ($token === '') {
            return ['ok' => false, 'message' => 'Token nao informado.'];
        }

        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return ['ok' => false, 'message' => 'Link do relatorio invalido.'];
        }

        [$payloadEncoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $payloadEncoded, $this->getSecret());

        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'message' => 'Link do relatorio invalido ou adulterado.'];
        }

        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!is_array($payload)) {
            return ['ok' => false, 'message' => 'Link do relatorio invalido.'];
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $expiraEm = (int) ($payload['exp'] ?? 0);
        $query = $payload['query'] ?? null;

        if ($empresaId <= 0 || !is_array($query) || $expiraEm <= time()) {
            return ['ok' => false, 'message' => 'O link publico do relatorio expirou.'];
        }

        return [
            'ok' => true,
            'empresa_id' => $empresaId,
            'query' => $this->normalizeQuery($query),
            'expires_at' => $expiraEm,
        ];
    }

    private function generateToken(int $empresaId, array $query): string
    {
        $payload = [
            'empresa_id' => $empresaId,
            'query' => $this->normalizeQuery($query),
            'exp' => time() + self::TOKEN_TTL,
        ];

        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $payloadEncoded, $this->getSecret());

        return $payloadEncoded . '.' . $signature;
    }

    private function normalizeQuery(array $query): array
    {
        return [
            'cliente_id' => !empty($query['cliente_id']) ? (int) $query['cliente_id'] : 0,
            'conta_id' => !empty($query['conta_id']) ? (int) $query['conta_id'] : 0,
            'campanha_id' => !empty($query['campanha_id']) ? (int) $query['campanha_id'] : 0,
            'campanha_status' => isset($query['campanha_status']) ? strtoupper(trim((string) $query['campanha_status'])) : '',
            'periodo' => isset($query['periodo']) ? trim((string) $query['periodo']) : '30',
            'data_inicio' => isset($query['data_inicio']) ? trim((string) $query['data_inicio']) : '',
            'data_fim' => isset($query['data_fim']) ? trim((string) $query['data_fim']) : '',
        ];
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
}
