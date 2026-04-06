<?php

class EmpresaAdminActionService
{
    private PDO $conn;
    private Empresa $empresaModel;
    private EmpresaAssinatura $assinaturaModel;
    private EmpresaLicencaService $licencaService;
    private ConviteEmpresaService $conviteService;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->empresaModel = new Empresa($conn);
        $this->assinaturaModel = new EmpresaAssinatura($conn);
        $this->licencaService = new EmpresaLicencaService($conn);
        $this->conviteService = new ConviteEmpresaService($conn);
    }

    public function reativarRenovar(int $empresaId): string
    {
        if ($empresaId <= 0) {
            throw new RuntimeException('Requisição inválida.');
        }

        $empresa = $this->empresaModel->findById($empresaId);
        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($empresaId);
        if (!$assinatura) {
            throw new RuntimeException('Assinatura da empresa não encontrada.');
        }

        $hoje = new DateTimeImmutable('now');
        $tipoCobranca = strtolower((string) ($assinatura['tipo_cobranca'] ?? 'mensal'));
        $baseTexto = $assinatura['assinatura_ate']
            ?? $assinatura['data_vencimento']
            ?? $assinatura['trial_ate']
            ?? null;

        try {
            $base = !empty($baseTexto) ? new DateTimeImmutable((string) $baseTexto) : $hoje;
        } catch (Throwable $e) {
            $base = $hoje;
        }

        if ($base < $hoje) {
            $base = $hoje;
        }

        $novoFim = match ($tipoCobranca) {
            'trial' => $base->modify('+7 days'),
            'mensal' => $base->modify('+1 month'),
            'trimestral' => $base->modify('+3 months'),
            'semestral' => $base->modify('+6 months'),
            'anual' => $base->modify('+1 year'),
            'personalizado' => $base->modify('+1 month'),
            default => $base->modify('+1 month'),
        };

        $this->conn->beginTransaction();

        try {
            $stmtAssinatura = $this->conn->prepare("
                UPDATE empresas_assinaturas
                SET
                    data_inicio = COALESCE(data_inicio, NOW()),
                    data_vencimento = :data_vencimento,
                    assinatura_ate = :assinatura_ate,
                    bloqueio_manual = 0,
                    bloqueio_manual_motivo = NULL,
                    updated_at = NOW()
                WHERE empresa_id = :empresa_id
            ");
            $stmtAssinatura->execute([
                ':data_vencimento' => $novoFim->format('Y-m-d H:i:s'),
                ':assinatura_ate' => $novoFim->format('Y-m-d H:i:s'),
                ':empresa_id' => $empresaId,
            ]);

            $stmtEmpresa = $this->conn->prepare("
                UPDATE empresas
                SET status = 'ativa'
                WHERE id = :empresa_id
            ");
            $stmtEmpresa->execute([
                ':empresa_id' => $empresaId,
            ]);

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }

        return 'Conta reativada e renovada com sucesso.';
    }

    public function reativar(int $empresaId): string
    {
        if ($empresaId <= 0) {
            throw new RuntimeException('Empresa inválida.');
        }

        $this->licencaService->reativar($empresaId);

        return 'Conta reativada com sucesso.';
    }

    public function toggleBloqueio(int $empresaId): string
    {
        if ($empresaId <= 0) {
            throw new RuntimeException('Empresa inválida.');
        }

        $empresa = $this->empresaModel->findById($empresaId);
        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) === 1) {
            throw new RuntimeException('A empresa root não pode ser bloqueada ou reativada por esta ação.');
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($empresaId);
        if (!$assinatura) {
            throw new RuntimeException('Assinatura atual da empresa não encontrada.');
        }

        $estaBloqueadaManual = (int) ($assinatura['bloqueio_manual'] ?? 0) === 1;

        if ($estaBloqueadaManual) {
            $this->licencaService->reativar($empresaId);
            return 'Empresa reativada com sucesso.';
        }

        $motivo = 'Bloqueio manual realizado pelo administrador da plataforma.';
        $this->licencaService->bloquearManual($empresaId, $motivo);

        return 'Empresa bloqueada manualmente com sucesso.';
    }

    public function reenviarConvite(int $empresaId, array $data): array
    {
        if ($empresaId <= 0) {
            throw new RuntimeException('Empresa inválida.');
        }

        $empresa = $this->empresaModel->findById($empresaId);
        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM usuarios_empresas ue
            WHERE ue.empresa_id = :empresa_id
              AND ue.status = 'ativo'
        ");
        $stmt->execute([':empresa_id' => $empresaId]);
        $totalUsuariosAtivos = (int) $stmt->fetchColumn();

        if ($totalUsuariosAtivos > 0) {
            throw new RuntimeException('A empresa já possui usuário criado. O convite foi bloqueado.');
        }

        $responsavelNome = trim((string) ($data['nome'] ?? $data['responsavel_nome'] ?? ''));
        $responsavelEmail = mb_strtolower(trim((string) ($data['email'] ?? $data['responsavel_email'] ?? '')));

        if ($responsavelNome === '' || $responsavelEmail === '') {
            throw new RuntimeException('Informe nome e e-mail do responsável antes de gerar o convite.');
        }

        $conviteAdmin = $this->conviteService->reenviarConviteAdmin(
            $empresaId,
            $responsavelNome,
            $responsavelEmail,
            'owner',
            7
        );

        $flashConvite = [
            'empresa_id' => $empresaId,
            'empresa_nome' => $empresa['nome_fantasia'] ?? '',
            'nome' => $responsavelNome,
            'email' => $responsavelEmail,
            'link' => $conviteAdmin['link'],
            'expires_at' => $conviteAdmin['expires_at'],
            'email_sent' => false,
            'email_message' => '',
        ];

        $emailResult = $this->conviteService->enviarConviteAdminEmail($conviteAdmin['convite'] + [
            'link' => $conviteAdmin['link'],
            'expires_at' => $conviteAdmin['expires_at'],
        ]);

        $flashConvite['email_sent'] = !empty($emailResult['success']);
        $flashConvite['email_message'] = (string) ($emailResult['message'] ?? '');

        $flashType = !empty($emailResult['success']) ? 'success' : 'warning';
        $flashMessage = !empty($emailResult['success'])
            ? 'Novo convite gerado e enviado para ' . $responsavelEmail . '.'
            : 'Novo convite gerado, mas o envio automático falhou: ' . (string) ($emailResult['message'] ?? 'erro desconhecido') . '.';

        return [
            'flash_convite_admin' => $flashConvite,
            'flash_type' => $flashType,
            'flash_message' => $flashMessage,
        ];
    }
}
