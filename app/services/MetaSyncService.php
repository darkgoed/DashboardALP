<?php

require_once __DIR__ . '/MetaAdsService.php';

class MetaSyncService
{
    private PDO $conn;
    private MetaAdsService $meta;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        $this->meta = new MetaAdsService();
    }

    public function syncEstrutura(?int $empresaId = null, ?int $contaId = null, array $options = []): array
    {
        return $this->processarContas(
            $empresaId,
            $contaId,
            function (array $conta, string $token, array $context): void {
                $empresaIdConta = (int) $conta['empresa_id'];
                $contaIdAtual   = (int) $conta['id'];
                $metaAccountId  = (string) $conta['meta_account_id'];

                $campanhasMap = $this->syncCampaigns($empresaIdConta, $contaIdAtual, $metaAccountId, $token);
                $conjuntosMap = $this->syncAdsets($empresaIdConta, $contaIdAtual, $metaAccountId, $campanhasMap, $token);
                $this->syncAds($empresaIdConta, $metaAccountId, $conjuntosMap, $token);

                $this->updateContaSyncTimestamps($empresaIdConta, $contaIdAtual, true, false, false);
            },
            [
                'tipo_log' => 'sync_estrutura',
                'sync_job_id' => $options['sync_job_id'] ?? null,
            ]
        );
    }

    public function syncInsights(
        ?int $empresaId = null,
        ?int $contaId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        array $options = []
    ): array {
        $dataInicio = $dataInicio ?: date('Y-m-d', strtotime('-1 day'));
        $dataFim    = $dataFim ?: date('Y-m-d');

        return $this->processarContas(
            $empresaId,
            $contaId,
            function (array $conta, string $token, array $context): void {
                $empresaIdConta = (int) $conta['empresa_id'];
                $contaIdAtual   = (int) $conta['id'];

                $maps = $this->buildRelationshipMaps($empresaIdConta, $contaIdAtual);

                $this->syncInsightsCampaign(
                    $conta,
                    $maps['campanhas'],
                    $token,
                    $context['data_inicio'],
                    $context['data_fim']
                );

                $this->syncInsightsAdset(
                    $conta,
                    $maps['campanhas'],
                    $maps['conjuntos'],
                    $token,
                    $context['data_inicio'],
                    $context['data_fim']
                );

                $this->syncInsightsAd(
                    $conta,
                    $maps['campanhas'],
                    $maps['conjuntos'],
                    $maps['anuncios'],
                    $token,
                    $context['data_inicio'],
                    $context['data_fim']
                );

                $this->updateContaSyncTimestamps($empresaIdConta, $contaIdAtual, false, true, false);
            },
            [
                'tipo_log' => 'sync_insights',
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'sync_job_id' => $options['sync_job_id'] ?? null,
            ]
        );
    }

    public function syncReconciliacao(
        ?int $empresaId = null,
        ?int $contaId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        array $options = []
    ): array {
        $dataInicio = $dataInicio ?: date('Y-m-d', strtotime('-7 days'));
        $dataFim    = $dataFim ?: date('Y-m-d');

        return $this->processarContas(
            $empresaId,
            $contaId,
            function (array $conta, string $token, array $context): void {
                $empresaIdConta = (int) $conta['empresa_id'];
                $contaIdAtual   = (int) $conta['id'];

                // Reconciliacao robusta:
                // 1. garante estrutura mínima local
                $campanhasMap = $this->syncCampaigns($empresaIdConta, $contaIdAtual, (string) $conta['meta_account_id'], $token);
                $conjuntosMap = $this->syncAdsets($empresaIdConta, $contaIdAtual, (string) $conta['meta_account_id'], $campanhasMap, $token);
                $anunciosMap  = $this->syncAds($empresaIdConta, (string) $conta['meta_account_id'], $conjuntosMap, $token);

                // 2. reprocessa janela alvo
                $this->syncInsightsCampaign($conta, $campanhasMap, $token, $context['data_inicio'], $context['data_fim']);
                $this->syncInsightsAdset($conta, $campanhasMap, $conjuntosMap, $token, $context['data_inicio'], $context['data_fim']);
                $this->syncInsightsAd($conta, $campanhasMap, $conjuntosMap, $anunciosMap, $token, $context['data_inicio'], $context['data_fim']);

                $this->updateContaSyncTimestamps($empresaIdConta, $contaIdAtual, false, false, true);
            },
            [
                'tipo_log' => 'sync_reconciliacao',
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'sync_job_id' => $options['sync_job_id'] ?? null,
            ]
        );
    }

    public function syncCompleto(
        ?int $empresaId = null,
        ?int $contaId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        array $options = []
    ): array {
        $resultadoEstrutura = $this->syncEstrutura($empresaId, $contaId, $options);
        $resultadoInsights  = $this->syncInsights($empresaId, $contaId, $dataInicio, $dataFim, $options);

        return [
            'estrutura' => $resultadoEstrutura,
            'insights'  => $resultadoInsights,
        ];
    }

    public function syncManutencao(array $options = []): array
    {
        // Aqui você pode futuramente:
        // - destravar jobs presos
        // - limpar locks expirados
        // - marcar jobs zumbis como erro
        // - limpar logs antigos
        return [
            'status' => 'success',
            'message' => 'Rotina de manutenção executada.'
        ];
    }

    private function processarContas(
        ?int $empresaId,
        ?int $contaId,
        callable $executor,
        array $context = []
    ): array {
        $contas = $this->getConnectedAccounts($empresaId, $contaId);

        if (empty($contas)) {
            throw new Exception('Nenhuma conta Meta ativa encontrada para sincronizar.');
        }

        $resultados = [];

        foreach ($contas as $conta) {
            $empresaIdConta = (int) $conta['empresa_id'];
            $clienteId      = (int) $conta['cliente_id'];
            $contaIdAtual   = (int) $conta['id'];
            $metaAccountId  = (string) $conta['meta_account_id'];

            try {
                $token = $this->getLatestTokenByConta($empresaIdConta, $clienteId);

                if (!$token) {
                    throw new Exception('Nenhum token Meta válido encontrado para a conta.');
                }

                $this->markContaSyncStatus($empresaIdConta, $contaIdAtual, 'processando', null);

                $this->conn->beginTransaction();

                $executor($conta, $token, $context);

                $this->markContaSyncStatus($empresaIdConta, $contaIdAtual, 'ok', null);

                $this->log(
                    $empresaIdConta,
                    $clienteId,
                    $contaIdAtual,
                    $context['tipo_log'] ?? 'sync_generico',
                    'success',
                    'Sincronização concluída com sucesso para a conta ' . $metaAccountId,
                    null,
                    $context['sync_job_id'] ?? null
                );

                $this->conn->commit();

                $resultados[] = [
                    'empresa_id' => $empresaIdConta,
                    'cliente_id' => $clienteId,
                    'conta_id'   => $contaIdAtual,
                    'conta'      => $metaAccountId,
                    'status'     => 'success',
                ];
            } catch (Throwable $e) {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }

                $this->markContaSyncStatus($empresaIdConta, $contaIdAtual, 'erro', $e->getMessage());

                $this->log(
                    $empresaIdConta,
                    $clienteId,
                    $contaIdAtual,
                    $context['tipo_log'] ?? 'sync_generico',
                    'error',
                    $e->getMessage(),
                    null,
                    $context['sync_job_id'] ?? null
                );

                $resultados[] = [
                    'empresa_id' => $empresaIdConta,
                    'cliente_id' => $clienteId,
                    'conta_id'   => $contaIdAtual,
                    'conta'      => $metaAccountId,
                    'status'     => 'error',
                    'message'    => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    private function buildRelationshipMaps(int $empresaId, int $contaId): array
    {
        return [
            'campanhas' => $this->getCampanhasMap($empresaId, $contaId),
            'conjuntos' => $this->getConjuntosMap($empresaId, $contaId),
            'anuncios'  => $this->getAnunciosMap($empresaId, $contaId),
        ];
    }

    private function getCampanhasMap(int $empresaId, int $contaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, meta_campaign_id
            FROM campanhas
            WHERE empresa_id = :empresa_id
              AND conta_id = :conta_id
              AND meta_campaign_id IS NOT NULL
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['meta_campaign_id']] = (int) $row['id'];
        }

        return $map;
    }

    private function getConjuntosMap(int $empresaId, int $contaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT cj.id, cj.meta_adset_id
            FROM conjuntos cj
            INNER JOIN campanhas cp
                ON cp.empresa_id = cj.empresa_id
               AND cp.id = cj.campanha_id
            WHERE cj.empresa_id = :empresa_id
              AND cp.conta_id = :conta_id
              AND cj.meta_adset_id IS NOT NULL
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['meta_adset_id']] = (int) $row['id'];
        }

        return $map;
    }

    private function getAnunciosMap(int $empresaId, int $contaId): array
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.meta_ad_id
            FROM anuncios a
            INNER JOIN conjuntos cj
                ON cj.empresa_id = a.empresa_id
               AND cj.id = a.conjunto_id
            INNER JOIN campanhas cp
                ON cp.empresa_id = cj.empresa_id
               AND cp.id = cj.campanha_id
            WHERE a.empresa_id = :empresa_id
              AND cp.conta_id = :conta_id
              AND a.meta_ad_id IS NOT NULL
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['meta_ad_id']] = (int) $row['id'];
        }

        return $map;
    }

    private function getLatestTokenByConta(int $empresaId, int $clienteId): ?string
    {
        $stmt = $this->conn->prepare("
            SELECT access_token
            FROM meta_tokens
            WHERE empresa_id = :empresa_id
              AND cliente_id = :cliente_id
              AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['access_token'] ?? null;
    }

    private function getConnectedAccounts(?int $empresaId = null, ?int $contaId = null): array
    {
        $sql = "
            SELECT *
            FROM contas_ads
            WHERE meta_account_id IS NOT NULL
              AND ativo = 1
        ";

        $params = [];

        if ($empresaId !== null) {
            $sql .= " AND empresa_id = :empresa_id";
            $params[':empresa_id'] = $empresaId;
        }

        if ($contaId !== null) {
            $sql .= " AND id = :conta_id";
            $params[':conta_id'] = $contaId;
        }

        $sql .= " ORDER BY id ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function syncCampaigns(int $empresaId, int $contaId, string $metaAccountId, string $token): array
    {
        $response = $this->meta->getCampaigns($metaAccountId, $token);
        $map = [];

        foreach (($response['data'] ?? []) as $campaign) {
            $metaCampaignId = $campaign['id'] ?? null;

            if (!$metaCampaignId) {
                continue;
            }

            $sql = "
                INSERT INTO campanhas
                    (empresa_id, conta_id, meta_campaign_id, nome, status, effective_status, objetivo)
                VALUES
                    (:empresa_id, :conta_id, :meta_campaign_id, :nome, :status, :effective_status, :objetivo)
                ON DUPLICATE KEY UPDATE
                    conta_id = VALUES(conta_id),
                    nome = VALUES(nome),
                    status = VALUES(status),
                    effective_status = VALUES(effective_status),
                    objetivo = VALUES(objetivo)
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':conta_id' => $contaId,
                ':meta_campaign_id' => $metaCampaignId,
                ':nome' => $campaign['name'] ?? 'Campanha sem nome',
                ':status' => $campaign['status'] ?? null,
                ':effective_status' => $campaign['effective_status'] ?? null,
                ':objetivo' => $campaign['objective'] ?? null
            ]);

            $idStmt = $this->conn->prepare("
                SELECT id
                FROM campanhas
                WHERE empresa_id = :empresa_id
                  AND meta_campaign_id = :meta_campaign_id
                LIMIT 1
            ");
            $idStmt->execute([
                ':empresa_id' => $empresaId,
                ':meta_campaign_id' => $metaCampaignId
            ]);

            $row = $idStmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $map[$metaCampaignId] = (int) $row['id'];
            }
        }

        return $map;
    }

    private function syncAdsets(int $empresaId, int $contaId, string $metaAccountId, array $campanhasMap, string $token): array
    {
        $response = $this->meta->getAdsets($metaAccountId, $token);
        $map = [];

        foreach (($response['data'] ?? []) as $adset) {
            $metaAdsetId = $adset['id'] ?? null;
            $metaCampaignId = $adset['campaign_id'] ?? null;

            if (!$metaAdsetId || !$metaCampaignId || empty($campanhasMap[$metaCampaignId])) {
                continue;
            }

            $sql = "
                INSERT INTO conjuntos
                    (empresa_id, campanha_id, meta_adset_id, nome, status, effective_status, optimization_goal, billing_event)
                VALUES
                    (:empresa_id, :campanha_id, :meta_adset_id, :nome, :status, :effective_status, :optimization_goal, :billing_event)
                ON DUPLICATE KEY UPDATE
                    campanha_id = VALUES(campanha_id),
                    nome = VALUES(nome),
                    status = VALUES(status),
                    effective_status = VALUES(effective_status),
                    optimization_goal = VALUES(optimization_goal),
                    billing_event = VALUES(billing_event)
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':campanha_id' => $campanhasMap[$metaCampaignId],
                ':meta_adset_id' => $metaAdsetId,
                ':nome' => $adset['name'] ?? 'Conjunto sem nome',
                ':status' => $adset['status'] ?? null,
                ':effective_status' => $adset['effective_status'] ?? null,
                ':optimization_goal' => $adset['optimization_goal'] ?? null,
                ':billing_event' => $adset['billing_event'] ?? null
            ]);

            $idStmt = $this->conn->prepare("
                SELECT id
                FROM conjuntos
                WHERE empresa_id = :empresa_id
                  AND meta_adset_id = :meta_adset_id
                LIMIT 1
            ");
            $idStmt->execute([
                ':empresa_id' => $empresaId,
                ':meta_adset_id' => $metaAdsetId
            ]);

            $row = $idStmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $map[$metaAdsetId] = (int) $row['id'];
            }
        }

        return $map;
    }

    private function syncAds(int $empresaId, string $metaAccountId, array $conjuntosMap, string $token): array
    {
        $response = $this->meta->getAds($metaAccountId, $token);
        $map = [];

        foreach (($response['data'] ?? []) as $ad) {
            $metaAdId = $ad['id'] ?? null;
            $metaAdsetId = $ad['adset_id'] ?? null;

            if (!$metaAdId || !$metaAdsetId || empty($conjuntosMap[$metaAdsetId])) {
                continue;
            }

            $sql = "
                INSERT INTO anuncios
                    (empresa_id, conjunto_id, meta_ad_id, nome, status, effective_status)
                VALUES
                    (:empresa_id, :conjunto_id, :meta_ad_id, :nome, :status, :effective_status)
                ON DUPLICATE KEY UPDATE
                    conjunto_id = VALUES(conjunto_id),
                    nome = VALUES(nome),
                    status = VALUES(status),
                    effective_status = VALUES(effective_status)
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':conjunto_id' => $conjuntosMap[$metaAdsetId],
                ':meta_ad_id' => $metaAdId,
                ':nome' => $ad['name'] ?? 'Anúncio sem nome',
                ':status' => $ad['status'] ?? null,
                ':effective_status' => $ad['effective_status'] ?? null
            ]);

            $idStmt = $this->conn->prepare("
                SELECT id
                FROM anuncios
                WHERE empresa_id = :empresa_id
                  AND meta_ad_id = :meta_ad_id
                LIMIT 1
            ");
            $idStmt->execute([
                ':empresa_id' => $empresaId,
                ':meta_ad_id' => $metaAdId
            ]);

            $row = $idStmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $map[$metaAdId] = (int) $row['id'];
            }
        }

        return $map;
    }

    private function syncInsightsCampaign(array $conta, array $campanhasMap, string $token, string $since, string $until): void
    {
        $response = $this->meta->getInsights($conta['meta_account_id'], $token, 'campaign', $since, $until);

        foreach (($response['data'] ?? []) as $row) {
            $metaCampaignId = $row['campaign_id'] ?? null;

            if (!$metaCampaignId || empty($campanhasMap[$metaCampaignId])) {
                continue;
            }

            $this->upsertInsight($this->buildInsightPayload($conta, $row, [
                'campanha_id' => $campanhasMap[$metaCampaignId],
                'conjunto_id' => null,
                'anuncio_id' => null,
                'meta_campaign_id' => $metaCampaignId,
                'meta_adset_id' => null,
                'meta_ad_id' => null,
                'nivel' => 'campaign',
            ]));
        }
    }

    private function syncInsightsAdset(array $conta, array $campanhasMap, array $conjuntosMap, string $token, string $since, string $until): void
    {
        $response = $this->meta->getInsights($conta['meta_account_id'], $token, 'adset', $since, $until);

        foreach (($response['data'] ?? []) as $row) {
            $metaCampaignId = $row['campaign_id'] ?? null;
            $metaAdsetId = $row['adset_id'] ?? null;

            if (!$metaCampaignId || !$metaAdsetId) {
                continue;
            }

            if (empty($campanhasMap[$metaCampaignId]) || empty($conjuntosMap[$metaAdsetId])) {
                continue;
            }

            $this->upsertInsight($this->buildInsightPayload($conta, $row, [
                'campanha_id' => $campanhasMap[$metaCampaignId],
                'conjunto_id' => $conjuntosMap[$metaAdsetId],
                'anuncio_id' => null,
                'meta_campaign_id' => $metaCampaignId,
                'meta_adset_id' => $metaAdsetId,
                'meta_ad_id' => null,
                'nivel' => 'adset',
            ]));
        }
    }

    private function syncInsightsAd(array $conta, array $campanhasMap, array $conjuntosMap, array $anunciosMap, string $token, string $since, string $until): void
    {
        $response = $this->meta->getInsights($conta['meta_account_id'], $token, 'ad', $since, $until);

        foreach (($response['data'] ?? []) as $row) {
            $metaCampaignId = $row['campaign_id'] ?? null;
            $metaAdsetId = $row['adset_id'] ?? null;
            $metaAdId = $row['ad_id'] ?? null;

            if (!$metaCampaignId || !$metaAdsetId || !$metaAdId) {
                continue;
            }

            if (
                empty($campanhasMap[$metaCampaignId]) ||
                empty($conjuntosMap[$metaAdsetId]) ||
                empty($anunciosMap[$metaAdId])
            ) {
                continue;
            }

            $this->upsertInsight($this->buildInsightPayload($conta, $row, [
                'campanha_id' => $campanhasMap[$metaCampaignId],
                'conjunto_id' => $conjuntosMap[$metaAdsetId],
                'anuncio_id' => $anunciosMap[$metaAdId],
                'meta_campaign_id' => $metaCampaignId,
                'meta_adset_id' => $metaAdsetId,
                'meta_ad_id' => $metaAdId,
                'nivel' => 'ad',
            ]));
        }
    }

    private function buildInsightPayload(array $conta, array $row, array $ids): array
    {
        return [
            'empresa_id' => (int) $conta['empresa_id'],
            'cliente_id' => (int) $conta['cliente_id'],
            'conta_id' => (int) $conta['id'],
            'campanha_id' => $ids['campanha_id'],
            'conjunto_id' => $ids['conjunto_id'],
            'anuncio_id' => $ids['anuncio_id'],
            'meta_account_id' => $row['account_id'] ?? $conta['meta_account_id'],
            'meta_campaign_id' => $ids['meta_campaign_id'],
            'meta_adset_id' => $ids['meta_adset_id'],
            'meta_ad_id' => $ids['meta_ad_id'],
            'nivel' => $ids['nivel'],
            'data' => $row['date_start'],
            'meta_date_start' => $row['date_start'],
            'meta_date_stop' => $row['date_stop'],
            'gasto' => (float) ($row['spend'] ?? 0),
            'impressoes' => (int) ($row['impressions'] ?? 0),
            'alcance' => (int) ($row['reach'] ?? 0),
            'frequencia' => (float) ($row['frequency'] ?? 0),
            'cliques' => (int) ($row['clicks'] ?? 0),
            'cliques_link' => (int) ($row['inline_link_clicks'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'cpc' => (float) ($row['cpc'] ?? 0),
            'cpm' => (float) ($row['cpm'] ?? 0),
            'leads' => $this->meta->extractActionValue(
                $row['actions'] ?? [],
                ['lead', 'onsite_conversion.lead_grouped', 'offsite_conversion.fb_pixel_lead']
            ),
            'conversas_whatsapp' => $this->meta->extractActionValue(
                $row['actions'] ?? [],
                ['onsite_conversion.messaging_conversation_started_7d', 'omni_initiated_messaging_conversation']
            ),
            'conversoes' => $this->meta->extractActionValue(
                $row['actions'] ?? [],
                ['purchase', 'offsite_conversion.fb_pixel_purchase', 'omni_purchase']
            ),
            'compras' => $this->meta->extractActionValue(
                $row['actions'] ?? [],
                ['purchase', 'offsite_conversion.fb_pixel_purchase', 'omni_purchase']
            ),
            'receita' => $this->meta->extractActionFloat(
                $row['action_values'] ?? [],
                ['purchase', 'offsite_conversion.fb_pixel_purchase', 'omni_purchase']
            ),
            'roas' => $this->meta->extractRoasValue($row['purchase_roas'] ?? []),
        ];
    }

    private function upsertInsight(array $data): void
    {
        $sql = "
            INSERT INTO insights_diarios (
                empresa_id, cliente_id, conta_id, campanha_id, conjunto_id, anuncio_id,
                meta_account_id, meta_campaign_id, meta_adset_id, meta_ad_id,
                nivel, data, meta_date_start, meta_date_stop,
                gasto, impressoes, alcance, frequencia, cliques, cliques_link,
                ctr, cpc, cpm, leads, conversas_whatsapp, conversoes, compras, receita, roas
            ) VALUES (
                :empresa_id, :cliente_id, :conta_id, :campanha_id, :conjunto_id, :anuncio_id,
                :meta_account_id, :meta_campaign_id, :meta_adset_id, :meta_ad_id,
                :nivel, :data, :meta_date_start, :meta_date_stop,
                :gasto, :impressoes, :alcance, :frequencia, :cliques, :cliques_link,
                :ctr, :cpc, :cpm, :leads, :conversas_whatsapp, :conversoes, :compras, :receita, :roas
            )
            ON DUPLICATE KEY UPDATE
                cliente_id = VALUES(cliente_id),
                conta_id = VALUES(conta_id),
                campanha_id = VALUES(campanha_id),
                conjunto_id = VALUES(conjunto_id),
                anuncio_id = VALUES(anuncio_id),
                meta_date_start = VALUES(meta_date_start),
                meta_date_stop = VALUES(meta_date_stop),
                gasto = VALUES(gasto),
                impressoes = VALUES(impressoes),
                alcance = VALUES(alcance),
                frequencia = VALUES(frequencia),
                cliques = VALUES(cliques),
                cliques_link = VALUES(cliques_link),
                ctr = VALUES(ctr),
                cpc = VALUES(cpc),
                cpm = VALUES(cpm),
                leads = VALUES(leads),
                conversas_whatsapp = VALUES(conversas_whatsapp),
                conversoes = VALUES(conversoes),
                compras = VALUES(compras),
                receita = VALUES(receita),
                roas = VALUES(roas)
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($data);
    }

    private function updateContaSyncTimestamps(
        int $empresaId,
        int $contaId,
        bool $estrutura = false,
        bool $insights = false,
        bool $reconciliacao = false
    ): void {
        $sets = [];
        $params = [
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId
        ];

        if ($estrutura) {
            $sets[] = "ultima_sync_estrutura_em = NOW()";
        }

        if ($insights) {
            $sets[] = "ultima_sync_insights_em = NOW()";
        }

        if ($reconciliacao) {
            $sets[] = "ultima_sync_reconciliacao_em = NOW()";
        }

        if (empty($sets)) {
            return;
        }

        $sql = "
            UPDATE contas_ads
            SET " . implode(', ', $sets) . "
            WHERE empresa_id = :empresa_id
              AND id = :conta_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
    }

    private function markContaSyncStatus(int $empresaId, int $contaId, string $status, ?string $erro): void
    {
        $stmt = $this->conn->prepare("
            UPDATE contas_ads
            SET status_sync = :status_sync,
                ultimo_erro_sync = :ultimo_erro_sync
            WHERE empresa_id = :empresa_id
              AND id = :conta_id
        ");

        $stmt->execute([
            ':status_sync' => $status,
            ':ultimo_erro_sync' => $erro,
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId
        ]);
    }

    private function log(
        int $empresaId,
        ?int $clienteId,
        ?int $contaId,
        string $tipo,
        string $status,
        string $mensagem,
        ?string $detalhes = null,
        ?int $syncJobId = null,
        ?string $nivel = null,
        ?string $referenciaMetaId = null
    ): void {
        $stmt = $this->conn->prepare("
            INSERT INTO sync_logs (
                empresa_id, cliente_id, conta_id,
                tipo, status, mensagem, detalhes,
                sync_job_id, nivel, referencia_meta_id
            )
            VALUES (
                :empresa_id, :cliente_id, :conta_id,
                :tipo, :status, :mensagem, :detalhes,
                :sync_job_id, :nivel, :referencia_meta_id
            )
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':conta_id' => $contaId,
            ':tipo' => $tipo,
            ':status' => $status,
            ':mensagem' => $mensagem,
            ':detalhes' => $detalhes,
            ':sync_job_id' => $syncJobId,
            ':nivel' => $nivel,
            ':referencia_meta_id' => $referenciaMetaId
        ]);
    }
}