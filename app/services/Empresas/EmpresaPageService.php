<?php

class EmpresaPageService
{
    private PDO $conn;
    private Empresa $empresaModel;
    private EmpresaAssinatura $assinaturaModel;
    private EmpresaLicencaService $licencaService;
    private EmpresaLimiteService $limiteService;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->empresaModel = new Empresa($conn);
        $this->assinaturaModel = new EmpresaAssinatura($conn);
        $this->licencaService = new EmpresaLicencaService($conn);
        $this->limiteService = new EmpresaLimiteService($conn);
    }

    public function getCreateData(): array
    {
        $planos = [];

        try {
            $planos = (new Plano($this->conn))->getAllAtivos();
        } catch (Throwable $e) {
            $planos = [];
        }

        $old = $_SESSION['old'] ?? [];
        $errors = $_SESSION['errors'] ?? [];
        unset($_SESSION['old'], $_SESSION['errors']);

        return [
            'planos' => $planos,
            'old' => $old,
            'errors' => $errors,
        ];
    }

    public function getEditData(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Empresa inválida.');
        }

        $conviteService = new ConviteEmpresaService($this->conn);
        $convitePendente = $conviteService->obterConvitePendenteDaEmpresa($id);

        $stmtUsuariosEmpresa = $this->conn->prepare("
            SELECT COUNT(*)
            FROM usuarios_empresas ue
            WHERE ue.empresa_id = :empresa_id
              AND ue.status = 'ativo'
        ");
        $stmtUsuariosEmpresa->execute([':empresa_id' => $id]);
        $empresaJaPossuiUsuario = ((int) $stmtUsuariosEmpresa->fetchColumn()) > 0;

        $flashConviteAdmin = $_SESSION['flash_convite_admin'] ?? null;
        if ($flashConviteAdmin && (int) ($flashConviteAdmin['empresa_id'] ?? 0) !== $id) {
            $flashConviteAdmin = null;
        }

        $empresa = $this->empresaModel->findById($id);
        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($id);
        $consumo = $this->resolveConsumo($empresa, $id);

        $old = $_SESSION['old'] ?? [];
        $errors = $_SESSION['errors'] ?? [];
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['old'], $_SESSION['errors'], $_SESSION['flash_success'], $_SESSION['flash_error']);

        $usuariosUsados = (int) ($consumo['usuarios']['usados'] ?? 0);
        $usuariosLimiteReal = (int) ($consumo['usuarios']['limite'] ?? 0);
        $contasUsadas = (int) ($consumo['contas_ads']['usadas'] ?? 0);
        $contasLimiteReal = (int) ($consumo['contas_ads']['limite'] ?? 0);

        return [
            'empresa' => $empresa,
            'assinatura' => $assinatura,
            'consumo' => $consumo,
            'old' => $old,
            'errors' => $errors,
            'flash_success' => $flashSuccess,
            'flash_error' => $flashError,
            'flash_convite_admin' => $flashConviteAdmin,
            'convite_pendente' => $convitePendente,
            'empresa_ja_possui_usuario' => $empresaJaPossuiUsuario,
            'usuarios_usados' => $usuariosUsados,
            'usuarios_limite_real' => $usuariosLimiteReal,
            'usuarios_percentual' => EmpresaPageHelper::percentualConsumo($usuariosUsados, $usuariosLimiteReal),
            'contas_usadas' => $contasUsadas,
            'contas_limite_real' => $contasLimiteReal,
            'contas_percentual' => EmpresaPageHelper::percentualConsumo($contasUsadas, $contasLimiteReal),
        ];
    }

    public function getViewData(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Empresa inválida.');
        }

        $empresa = $this->empresaModel->findById($id);
        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($id);

        try {
            $statusLicenca = $this->licencaService->calcularStatus($id);
        } catch (Throwable $e) {
            $statusLicenca = [
                'status_assinatura' => 'erro',
                'status_acesso' => 'bloqueada',
                'dias_restantes' => 0,
                'em_tolerancia' => false,
                'bloqueada' => true,
                'motivo' => 'Falha ao calcular licença.',
                'data_vencimento' => null,
                'data_limite_tolerancia' => null,
            ];
        }

        $consumo = $this->resolveConsumo($empresa, $id);

        $stmtUsuarios = $this->conn->prepare("
            SELECT
                u.id,
                u.nome,
                u.email,
                u.telefone,
                u.status AS usuario_status,
                u.ultimo_login_em,
                ue.perfil,
                ue.status AS vinculo_status,
                ue.is_principal,
                ue.criado_em
            FROM usuarios_empresas ue
            INNER JOIN usuarios u ON u.id = ue.usuario_id
            WHERE ue.empresa_id = :empresa_id
            ORDER BY ue.is_principal DESC, u.nome ASC
        ");
        $stmtUsuarios->execute(['empresa_id' => $id]);
        $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

        $stmtContas = $this->conn->prepare("
            SELECT
                id,
                nome,
                meta_account_id,
                business_name,
                moeda,
                timezone_name,
                status,
                ativo,
                status_sync,
                created_at,
                ultima_sync_estrutura_em,
                ultima_sync_insights_em,
                ultima_sync_reconciliacao_em
            FROM contas_ads
            WHERE empresa_id = :empresa_id
            ORDER BY ativo DESC, nome ASC
        ");
        $stmtContas->execute(['empresa_id' => $id]);
        $contasAds = $stmtContas->fetchAll(PDO::FETCH_ASSOC);

        $usuariosUsados = (int) ($consumo['usuarios']['usados'] ?? 0);
        $contasUsadas = (int) ($consumo['contas_ads']['usadas'] ?? 0);

        return [
            'empresa' => $empresa,
            'assinatura' => $assinatura,
            'status_licenca' => $statusLicenca,
            'consumo' => $consumo,
            'usuarios' => $usuarios,
            'contas_ads' => $contasAds,
            'usuarios_usados' => $usuariosUsados,
            'usuarios_percentual' => EmpresaPageHelper::percentualConsumo($usuariosUsados, (int) ($consumo['usuarios']['limite'] ?? 0)),
            'contas_usadas' => $contasUsadas,
            'contas_percentual' => EmpresaPageHelper::percentualConsumo($contasUsadas, (int) ($consumo['contas_ads']['limite'] ?? 0)),
        ];
    }

    public function getDeleteData(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Empresa inválida.');
        }

        $empresa = $this->empresaModel->findById($id);
        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) === 1) {
            throw new RuntimeException('A empresa root não pode ser excluída.');
        }

        return [
            'empresa' => $empresa,
            'pagina_atual' => 'empresas',
        ];
    }

    private function resolveConsumo(array $empresa, int $id): array
    {
        try {
            return $this->limiteService->getConsumo($id);
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
