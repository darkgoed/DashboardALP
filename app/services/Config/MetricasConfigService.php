<?php

class MetricasConfigService
{
    private PDO $conn;
    private int $empresaId;
    private Cliente $clienteModel;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->clienteModel = new Cliente($conn, $empresaId);
    }

    public function save(array $data): int
    {
        $clienteId = isset($data['cliente_id']) ? (int) $data['cliente_id'] : 0;

        if (($data['acao'] ?? '') !== 'salvar_metricas' || $clienteId <= 0) {
            throw new RuntimeException('Requisição inválida para salvar métricas.');
        }

        $clientes = $this->clienteModel->getAll();
        $metricasBase = $this->loadMetricasBase();
        $nomeCliente = $this->buscarNomeCliente($clientes, $clienteId);
        $perfilPost = $data['perfil'] ?? [];
        $metricasPost = $data['metricas'] ?? [];
        $jsonImportado = trim((string) ($data['json_importado'] ?? ''));

        $configFinal = [
            'empresa' => $nomeCliente,
            'perfil' => [
                'segmento' => trim((string) ($perfilPost['segmento'] ?? '')),
                'objetivo_principal' => trim((string) ($perfilPost['objetivo_principal'] ?? 'Leads')),
                'tipo_operacao' => trim((string) ($perfilPost['tipo_operacao'] ?? 'Meta Ads')),
                'observacoes' => trim((string) ($perfilPost['observacoes'] ?? '')),
            ],
            'categorias' => $metricasBase['categorias'] ?? [],
            'metricas' => [],
        ];

        if ($jsonImportado !== '') {
            $jsonDecodificado = json_decode($jsonImportado, true);

            if (is_array($jsonDecodificado)) {
                $configFinal = $jsonDecodificado;
                $configFinal['empresa'] = $nomeCliente;
                $configFinal['perfil'] = is_array($configFinal['perfil'] ?? null) ? $configFinal['perfil'] : [];
                $configFinal['perfil']['segmento'] = trim((string) ($configFinal['perfil']['segmento'] ?? ($perfilPost['segmento'] ?? '')));
                $configFinal['perfil']['objetivo_principal'] = trim((string) ($configFinal['perfil']['objetivo_principal'] ?? ($perfilPost['objetivo_principal'] ?? 'Leads')));
                $configFinal['perfil']['tipo_operacao'] = trim((string) ($configFinal['perfil']['tipo_operacao'] ?? ($perfilPost['tipo_operacao'] ?? 'Meta Ads')));
                $configFinal['perfil']['observacoes'] = trim((string) ($configFinal['perfil']['observacoes'] ?? ($perfilPost['observacoes'] ?? '')));

                if (!isset($configFinal['categorias']) || !is_array($configFinal['categorias'])) {
                    $configFinal['categorias'] = $metricasBase['categorias'] ?? [];
                }

                if (!isset($configFinal['metricas']) || !is_array($configFinal['metricas'])) {
                    $configFinal['metricas'] = [];
                }
            }
        }

        if (empty($configFinal['metricas'])) {
            foreach ($metricasPost as $chave => $metrica) {
                $configFinal['metricas'][$chave] = [
                    'label' => trim((string) ($metrica['label'] ?? '')),
                    'unit' => trim((string) ($metrica['unit'] ?? '')),
                    'categoria' => trim((string) ($metrica['categoria'] ?? '')),
                    'tipo_leitura' => trim((string) ($metrica['tipo_leitura'] ?? 'faixa_ideal')),
                    'peso' => $this->intOuZero($metrica['peso'] ?? 0),
                    'ativo' => $this->normalizarCheckbox($metrica['ativo'] ?? false),
                    'critico_min' => $this->floatOuZero($metrica['critico_min'] ?? 0),
                    'alerta_min' => $this->floatOuZero($metrica['alerta_min'] ?? 0),
                    'ideal_min' => $this->floatOuZero($metrica['ideal_min'] ?? 0),
                    'ideal_max' => $this->floatOuZero($metrica['ideal_max'] ?? 0),
                    'alerta_max' => $this->floatOuZero($metrica['alerta_max'] ?? 0),
                    'critico_max' => $this->floatOuZero($metrica['critico_max'] ?? 0),
                    'descricao' => trim((string) ($metrica['descricao'] ?? '')),
                ];
            }
        }

        if (empty($configFinal['categorias'])) {
            $configFinal['categorias'] = $this->extrairCategoriasDasMetricas($configFinal['metricas']);
        }

        $jsonFinal = json_encode($configFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $check = $this->conn->prepare("
            SELECT id
            FROM metricas_config
            WHERE empresa_id = :empresa_id
              AND cliente_id = :cliente_id
            LIMIT 1
        ");
        $check->execute([
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
        ]);

        $existe = $check->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            $stmt = $this->conn->prepare("
                UPDATE metricas_config
                SET config_json = :config_json, updated_at = NOW()
                WHERE empresa_id = :empresa_id
                  AND cliente_id = :cliente_id
            ");
        } else {
            $stmt = $this->conn->prepare("
                INSERT INTO metricas_config (empresa_id, cliente_id, nome_config, config_json, created_at, updated_at)
                VALUES (:empresa_id, :cliente_id, 'Padrao', :config_json, NOW(), NOW())
            ");
        }

        $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
            ':config_json' => $jsonFinal,
        ]);

        return $clienteId;
    }

    public function getPageData(int $clienteId, string $mensagem = ''): array
    {
        $clientes = $this->clienteModel->getAll();
        $metricasBase = $this->loadMetricasBase();
        $erroBase = $metricasBase['_erro'] ?? '';

        if ($erroBase !== '') {
            $metricasBase = [
                'perfil_padrao' => [
                    'segmento' => '',
                    'objetivo_principal' => 'Leads',
                    'tipo_operacao' => 'Meta Ads',
                    'observacoes' => '',
                ],
                'categorias' => [],
                'metricas' => [],
            ];
        }

        $configCliente = $this->loadConfigCliente($clienteId);
        $configAtual = $configCliente ?: $this->montarConfigPadraoParaCliente(
            $metricasBase,
            $this->buscarNomeCliente($clientes, $clienteId)
        );

        $perfilAtual = $configAtual['perfil'] ?? [];
        $metricas = $configAtual['metricas'] ?? [];
        $categoriasBase = $metricasBase['categorias'] ?? [];
        $categorias = $configAtual['categorias'] ?? [];

        if (empty($categorias) && !empty($metricas)) {
            $categorias = $this->extrairCategoriasDasMetricas($metricas, $categoriasBase);
        }

        $totalConfigurados = $this->countConfigurados();
        $totalAtivas = 0;
        $categoriasUsadas = [];

        foreach ($metricas as $metrica) {
            if (!empty($metrica['ativo'])) {
                $totalAtivas++;
            }

            $categoriaKey = $metrica['categoria'] ?? '';
            if ($categoriaKey !== '') {
                $categoriasUsadas[$categoriaKey] = true;
            }
        }

        return [
            'clientes' => $clientes,
            'cliente_id' => $clienteId,
            'mensagem' => $mensagem,
            'erro_base' => $erroBase,
            'metricas_base' => $metricasBase,
            'config_atual' => $configAtual,
            'config_padrao_cliente' => $this->montarConfigPadraoParaCliente(
                $metricasBase,
                $this->buscarNomeCliente($clientes, $clienteId)
            ),
            'perfil_atual' => $perfilAtual,
            'metricas' => $metricas,
            'categorias' => $categorias,
            'totais' => [
                'total_clientes' => count($clientes),
                'total_configurados' => $totalConfigurados,
                'total_metricas' => count($metricas),
                'total_ativas' => $totalAtivas,
                'total_categorias' => count($categoriasUsadas),
            ],
            'json_preview' => json_encode($configAtual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }

    private function loadMetricasBase(): array
    {
        $arquivo = BASE_PATH . '/app/config/metricas_base.json';

        if (!file_exists($arquivo)) {
            return ['_erro' => 'Arquivo metricas_base.json não encontrado em app/config.'];
        }

        $conteudo = file_get_contents($arquivo);
        $json = json_decode((string) $conteudo, true);

        if (!is_array($json)) {
            return ['_erro' => 'O arquivo metricas_base.json está inválido.'];
        }

        return $json;
    }

    private function buscarNomeCliente(array $clientes, int $clienteId): string
    {
        foreach ($clientes as $cliente) {
            if ((int) ($cliente['id'] ?? 0) === $clienteId) {
                return (string) ($cliente['nome'] ?? 'Empresa');
            }
        }

        return 'Empresa';
    }

    private function loadConfigCliente(int $clienteId): ?array
    {
        if ($clienteId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT config_json
            FROM metricas_config
            WHERE empresa_id = :empresa_id
              AND cliente_id = :cliente_id
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $this->empresaId,
            ':cliente_id' => $clienteId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['config_json'])) {
            return null;
        }

        $json = json_decode((string) $row['config_json'], true);

        return is_array($json) ? $json : null;
    }

    private function montarConfigPadraoParaCliente(array $base, string $nomeCliente): array
    {
        return [
            'empresa' => $nomeCliente,
            'perfil' => $base['perfil_padrao'] ?? [
                'segmento' => '',
                'objetivo_principal' => 'Leads',
                'tipo_operacao' => 'Meta Ads',
                'observacoes' => '',
            ],
            'categorias' => $base['categorias'] ?? [],
            'metricas' => $base['metricas'] ?? [],
        ];
    }

    private function extrairCategoriasDasMetricas(array $metricas, array $categoriasBase = []): array
    {
        $categorias = [];

        foreach ($metricas as $metrica) {
            $chaveCategoria = $metrica['categoria'] ?? '';
            if ($chaveCategoria === '') {
                continue;
            }

            if (isset($categoriasBase[$chaveCategoria])) {
                $categorias[$chaveCategoria] = $categoriasBase[$chaveCategoria];
            } else {
                $categorias[$chaveCategoria] = ucfirst(str_replace('_', ' ', $chaveCategoria));
            }
        }

        return $categorias;
    }

    private function countConfigurados(): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS total
            FROM metricas_config
            WHERE empresa_id = :empresa_id
        ");
        $stmt->execute([':empresa_id' => $this->empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0);
    }

    private function normalizarCheckbox($valor): bool
    {
        return $valor === '1' || $valor === 1 || $valor === true || $valor === 'on';
    }

    private function floatOuZero($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        return (float) str_replace(',', '.', (string) $valor);
    }

    private function intOuZero($valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        return (int) $valor;
    }
}
