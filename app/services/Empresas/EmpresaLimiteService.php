<?php

class EmpresaLimiteService
{
    private PDO $conn;
    private Empresa $empresaModel;
    private EmpresaLicencaService $licencaService;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->empresaModel = new Empresa($conn);
        $this->licencaService = new EmpresaLicencaService($conn);
    }

    public function getConsumo(int $empresaId): array
    {
        $empresa = $this->empresaModel->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $sqlUsuarios = "
            SELECT COUNT(*)
            FROM usuarios_empresas
            WHERE empresa_id = :empresa_id
              AND status = 'ativo'
        ";
        $stmtUsuarios = $this->conn->prepare($sqlUsuarios);
        $stmtUsuarios->execute(['empresa_id' => $empresaId]);
        $usuariosUsados = (int) $stmtUsuarios->fetchColumn();

        $sqlContasAds = "
            SELECT COUNT(*)
            FROM contas_ads
            WHERE empresa_id = :empresa_id
              AND ativo = 1
        ";
        $stmtContasAds = $this->conn->prepare($sqlContasAds);
        $stmtContasAds->execute(['empresa_id' => $empresaId]);
        $contasAdsUsadas = (int) $stmtContasAds->fetchColumn();

        $limiteUsuarios = (int) ($empresa['limite_usuarios'] ?? 0);
        $limiteContasAds = (int) ($empresa['limite_contas_ads'] ?? 0);

        return [
            'usuarios' => [
                'usados' => $usuariosUsados,
                'limite' => $limiteUsuarios,
                'disponivel' => max(0, $limiteUsuarios - $usuariosUsados),
                'atingido' => $usuariosUsados >= $limiteUsuarios,
            ],
            'contas_ads' => [
                'usadas' => $contasAdsUsadas,
                'limite' => $limiteContasAds,
                'disponivel' => max(0, $limiteContasAds - $contasAdsUsadas),
                'atingido' => $contasAdsUsadas >= $limiteContasAds,
            ],
        ];
    }

    public function validarNovoUsuario(int $empresaId): void
    {
        $empresa = $this->empresaModel->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) !== 1) {
            $podeAcessar = $this->licencaService->podeAcessar($empresaId);

            if (!$podeAcessar) {
                throw new RuntimeException('A empresa está bloqueada e não pode cadastrar novos usuários.');
            }
        }

        $consumo = $this->getConsumo($empresaId);

        if ($consumo['usuarios']['atingido']) {
            throw new RuntimeException('Limite de usuários atingido para esta empresa.');
        }
    }

    public function validarNovaContaAds(int $empresaId): void
    {
        $empresa = $this->empresaModel->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) !== 1) {
            $podeAcessar = $this->licencaService->podeAcessar($empresaId);

            if (!$podeAcessar) {
                throw new RuntimeException('A empresa está bloqueada e não pode cadastrar novas contas de anúncio.');
            }
        }

        $consumo = $this->getConsumo($empresaId);

        if ($consumo['contas_ads']['atingido']) {
            throw new RuntimeException('Limite de contas de anúncio atingido para esta empresa.');
        }
    }

    public function validarOperacaoBasica(int $empresaId): void
    {
        $empresa = $this->empresaModel->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) === 1) {
            return;
        }

        $podeAcessar = $this->licencaService->podeAcessar($empresaId);

        if (!$podeAcessar) {
            throw new RuntimeException('A empresa está bloqueada e não pode executar esta ação.');
        }
    }
}