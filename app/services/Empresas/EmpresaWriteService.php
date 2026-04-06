<?php

class EmpresaWriteService
{
    private PDO $conn;
    private Empresa $empresaModel;
    private EmpresaAssinatura $assinaturaModel;
    private EmpresaLimiteService $limiteService;
    private ConviteEmpresaService $conviteService;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->empresaModel = new Empresa($conn);
        $this->assinaturaModel = new EmpresaAssinatura($conn);
        $this->limiteService = new EmpresaLimiteService($conn);
        $this->conviteService = new ConviteEmpresaService($conn);
    }

    public function create(array $input): array
    {
        $old = $this->buildCreateOld($input);
        $payload = $this->validateCreatePayload($old);

        try {
            $this->conn->beginTransaction();

            $empresaId = $this->empresaModel->create($payload['empresa_data']);

            $assinaturaData = $payload['assinatura_data'];
            $assinaturaData['empresa_id'] = $empresaId;
            $this->assinaturaModel->create($assinaturaData);

            $conviteAdmin = $this->conviteService->criarConviteAdmin(
                $empresaId,
                $payload['responsavel_nome'],
                $payload['responsavel_email'],
                'owner',
                7
            );

            $this->conn->commit();

            $emailResult = $this->conviteService->enviarConviteAdminEmail($conviteAdmin['convite'] + [
                'link' => $conviteAdmin['link'],
                'expires_at' => $conviteAdmin['expires_at'],
            ]);

            return [
                'empresa_id' => $empresaId,
                'empresa_nome' => $payload['empresa_data']['nome_fantasia'],
                'responsavel_nome' => $payload['responsavel_nome'],
                'responsavel_email' => $payload['responsavel_email'],
                'convite_link' => $conviteAdmin['link'],
                'convite_expires_at' => $conviteAdmin['expires_at'],
                'email_result' => $emailResult,
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            if ($e instanceof FormValidationException) {
                throw $e;
            }

            throw new RuntimeException('Não foi possível criar a empresa: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(int $id, array $input): void
    {
        $empresaAtual = $this->empresaModel->findById($id);
        if (!$empresaAtual) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $assinaturaAtual = $this->assinaturaModel->findAtualByEmpresa($id);
        if (!$assinaturaAtual) {
            throw new RuntimeException('Assinatura atual da empresa não encontrada.');
        }

        $old = $this->buildUpdateOld($id, $input);
        $payload = $this->validateUpdatePayload($id, $old, $empresaAtual, $assinaturaAtual);

        try {
            $this->conn->beginTransaction();
            $this->empresaModel->update($id, $payload['empresa_data']);
            $this->assinaturaModel->update((int) $assinaturaAtual['id'], $payload['assinatura_data']);
            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            if ($e instanceof FormValidationException) {
                throw $e;
            }

            throw new RuntimeException('Não foi possível atualizar a empresa.', 0, $e);
        }
    }

    private function buildCreateOld(array $input): array
    {
        return [
            'nome_fantasia' => trim((string) ($input['nome_fantasia'] ?? '')),
            'razao_social' => trim((string) ($input['razao_social'] ?? '')),
            'documento' => trim((string) ($input['documento'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'telefone' => trim((string) ($input['telefone'] ?? '')),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'plano' => trim((string) ($input['plano'] ?? 'trial')),
            'plano_id' => trim((string) ($input['plano_id'] ?? '')),
            'status' => trim((string) ($input['status'] ?? 'ativa')),
            'tipo_cobranca' => trim((string) ($input['tipo_cobranca'] ?? 'trial')),
            'is_root' => isset($input['is_root']) ? '1' : '',
            'bloqueio_manual' => isset($input['bloqueio_manual']) ? '1' : '',
            'bloqueio_manual_motivo' => trim((string) ($input['bloqueio_manual_motivo'] ?? '')),
            'limite_usuarios' => trim((string) ($input['limite_usuarios'] ?? '1')),
            'limite_contas_ads' => trim((string) ($input['limite_contas_ads'] ?? '1')),
            'valor_cobrado' => trim((string) ($input['valor_cobrado'] ?? '')),
            'status_assinatura' => trim((string) ($input['status_assinatura'] ?? 'trial')),
            'data_inicio' => trim((string) ($input['data_inicio'] ?? '')),
            'data_vencimento' => trim((string) ($input['data_vencimento'] ?? '')),
            'dias_tolerancia' => trim((string) ($input['dias_tolerancia'] ?? '0')),
            'trial_ate' => trim((string) ($input['trial_ate'] ?? '')),
            'assinatura_ate' => trim((string) ($input['assinatura_ate'] ?? '')),
            'data_bloqueio' => trim((string) ($input['data_bloqueio'] ?? '')),
            'observacoes_internas' => trim((string) ($input['observacoes_internas'] ?? '')),
            'observacoes_empresa' => trim((string) ($input['observacoes_empresa'] ?? '')),
            'responsavel_nome' => trim((string) ($input['responsavel_nome'] ?? '')),
            'responsavel_email' => trim((string) ($input['responsavel_email'] ?? '')),
        ];
    }

    private function buildUpdateOld(int $id, array $input): array
    {
        return [
            'id' => (string) $id,
            'nome_fantasia' => trim((string) ($input['nome_fantasia'] ?? '')),
            'razao_social' => trim((string) ($input['razao_social'] ?? '')),
            'documento' => trim((string) ($input['documento'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'telefone' => trim((string) ($input['telefone'] ?? '')),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'plano' => trim((string) ($input['plano'] ?? 'trial')),
            'plano_id' => trim((string) ($input['plano_id'] ?? '')),
            'status' => trim((string) ($input['status'] ?? 'ativa')),
            'tipo_cobranca' => trim((string) ($input['tipo_cobranca'] ?? 'trial')),
            'is_root' => isset($input['is_root']) ? '1' : '',
            'bloqueio_manual' => isset($input['bloqueio_manual']) ? '1' : '',
            'bloqueio_manual_motivo' => trim((string) ($input['bloqueio_manual_motivo'] ?? '')),
            'limite_usuarios' => trim((string) ($input['limite_usuarios'] ?? '1')),
            'limite_contas_ads' => trim((string) ($input['limite_contas_ads'] ?? '1')),
            'valor_cobrado' => trim((string) ($input['valor_cobrado'] ?? '')),
            'status_assinatura' => trim((string) ($input['status_assinatura'] ?? 'trial')),
            'data_inicio' => trim((string) ($input['data_inicio'] ?? '')),
            'data_vencimento' => trim((string) ($input['data_vencimento'] ?? '')),
            'dias_tolerancia' => trim((string) ($input['dias_tolerancia'] ?? '0')),
            'trial_ate' => trim((string) ($input['trial_ate'] ?? '')),
            'assinatura_ate' => trim((string) ($input['assinatura_ate'] ?? '')),
            'data_bloqueio' => trim((string) ($input['data_bloqueio'] ?? '')),
            'observacoes_internas' => trim((string) ($input['observacoes_internas'] ?? '')),
            'observacoes_empresa' => trim((string) ($input['observacoes_empresa'] ?? '')),
        ];
    }

    private function validateCreatePayload(array $old): array
    {
        $errors = [];
        $normalized = $this->normalizeCommonPayload($old, $errors, null, null);

        $responsavelNome = $old['responsavel_nome'];
        $responsavelEmail = mb_strtolower(trim($old['responsavel_email']));

        if ($responsavelNome === '') {
            $errors['responsavel_nome'] = 'Informe o nome do responsável.';
        }

        if ($responsavelEmail === '') {
            $errors['responsavel_email'] = 'Informe o e-mail do responsável.';
        } elseif (!filter_var($responsavelEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['responsavel_email'] = 'Informe um e-mail válido para o responsável.';
        }

        if (!empty($errors)) {
            throw new FormValidationException($errors, $old);
        }

        return $normalized + [
            'responsavel_nome' => $responsavelNome,
            'responsavel_email' => $responsavelEmail,
        ];
    }

    private function validateUpdatePayload(int $id, array $old, array $empresaAtual, array $assinaturaAtual): array
    {
        $errors = [];
        $normalized = $this->normalizeCommonPayload($old, $errors, $id, $empresaAtual);

        try {
            $consumoAtual = $this->limiteService->getConsumo($id);
            $usuariosUsados = (int) ($consumoAtual['usuarios']['usados'] ?? 0);
            $contasUsadas = (int) ($consumoAtual['contas_ads']['usadas'] ?? 0);

            if ((int) $normalized['empresa_data']['limite_usuarios'] < $usuariosUsados) {
                $errors['limite_usuarios'] = 'O limite de usuários não pode ficar abaixo do uso atual (' . $usuariosUsados . ').';
            }

            if ((int) $normalized['empresa_data']['limite_contas_ads'] < $contasUsadas) {
                $errors['limite_contas_ads'] = 'O limite de contas ads não pode ficar abaixo do uso atual (' . $contasUsadas . ').';
            }
        } catch (Throwable $e) {
            $errors['geral'] = 'Não foi possível validar o consumo atual da empresa.';
        }

        if ((int) ($empresaAtual['is_root'] ?? 0) === 1 && (int) $normalized['empresa_data']['is_root'] !== 1) {
            $errors['is_root'] = 'A empresa root atual não pode perder esse status por esta tela.';
        }

        if (!$assinaturaAtual) {
            $errors['geral'] = 'Assinatura atual da empresa não encontrada.';
        }

        if (!empty($errors)) {
            throw new FormValidationException($errors, $old);
        }

        return $normalized;
    }

    private function normalizeCommonPayload(array $old, array &$errors, ?int $empresaId, ?array $empresaAtual): array
    {
        $nomeFantasia = $old['nome_fantasia'];
        $razaoSocial = $old['razao_social'] !== '' ? $old['razao_social'] : null;
        $documento = $old['documento'] !== '' ? $old['documento'] : null;
        $email = $old['email'] !== '' ? $old['email'] : null;
        $telefone = $old['telefone'] !== '' ? $old['telefone'] : null;
        $slug = mb_strtolower($old['slug']);
        $plano = $old['plano'] !== '' ? $old['plano'] : 'trial';
        $planoId = $old['plano_id'] !== '' ? (int) $old['plano_id'] : null;
        $status = $old['status'] !== '' ? $old['status'] : 'ativa';
        $tipoCobranca = $old['tipo_cobranca'] !== '' ? $old['tipo_cobranca'] : 'trial';
        $isRoot = $old['is_root'] === '1' ? 1 : 0;
        $bloqueioManual = $old['bloqueio_manual'] === '1' ? 1 : 0;
        $bloqueioManualMotivo = $old['bloqueio_manual_motivo'] !== '' ? $old['bloqueio_manual_motivo'] : null;
        $limiteUsuarios = is_numeric($old['limite_usuarios']) ? (int) $old['limite_usuarios'] : 0;
        $limiteContasAds = is_numeric($old['limite_contas_ads']) ? (int) $old['limite_contas_ads'] : 0;
        $valorCobrado = $this->normalizeMoney($old['valor_cobrado']);
        $statusAssinatura = $old['status_assinatura'] !== '' ? $old['status_assinatura'] : 'trial';
        $dataInicio = $this->normalizeDateTimeLocal($old['data_inicio']);
        $dataVencimento = $this->normalizeDateTimeLocal($old['data_vencimento']);
        $diasTolerancia = is_numeric($old['dias_tolerancia']) ? (int) $old['dias_tolerancia'] : 0;
        $trialAte = $this->normalizeDateTimeLocal($old['trial_ate']);
        $assinaturaAte = $this->normalizeDateTimeLocal($old['assinatura_ate']);
        $dataBloqueio = $this->normalizeDateTimeLocal($old['data_bloqueio']);
        $observacoesInternas = $old['observacoes_internas'] !== '' ? $old['observacoes_internas'] : null;

        $planosValidos = ['trial', 'basic', 'pro', 'enterprise'];
        $statusEmpresaValidos = ['ativa', 'inativa', 'suspensa', 'cancelada'];
        $tiposCobrancaValidos = ['trial', 'mensal', 'trimestral', 'semestral', 'anual', 'personalizado'];
        $statusAssinaturaValidos = ['trial', 'ativa', 'vencida', 'em_tolerancia', 'bloqueada', 'cancelada'];

        if ($nomeFantasia === '') {
            $errors['nome_fantasia'] = 'Informe o nome fantasia.';
        }

        if ($slug === '') {
            $errors['slug'] = 'Informe o slug.';
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $errors['slug'] = 'Use apenas letras minúsculas, números e hífens no slug.';
        } elseif ($this->empresaModel->slugExists($slug, $empresaId)) {
            $errors['slug'] = $empresaId === null
                ? 'Este slug já está em uso.'
                : 'Este slug já está em uso por outra empresa.';
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }

        if (!in_array($plano, $planosValidos, true)) {
            $errors['plano'] = 'Plano inválido.';
        }

        if (!in_array($status, $statusEmpresaValidos, true)) {
            $errors['status'] = 'Status cadastral inválido.';
        }

        if (!in_array($tipoCobranca, $tiposCobrancaValidos, true)) {
            $errors['tipo_cobranca'] = 'Tipo de cobrança inválido.';
        }

        if (!in_array($statusAssinatura, $statusAssinaturaValidos, true)) {
            $errors['status_assinatura'] = 'Status da assinatura inválido.';
        }

        if ($limiteUsuarios < 1) {
            $errors['limite_usuarios'] = 'O limite de usuários deve ser pelo menos 1.';
        }

        if ($limiteContasAds < 1) {
            $errors['limite_contas_ads'] = 'O limite de contas ads deve ser pelo menos 1.';
        }

        if ($old['data_inicio'] === '' || !$dataInicio) {
            $errors['data_inicio'] = 'Informe uma data de início válida.';
        }

        if ($old['data_vencimento'] !== '' && !$dataVencimento) {
            $errors['data_vencimento'] = 'Informe uma data de vencimento válida.';
        }

        if ($old['trial_ate'] !== '' && !$trialAte) {
            $errors['trial_ate'] = 'Informe uma data de trial válida.';
        }

        if ($old['assinatura_ate'] !== '' && !$assinaturaAte) {
            $errors['assinatura_ate'] = 'Informe uma data de assinatura válida.';
        }

        if ($old['data_bloqueio'] !== '' && !$dataBloqueio) {
            $errors['data_bloqueio'] = 'Informe uma data de bloqueio válida.';
        }

        if ($diasTolerancia < 0) {
            $errors['dias_tolerancia'] = 'Os dias de tolerância não podem ser negativos.';
        }

        if ($bloqueioManual === 1 && empty($bloqueioManualMotivo)) {
            $errors['bloqueio_manual_motivo'] = 'Informe o motivo do bloqueio manual.';
        }

        if ($dataInicio && $dataVencimento) {
            try {
                $inicioObj = new DateTime($dataInicio);
                $vencimentoObj = new DateTime($dataVencimento);

                if ($vencimentoObj < $inicioObj) {
                    $errors['data_vencimento'] = 'O vencimento não pode ser anterior ao início.';
                }
            } catch (Throwable $e) {
                $errors['data_vencimento'] = 'Período da licença inválido.';
            }
        }

        if ($valorCobrado === null && $old['valor_cobrado'] !== '') {
            $errors['valor_cobrado'] = 'Informe um valor cobrado válido.';
        }

        $statusEmpresaFinal = $bloqueioManual === 1 ? 'suspensa' : $status;
        $statusAssinaturaFinal = $bloqueioManual === 1 ? 'bloqueada' : $statusAssinatura;
        $dataBloqueioFinal = $bloqueioManual === 1 ? ($dataBloqueio ?: date('Y-m-d H:i:s')) : null;

        return [
            'empresa_data' => [
                'uuid' => uuidv4(),
                'nome_fantasia' => $nomeFantasia,
                'razao_social' => $razaoSocial,
                'documento' => $documento,
                'email' => $email,
                'telefone' => $telefone,
                'slug' => $slug,
                'plano' => $plano,
                'status' => $statusEmpresaFinal,
                'limite_usuarios' => $limiteUsuarios,
                'limite_contas_ads' => $limiteContasAds,
                'trial_ate' => $trialAte,
                'assinatura_ate' => $assinaturaAte ?: $dataVencimento,
                'is_root' => $isRoot,
            ],
            'assinatura_data' => [
                'plano_id' => $planoId,
                'tipo_cobranca' => $tipoCobranca,
                'status_assinatura' => $statusAssinaturaFinal,
                'data_inicio' => $dataInicio,
                'data_vencimento' => $dataVencimento,
                'dias_tolerancia' => $diasTolerancia,
                'data_bloqueio' => $dataBloqueioFinal,
                'valor_cobrado' => $valorCobrado,
                'observacoes_internas' => $observacoesInternas,
                'bloqueio_manual' => $bloqueioManual,
                'bloqueio_manual_motivo' => $bloqueioManualMotivo,
            ],
        ];
    }

    private function normalizeDateTimeLocal(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d',
            'Y-m-d\TH:i',
            'Y-m-d\TH:i:s',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        ];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                if ($format === 'Y-m-d') {
                    return $date->format('Y-m-d 00:00:00');
                }

                return $date->format('Y-m-d H:i:s');
            }
        }

        try {
            $date = new DateTime($value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $date->format('Y-m-d 00:00:00');
            }

            return $date->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function normalizeMoney(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
