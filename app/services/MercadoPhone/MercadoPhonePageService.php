<?php

class MercadoPhonePageService
{
    private PDO $conn;
    private int $empresaId;
    private Cliente $clienteModel;
    private ContaAds $contaModel;
    private MercadoPhoneService $mercadoPhoneService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->clienteModel = new Cliente($conn, $empresaId);
        $this->contaModel = new ContaAds($conn, $empresaId);
        $this->mercadoPhoneService = new MercadoPhoneService($conn);
    }

    public function saveConfigs(array $data): string
    {
        $ids = $data['integracao_id'] ?? [];
        $clientesPost = $data['cliente_id'] ?? [];
        $contasPost = $data['conta_id'] ?? [];
        $ativosPost = $data['mercado_phone_ativo'] ?? [];
        $dashboardPost = $data['exibir_dashboard'] ?? [];
        $relatoriosPost = $data['exibir_relatorios'] ?? [];
        $tokensPost = $data['api_token'] ?? [];

        $totalLinhas = max(
            count((array) $ids),
            count((array) $clientesPost),
            count((array) $contasPost),
            count((array) $tokensPost)
        );

        $payload = [];

        for ($index = 0; $index < $totalLinhas; $index++) {
            $payload[] = [
                'id' => (int) ($ids[$index] ?? 0),
                'cliente_id' => (int) ($clientesPost[$index] ?? 0),
                'conta_id' => (int) ($contasPost[$index] ?? 0),
                'ativo' => !empty($ativosPost[$index]),
                'exibir_dashboard' => !empty($dashboardPost[$index]),
                'exibir_relatorios' => !empty($relatoriosPost[$index]),
                'api_token' => trim((string) ($tokensPost[$index] ?? '')),
            ];
        }

        $resultado = $this->mercadoPhoneService->saveConfigs($this->empresaId, $payload);

        if (($resultado['salvos'] ?? 0) > 0) {
            return 'Configuracoes do Mercado Phone salvas com sucesso.';
        }

        return 'Nenhuma integracao valida foi enviada para salvar.';
    }

    public function getPageData(): array
    {
        $clientes = $this->clienteModel->getAll();
        $contas = $this->contaModel->getAll();
        $integracoes = $this->mercadoPhoneService->listConfigs($this->empresaId);

        $stmtJobs = $this->conn->prepare("
            SELECT
                sj.id,
                sj.tipo,
                sj.status,
                sj.origem,
                sj.criado_em,
                sj.finalizado_em,
                sj.mensagem,
                sj.conta_id,
                c.nome AS cliente_nome,
                ca.nome AS conta_nome,
                ca.meta_account_id
            FROM sync_jobs sj
            LEFT JOIN clientes c
                ON c.id = sj.cliente_id
               AND c.empresa_id = sj.empresa_id
            LEFT JOIN contas_ads ca
                ON ca.id = sj.conta_id
               AND ca.empresa_id = sj.empresa_id
            WHERE sj.empresa_id = :empresa_id
              AND sj.tipo = 'mercado_phone'
            ORDER BY sj.id DESC
            LIMIT 10
        ");
        $stmtJobs->execute([':empresa_id' => $this->empresaId]);
        $jobsMercadoPhone = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

        $contasPorCliente = [];
        foreach ($contas as $conta) {
            $clienteContaId = (int) ($conta['cliente_id'] ?? 0);
            if ($clienteContaId <= 0) {
                continue;
            }

            $contasPorCliente[$clienteContaId][] = [
                'id' => (int) ($conta['id'] ?? 0),
                'nome' => (string) ($conta['nome'] ?? ''),
                'meta_account_id' => (string) ($conta['meta_account_id'] ?? ''),
            ];
        }

        return [
            'integracoes' => $integracoes,
            'jobs_mercado_phone' => $jobsMercadoPhone,
            'clientes_json' => json_encode(array_map(static function (array $cliente): array {
                return [
                    'id' => (int) ($cliente['id'] ?? 0),
                    'nome' => (string) ($cliente['nome'] ?? ''),
                ];
            }, $clientes), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'integracoes_json' => json_encode(array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'cliente_id' => (int) ($row['cliente_id'] ?? 0),
                    'conta_id' => !empty($row['conta_id']) ? (int) $row['conta_id'] : 0,
                    'ativo' => !empty($row['ativo']),
                    'exibir_dashboard' => !empty($row['exibir_dashboard']),
                    'exibir_relatorios' => !empty($row['exibir_relatorios']),
                    'api_token' => (string) ($row['api_token'] ?? ''),
                ];
            }, $integracoes), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'contas_por_cliente_json' => json_encode($contasPorCliente, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
        ];
    }
}
