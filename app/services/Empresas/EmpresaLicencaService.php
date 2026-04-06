<?php

class EmpresaLicencaService
{
    private PDO $conn;
    private Empresa $empresaModel;
    private EmpresaAssinatura $assinaturaModel;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->empresaModel = new Empresa($conn);
        $this->assinaturaModel = new EmpresaAssinatura($conn);
    }

    public function calcularStatus(int $empresaId): array
    {
        $empresa = $this->empresaModel->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) === 1) {
            return [
                'empresa_id' => $empresaId,
                'status_assinatura' => 'ativa',
                'status_acesso' => 'liberada',
                'dias_restantes' => null,
                'em_tolerancia' => false,
                'bloqueada' => false,
                'motivo' => null,
                'data_vencimento' => null,
                'data_limite_tolerancia' => null,
            ];
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($empresaId);

        if (!$assinatura) {
            return [
                'empresa_id' => $empresaId,
                'status_assinatura' => 'bloqueada',
                'status_acesso' => 'bloqueada',
                'dias_restantes' => 0,
                'em_tolerancia' => false,
                'bloqueada' => true,
                'motivo' => 'Empresa sem assinatura cadastrada.',
                'data_vencimento' => null,
                'data_limite_tolerancia' => null,
            ];
        }

        if ((int) ($assinatura['bloqueio_manual'] ?? 0) === 1) {
            return [
                'empresa_id' => $empresaId,
                'status_assinatura' => 'bloqueada',
                'status_acesso' => 'bloqueada',
                'dias_restantes' => 0,
                'em_tolerancia' => false,
                'bloqueada' => true,
                'motivo' => $assinatura['bloqueio_manual_motivo'] ?: 'Empresa bloqueada manualmente.',
                'data_vencimento' => $assinatura['data_vencimento'] ?? null,
                'data_limite_tolerancia' => null,
            ];
        }

        $agora = new DateTimeImmutable('now');
        $dataVencimentoRaw = $assinatura['data_vencimento'] ?? null;
        $statusAssinaturaAtual = $assinatura['status_assinatura'] ?? 'ativa';
        $diasTolerancia = (int) ($assinatura['dias_tolerancia'] ?? 0);

        if (empty($dataVencimentoRaw)) {
            return [
                'empresa_id' => $empresaId,
                'status_assinatura' => $statusAssinaturaAtual,
                'status_acesso' => 'liberada',
                'dias_restantes' => null,
                'em_tolerancia' => false,
                'bloqueada' => false,
                'motivo' => null,
                'data_vencimento' => null,
                'data_limite_tolerancia' => null,
            ];
        }

        $dataVencimento = new DateTimeImmutable($dataVencimentoRaw);
        $dataLimiteTolerancia = $dataVencimento
            ->modify('+' . $diasTolerancia . ' days')
            ->setTime(23, 59, 59);

        if ($agora > $dataLimiteTolerancia) {
            return [
                'empresa_id' => $empresaId,
                'status_assinatura' => 'bloqueada',
                'status_acesso' => 'bloqueada',
                'dias_restantes' => 0,
                'em_tolerancia' => false,
                'bloqueada' => true,
                'motivo' => 'Licença expirada.',
                'data_vencimento' => $dataVencimento->format('Y-m-d H:i:s'),
                'data_limite_tolerancia' => $dataLimiteTolerancia->format('Y-m-d H:i:s'),
            ];
        }

        if ($agora > $dataVencimento) {
            return [
                'empresa_id' => $empresaId,
                'status_assinatura' => 'em_tolerancia',
                'status_acesso' => 'liberada',
                'dias_restantes' => 0,
                'em_tolerancia' => true,
                'bloqueada' => false,
                'motivo' => 'Empresa em período de tolerância.',
                'data_vencimento' => $dataVencimento->format('Y-m-d H:i:s'),
                'data_limite_tolerancia' => $dataLimiteTolerancia->format('Y-m-d H:i:s'),
            ];
        }

        $diff = $agora->diff($dataVencimento);
        $diasRestantes = (int) $diff->format('%a');

        return [
            'empresa_id' => $empresaId,
            'status_assinatura' => $statusAssinaturaAtual,
            'status_acesso' => 'liberada',
            'dias_restantes' => $diasRestantes,
            'em_tolerancia' => false,
            'bloqueada' => false,
            'motivo' => null,
            'data_vencimento' => $dataVencimento->format('Y-m-d H:i:s'),
            'data_limite_tolerancia' => $dataLimiteTolerancia->format('Y-m-d H:i:s'),
        ];
    }

    public function sincronizarStatus(int $empresaId): array
    {
        $status = $this->calcularStatus($empresaId);

        $empresa = $this->empresaModel->findById($empresaId);
        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) === 1) {
            return $status;
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($empresaId);

        if ($assinatura) {
            $this->assinaturaModel->updateStatus(
                (int) $assinatura['id'],
                $status['status_assinatura']
            );
        }

        $statusEmpresa = $status['bloqueada'] ? 'suspensa' : 'ativa';
        $this->empresaModel->updateStatus($empresaId, $statusEmpresa);

        return $status;
    }

    public function podeAcessar(int $empresaId): bool
    {
        $status = $this->sincronizarStatus($empresaId);

        return $status['status_acesso'] === 'liberada';
    }

    public function bloquearManual(int $empresaId, string $motivo): void
    {
        $empresa = $this->empresaModel->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) === 1) {
            throw new RuntimeException('A empresa root não pode ser bloqueada manualmente.');
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($empresaId);

        if (!$assinatura) {
            throw new RuntimeException('Assinatura da empresa não encontrada.');
        }

        $this->conn->beginTransaction();

        try {
            $this->assinaturaModel->setBloqueioManual((int) $assinatura['id'], true, $motivo);
            $this->assinaturaModel->updateStatus((int) $assinatura['id'], 'bloqueada');
            $this->empresaModel->updateStatus($empresaId, 'suspensa');

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function reativar(int $empresaId): void
    {
        $empresa = $this->empresaModel->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        if ((int) ($empresa['is_root'] ?? 0) === 1) {
            return;
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($empresaId);

        if (!$assinatura) {
            throw new RuntimeException('Assinatura da empresa não encontrada.');
        }

        $this->conn->beginTransaction();

        try {
            $this->assinaturaModel->setBloqueioManual((int) $assinatura['id'], false, null);

            $novoStatus = $this->calcularStatus($empresaId);

            $this->assinaturaModel->updateStatus(
                (int) $assinatura['id'],
                $novoStatus['status_assinatura']
            );

            $this->empresaModel->updateStatus(
                $empresaId,
                $novoStatus['bloqueada'] ? 'suspensa' : 'ativa'
            );

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function renovar(
        int $empresaId,
        string $dataInicio,
        ?string $dataVencimento,
        int $diasTolerancia = 0,
        ?string $statusAssinatura = 'ativa'
    ): void {
        $empresa = $this->empresaModel->findById($empresaId);

        if (!$empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $assinatura = $this->assinaturaModel->findAtualByEmpresa($empresaId);

        if (!$assinatura) {
            throw new RuntimeException('Assinatura da empresa não encontrada.');
        }

        $payload = [
            'plano_id' => $assinatura['plano_id'] ?? null,
            'tipo_cobranca' => $assinatura['tipo_cobranca'] ?? 'mensal',
            'status_assinatura' => $statusAssinatura ?? 'ativa',
            'data_inicio' => $dataInicio,
            'data_vencimento' => $dataVencimento,
            'dias_tolerancia' => $diasTolerancia,
            'data_bloqueio' => null,
            'valor_cobrado' => $assinatura['valor_cobrado'] ?? null,
            'observacoes_internas' => $assinatura['observacoes_internas'] ?? null,
            'bloqueio_manual' => 0,
            'bloqueio_manual_motivo' => null,
        ];

        $this->conn->beginTransaction();

        try {
            $this->assinaturaModel->update((int) $assinatura['id'], $payload);
            $this->empresaModel->updateStatus($empresaId, 'ativa');

            $this->conn->commit();

            $this->sincronizarStatus($empresaId);
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }
}