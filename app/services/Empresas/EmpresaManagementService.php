<?php

class EmpresaManagementService
{
    private Empresa $empresaModel;
    private EmpresaLicencaService $licencaService;
    private EmpresaLimiteService $limiteService;

    public function __construct(PDO $conn)
    {
        $this->empresaModel = new Empresa($conn);
        $this->licencaService = new EmpresaLicencaService($conn);
        $this->limiteService = new EmpresaLimiteService($conn);
    }

    public function getIndexData(array $filters): array
    {
        $busca = trim((string) ($filters['busca'] ?? ''));
        $statusFiltro = trim((string) ($filters['status'] ?? ''));
        $planoFiltro = trim((string) ($filters['plano'] ?? ''));

        $empresas = $this->empresaModel->getAllWithResumo();
        $linhas = [];

        foreach ($empresas as $empresa) {
            if (!$this->matchesBusca($empresa, $busca)) {
                continue;
            }

            if ($statusFiltro !== '' && ($empresa['status'] ?? '') !== $statusFiltro) {
                continue;
            }

            if ($planoFiltro !== '' && ($empresa['plano'] ?? '') !== $planoFiltro) {
                continue;
            }

            $linhas[] = [
                'empresa' => $empresa,
                'licenca' => $this->resolveLicenca((int) $empresa['id']),
                'consumo' => $this->resolveConsumo((int) $empresa['id'], $empresa),
            ];
        }

        $totais = [
            'total_empresas' => count($linhas),
            'total_ativas' => 0,
            'total_bloqueadas' => 0,
            'total_trial' => 0,
            'total_root' => 0,
        ];

        foreach ($linhas as $item) {
            $empresa = $item['empresa'];
            $licenca = $item['licenca'];

            if ((int) ($empresa['is_root'] ?? 0) === 1) {
                $totais['total_root']++;
            }

            if (($licenca['bloqueada'] ?? false) === true) {
                $totais['total_bloqueadas']++;
            } else {
                $totais['total_ativas']++;
            }

            if (($licenca['status_assinatura'] ?? '') === 'trial') {
                $totais['total_trial']++;
            }
        }

        return [
            'linhas' => $linhas,
            'totais' => $totais,
            'filters' => [
                'busca' => $busca,
                'status' => $statusFiltro,
                'plano' => $planoFiltro,
            ],
        ];
    }

    private function matchesBusca(array $empresa, string $busca): bool
    {
        if ($busca === '') {
            return true;
        }

        $textoBusca = mb_strtolower($busca);
        $campos = [
            (string) ($empresa['nome_fantasia'] ?? ''),
            (string) ($empresa['razao_social'] ?? ''),
            (string) ($empresa['documento'] ?? ''),
            (string) ($empresa['email'] ?? ''),
        ];

        foreach ($campos as $campo) {
            if (str_contains(mb_strtolower($campo), $textoBusca)) {
                return true;
            }
        }

        return false;
    }

    private function resolveLicenca(int $empresaId): array
    {
        try {
            return $this->licencaService->calcularStatus($empresaId);
        } catch (Throwable $e) {
            return [
                'status_assinatura' => 'erro',
                'status_acesso' => 'bloqueada',
                'dias_restantes' => 0,
                'em_tolerancia' => false,
                'bloqueada' => true,
                'motivo' => 'Falha ao calcular licença.',
                'data_vencimento' => null,
            ];
        }
    }

    private function resolveConsumo(int $empresaId, array $empresa): array
    {
        try {
            return $this->limiteService->getConsumo($empresaId);
        } catch (Throwable $e) {
            return [
                'usuarios' => [
                    'usados' => 0,
                    'limite' => (int) ($empresa['limite_usuarios'] ?? 0),
                    'disponivel' => 0,
                    'atingido' => false,
                ],
                'contas_ads' => [
                    'usadas' => 0,
                    'limite' => (int) ($empresa['limite_contas_ads'] ?? 0),
                    'disponivel' => 0,
                    'atingido' => false,
                ],
            ];
        }
    }
}
