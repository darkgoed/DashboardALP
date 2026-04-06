<?php

class WhatsAppConnectionConfigResolver
{
    public static function resolve(int $empresaId, ?array $storedConfig = null): array
    {
        $storedBridgeUrl = rtrim(trim((string) ($storedConfig['bridge_url'] ?? '')), '/');
        $storedAuthToken = trim((string) ($storedConfig['auth_token'] ?? ''));
        $storedSessionName = trim((string) ($storedConfig['session_name'] ?? ''));

        $bridgeUrl = rtrim(trim((string) Env::get('WHATSAPP_BRIDGE_URL', '')), '/');
        if ($bridgeUrl === '') {
            $bridgeUrl = $storedBridgeUrl !== '' ? $storedBridgeUrl : 'http://127.0.0.1:3010';
        }

        $authToken = trim((string) Env::get('WHATSAPP_BRIDGE_AUTH_TOKEN', ''));
        if ($authToken === '') {
            $authToken = $storedAuthToken;
        }

        $prefix = trim((string) Env::get('WHATSAPP_SESSION_PREFIX', ''));
        if ($prefix !== '') {
            $sessionName = $prefix . '-' . $empresaId;
        } else {
            $sessionName = $storedSessionName !== '' ? $storedSessionName : ('alp-empresa-' . $empresaId);
        }

        return [
            'nome_conexao' => trim((string) ($storedConfig['nome_conexao'] ?? 'WhatsApp relatorios')),
            'bridge_url' => $bridgeUrl,
            'session_name' => $sessionName,
            'auth_token' => $authToken,
            'numero_teste_padrao' => trim((string) ($storedConfig['numero_teste_padrao'] ?? '')),
            'status_conexao' => (string) ($storedConfig['status_conexao'] ?? 'inativo'),
            'ultimo_teste_em' => $storedConfig['ultimo_teste_em'] ?? null,
            'observacao_erro' => $storedConfig['observacao_erro'] ?? null,
        ];
    }
}
