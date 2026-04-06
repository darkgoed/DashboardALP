<?php

class DashboardMetaSummaryService
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function loadMetricConfig(?int $contaId): array
    {
        if (empty($contaId)) {
            return [];
        }

        $sql = "
            SELECT mc.config_json
            FROM metricas_config mc
            INNER JOIN contas_ads ca
                ON ca.cliente_id = mc.cliente_id
               AND ca.empresa_id = mc.empresa_id
            WHERE ca.id = :conta_id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':conta_id' => $contaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['config_json'])) {
            return [];
        }

        $json = json_decode($row['config_json'], true);

        return is_array($json) ? $json : [];
    }

    public function loadMetaPeriodSummary(
        int $empresaId,
        ?int $contaId,
        ?int $campanhaId,
        string $inicio,
        string $fim,
        ?string $campanhaStatus = null
    ): array {
        if (empty($contaId)) {
            return [];
        }

        $stmtConta = $this->conn->prepare("
            SELECT id, cliente_id, meta_account_id
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND id = :conta_id
            LIMIT 1
        ");
        $stmtConta->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);

        $conta = $stmtConta->fetch(PDO::FETCH_ASSOC);
        if (!$conta || empty($conta['meta_account_id']) || empty($conta['cliente_id'])) {
            return [];
        }

        $stmtToken = $this->conn->prepare("
            SELECT access_token
            FROM meta_tokens
            WHERE empresa_id = :empresa_id
              AND cliente_id = :cliente_id
              AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtToken->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => (int) $conta['cliente_id'],
        ]);

        $token = (string) ($stmtToken->fetchColumn() ?: '');
        if ($token === '') {
            return [];
        }

        $campaignSql = "
            SELECT meta_campaign_id
            FROM campanhas
            WHERE empresa_id = :empresa_id
              AND conta_id = :conta_id
              AND meta_campaign_id IS NOT NULL
        ";
        $campaignParams = [
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ];

        if (!empty($campanhaId)) {
            $campaignSql .= " AND id = :campanha_id";
            $campaignParams[':campanha_id'] = $campanhaId;
        }

        if (!empty($campanhaStatus)) {
            $campaignSql .= " AND UPPER(COALESCE(NULLIF(effective_status, ''), NULLIF(status, ''))) = :campanha_status";
            $campaignParams[':campanha_status'] = strtoupper((string) $campanhaStatus);
        }

        $stmtCampaigns = $this->conn->prepare($campaignSql);
        $stmtCampaigns->execute($campaignParams);
        $metaCampaignIds = array_values(array_filter(array_map('strval', $stmtCampaigns->fetchAll(PDO::FETCH_COLUMN))));

        if (($campanhaId || $campanhaStatus) && empty($metaCampaignIds)) {
            return [];
        }

        $filtering = [];
        if (!empty($metaCampaignIds)) {
            $filtering[] = [
                'field' => 'campaign.id',
                'operator' => 'IN',
                'value' => $metaCampaignIds,
            ];
        }

        try {
            $metaAds = new MetaAdsService();
            $response = $metaAds->getInsightsAggregate(
                (string) $conta['meta_account_id'],
                $token,
                'account',
                $inicio,
                $fim,
                $filtering
            );
        } catch (Throwable $e) {
            return [];
        }

        $row = $response['data'][0] ?? null;
        if (!is_array($row)) {
            return [];
        }

        return [
            'alcance' => (int) ($row['reach'] ?? 0),
            'impressoes' => (int) ($row['impressions'] ?? 0),
            'frequencia' => (float) ($row['frequency'] ?? 0),
        ];
    }

    public function applyMetaSummary(array &$resumo, array $resumoMeta): void
    {
        if (empty($resumoMeta) || empty($resumoMeta['alcance'])) {
            return;
        }

        $resumo['alcance'] = (int) $resumoMeta['alcance'];

        if (!empty($resumoMeta['frequencia'])) {
            $resumo['frequencia'] = (float) $resumoMeta['frequencia'];
            return;
        }

        $impressoes = (int) ($resumo['impressoes'] ?? 0);
        $alcance = (int) ($resumo['alcance'] ?? 0);
        $resumo['frequencia'] = $alcance > 0 ? $impressoes / $alcance : 0;
    }
}
