<?php

class WhatsAppBridgeProxyService
{
    private CanalWhatsapp $canalWhatsappModel;
    private int $empresaId;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->empresaId = $empresaId;
        $this->canalWhatsappModel = new CanalWhatsapp($conn, $empresaId);
    }

    public function getSessionStatus(): array
    {
        $config = $this->getConfig();
        $path = '/sessions/' . rawurlencode((string) $config['session_name']) . '/status';

        return $this->requestJson('GET', $path, null, $config);
    }

    public function startSession(): array
    {
        $config = $this->getConfig();

        return $this->requestJson('POST', '/sessions/start', [
            'session' => (string) $config['session_name'],
        ], $config);
    }

    public function getQrViewHtml(): string
    {
        $config = $this->getConfig();
        $url = $this->buildUrl(
            '/sessions/' . rawurlencode((string) $config['session_name']) . '/qr/view',
            $config
        );

        $response = $this->requestRaw('GET', $url, null);

        if ($response['status_code'] < 200 || $response['status_code'] >= 300) {
            throw new RuntimeException('Falha ao carregar o QR do WhatsApp.');
        }

        return (string) $response['body'];
    }

    public function getQrViewUrl(): string
    {
        return routeUrl('conexoes_whatsapp_qr');
    }

    public function sendTestMessage(string $to, ?string $message = null): array
    {
        $config = $this->getConfig();
        $normalizedPhone = WhatsAppChannelService::normalizePhone($to);

        if ($normalizedPhone === '') {
            throw new RuntimeException('Informe um numero de WhatsApp valido para o teste.');
        }

        $text = trim((string) $message);
        if ($text === '') {
            $text = 'Teste de envio do canal WhatsApp do Dashboard ALP.';
        }

        return $this->requestJson('POST', '/send', [
            'session' => (string) $config['session_name'],
            'to' => $normalizedPhone,
            'message' => $text,
        ], $config);
    }

    private function getConfig(): array
    {
        $config = WhatsAppConnectionConfigResolver::resolve(
            $this->empresaId,
            $this->canalWhatsappModel->get()
        );

        $bridgeUrl = rtrim(trim((string) ($config['bridge_url'] ?? '')), '/');
        $sessionName = trim((string) ($config['session_name'] ?? ''));

        if ($bridgeUrl === '' || $sessionName === '') {
            throw new RuntimeException('Configure a URL do bridge e o nome da sessao do WhatsApp antes de conectar.');
        }

        $config['bridge_url'] = $bridgeUrl;
        $config['session_name'] = $sessionName;

        return $config;
    }

    private function requestJson(string $method, string $path, ?array $payload, array $config): array
    {
        $url = $this->buildUrl($path, $config);
        $response = $this->requestRaw($method, $url, $payload);

        $decoded = json_decode((string) $response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta invalida do bridge do WhatsApp.');
        }

        return $decoded;
    }

    private function buildUrl(string $path, array $config): string
    {
        $query = [];
        $token = trim((string) ($config['auth_token'] ?? ''));
        if ($token !== '') {
            $query['access_token'] = $token;
        }

        $url = $config['bridge_url'] . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    private function requestRaw(string $method, string $url, ?array $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nao foi possivel iniciar a conexao com o bridge do WhatsApp.');
        }

        $headers = ['Accept: application/json'];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('Erro ao conectar no bridge do WhatsApp: ' . $error);
        }

        return [
            'status_code' => $statusCode,
            'body' => (string) $body,
        ];
    }
}
