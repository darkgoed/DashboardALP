<?php

class WhatsAppChannelService
{
    public static function validateConfig(array $config): array
    {
        $bridgeUrl = rtrim(trim((string) ($config['bridge_url'] ?? '')), '/');
        $sessionName = trim((string) ($config['session_name'] ?? ''));
        $authToken = trim((string) ($config['auth_token'] ?? ''));

        if ($bridgeUrl === '' || !filter_var($bridgeUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Informe uma URL valida para o bridge do WhatsApp.');
        }

        if ($sessionName === '') {
            throw new RuntimeException('Informe o nome da sessao do WhatsApp.');
        }

        return [
            'bridge_url' => $bridgeUrl,
            'session_name' => $sessionName,
            'auth_token' => $authToken,
        ];
    }

    public static function testar(array $config): array
    {
        $config = self::validateConfig($config);
        $response = self::request('GET', $config['bridge_url'] . '/health', $config, null);

        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'Bridge do WhatsApp respondeu com sucesso.',
            ];
        }

        return [
            'success' => false,
            'message' => $response['message'] ?: 'Falha ao validar o bridge do WhatsApp.',
        ];
    }

    public static function enviar(array $config, array $payload): array
    {
        $config = self::validateConfig($config);

        $to = self::normalizePhone((string) ($payload['to'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        if ($to === '') {
            throw new RuntimeException('Informe um numero de WhatsApp valido.');
        }

        if ($message === '') {
            throw new RuntimeException('A mensagem do WhatsApp nao pode ficar vazia.');
        }

        $response = self::request('POST', $config['bridge_url'] . '/send', $config, [
            'session' => $config['session_name'],
            'to' => $to,
            'message' => $message,
        ]);

        return [
            'success' => $response['success'],
            'message' => $response['message'] ?: ($response['success']
                ? 'Mensagem enviada com sucesso.'
                : 'Falha ao enviar mensagem pelo WhatsApp.'),
        ];
    }

    public static function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55' . $digits;
        }

        if (strlen($digits) < 12 || strlen($digits) > 15) {
            return '';
        }

        return $digits;
    }

    private static function request(string $method, string $url, array $config, ?array $json): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nao foi possivel iniciar a conexao HTTP do WhatsApp.');
        }

        $headers = [
            'Accept: application/json',
        ];

        if (!empty($config['auth_token'])) {
            $headers[] = 'Authorization: Bearer ' . $config['auth_token'];
        }

        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'success' => false,
                'message' => 'Erro de conexao com o bridge do WhatsApp: ' . $error,
            ];
        }

        $decoded = is_string($body) && $body !== ''
            ? json_decode($body, true)
            : null;

        $message = '';
        if (is_array($decoded)) {
            $message = trim((string) ($decoded['message'] ?? $decoded['error'] ?? ''));
        }

        if ($status >= 200 && $status < 300) {
            return [
                'success' => true,
                'message' => $message,
            ];
        }

        if ($message === '') {
            $message = 'Bridge do WhatsApp retornou HTTP ' . $status . '.';
        }

        return [
            'success' => false,
            'message' => $message,
        ];
    }
}
