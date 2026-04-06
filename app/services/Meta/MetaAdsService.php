<?php

class MetaAdsService
{
    private array $config;
    private string $baseUrl;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/meta.php';
        $this->baseUrl = 'https://graph.facebook.com/' . $this->config['graph_version'];
    }

    public function getLoginUrl(?string $state = null): string
    {
        $params = [
            'client_id' => $this->config['app_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => implode(',', $this->config['scopes']),
            'response_type' => 'code'
        ];

        if (!empty($state)) {
            $params['state'] = $state;
        }

        return 'https://www.facebook.com/' . $this->config['graph_version'] . '/dialog/oauth?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code): array
    {
        $url = $this->baseUrl . '/oauth/access_token?' . http_build_query([
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'redirect_uri' => $this->config['redirect_uri'],
            'code' => $code
        ]);

        return $this->request('GET', $url);
    }

    public function getLongLivedToken(string $shortToken): array
    {
        $url = $this->baseUrl . '/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'fb_exchange_token' => $shortToken
        ]);

        return $this->request('GET', $url);
    }

    public function getMe(string $accessToken): array
    {
        $url = $this->baseUrl . '/me?' . http_build_query([
            'fields' => 'id,name'
        ]);

        return $this->request('GET', $url, $accessToken);
    }

    public function getAdAccounts(string $accessToken): array
    {
        $url = $this->baseUrl . '/me/adaccounts?' . http_build_query([
            'fields' => 'id,account_id,name,currency,timezone_name,account_status,business{id,name}',
            'limit' => 200
        ]);

        return $this->requestAllPages($url, $accessToken);
    }

    public function getCampaigns(string $metaAccountId, string $accessToken): array
    {
        $accountId = $this->normalizeAccountId($metaAccountId);

        $url = $this->baseUrl . '/' . $accountId . '/campaigns?' . http_build_query([
            'fields' => 'id,name,status,effective_status,objective',
            'limit' => 500
        ]);

        return $this->requestAllPages($url, $accessToken);
    }

    public function getAdsets(string $metaAccountId, string $accessToken): array
    {
        $accountId = $this->normalizeAccountId($metaAccountId);

        $url = $this->baseUrl . '/' . $accountId . '/adsets?' . http_build_query([
            'fields' => 'id,campaign_id,name,status,effective_status,optimization_goal,billing_event',
            'limit' => 500
        ]);

        return $this->requestAllPages($url, $accessToken);
    }

    public function getAds(string $metaAccountId, string $accessToken): array
    {
        $accountId = $this->normalizeAccountId($metaAccountId);

        $url = $this->baseUrl . '/' . $accountId . '/ads?' . http_build_query([
            'fields' => 'id,adset_id,name,status,effective_status',
            'limit' => 500
        ]);

        return $this->requestAllPages($url, $accessToken);
    }

    public function getInsights(
        string $metaAccountId,
        string $accessToken,
        string $level,
        string $since,
        string $until
    ): array {
        $accountId = $this->normalizeAccountId($metaAccountId);

        $url = $this->baseUrl . '/' . $accountId . '/insights?' . http_build_query([
            'level' => $level,
            'time_increment' => 1,
            'fields' => implode(',', [
                'account_id',
                'campaign_id',
                'adset_id',
                'ad_id',
                'campaign_name',
                'adset_name',
                'ad_name',
                'date_start',
                'date_stop',
                'spend',
                'impressions',
                'reach',
                'clicks',
                'inline_link_clicks',
                'ctr',
                'cpc',
                'cpm',
                'frequency',
                'actions',
                'cost_per_action_type',
                'purchase_roas',
                'action_values'
            ]),
            'time_range' => json_encode([
                'since' => $since,
                'until' => $until
            ]),
            'limit' => 500
        ]);

        return $this->requestAllPages($url, $accessToken);
    }

    public function getInsightsAggregate(
        string $metaAccountId,
        string $accessToken,
        string $level,
        string $since,
        string $until,
        array $filtering = []
    ): array {
        $accountId = $this->normalizeAccountId($metaAccountId);

        $params = [
            'level' => $level,
            'fields' => implode(',', [
                'spend',
                'impressions',
                'reach',
                'frequency',
            ]),
            'time_range' => json_encode([
                'since' => $since,
                'until' => $until
            ]),
            'limit' => 500
        ];

        if (!empty($filtering)) {
            $params['filtering'] = json_encode($filtering);
        }

        $url = $this->baseUrl . '/' . $accountId . '/insights?' . http_build_query($params);

        return $this->request('GET', $url, $accessToken);
    }

    private function normalizeAccountId(string $metaAccountId): string
    {
        return str_starts_with($metaAccountId, 'act_') ? $metaAccountId : 'act_' . $metaAccountId;
    }

    private function requestAllPages(string $url, ?string $accessToken = null): array
    {
        $allData = [];
        $firstResponse = null;
        $nextUrl = $url;
        $page = 0;

        while ($nextUrl) {
            $page++;
            $response = $this->request('GET', $nextUrl, $accessToken);

            if ($firstResponse === null) {
                $firstResponse = $response;
            }

            foreach (($response['data'] ?? []) as $item) {
                $allData[] = $item;
            }

            $nextUrl = $response['paging']['next'] ?? null;

            if ($page >= 100) {
                throw new Exception('Paginação Meta excedeu o limite de segurança (100 páginas).');
            }
        }

        if ($firstResponse === null) {
            return ['data' => []];
        }

        $firstResponse['data'] = $allData;
        unset($firstResponse['paging']);

        return $firstResponse;
    }

    private function request(string $method, string $url, ?string $accessToken = null): array
    {
        $ch = curl_init();

        $headers = ['Accept: application/json'];

        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            curl_close($ch);
            throw new Exception('Erro cURL: ' . $curlError);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new Exception('Resposta inválida da Meta API.');
        }

        if ($httpCode >= 400) {
            $message = $decoded['error']['message'] ?? 'Erro desconhecido na Meta API';
            $code = $decoded['error']['code'] ?? null;
            $subcode = $decoded['error']['error_subcode'] ?? null;

            throw new Exception(
                'Meta API HTTP ' . $httpCode .
                ($code ? ' | code ' . $code : '') .
                ($subcode ? ' | subcode ' . $subcode : '') .
                ' | ' . $message
            );
        }

        return $decoded;
    }

    public function extractActionValue(array $actions, array $keys): int
    {
        $total = 0;

        foreach ($actions as $action) {
            if (in_array($action['action_type'] ?? '', $keys, true)) {
                $total += (int) round((float) ($action['value'] ?? 0));
            }
        }

        return $total;
    }

    public function extractActionFloat(array $actions, array $keys): float
    {
        $total = 0.0;

        foreach ($actions as $action) {
            if (in_array($action['action_type'] ?? '', $keys, true)) {
                $total += (float) ($action['value'] ?? 0);
            }
        }

        return $total;
    }

    public function extractCostPerAction(array $items, array $keys): float
    {
        foreach ($items as $item) {
            if (in_array($item['action_type'] ?? '', $keys, true)) {
                return (float) ($item['value'] ?? 0);
            }
        }

        return 0.0;
    }

    public function extractRoasValue(array $purchaseRoas): float
    {
        if (!empty($purchaseRoas[0]['value'])) {
            return (float) $purchaseRoas[0]['value'];
        }

        return 0.0;
    }
}
