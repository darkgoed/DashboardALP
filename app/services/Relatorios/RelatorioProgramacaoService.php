<?php

class RelatorioProgramacaoService
{
    private PDO $conn;
    private int $empresaId;
    private Cliente $clienteModel;
    private ContaAds $contaModel;
    private Campanha $campanhaModel;
    private RelatorioProgramacao $programacaoModel;
    private RelatorioEmailService $emailService;
    private RelatorioWhatsAppService $whatsAppService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->clienteModel = new Cliente($conn, $empresaId);
        $this->contaModel = new ContaAds($conn, $empresaId);
        $this->campanhaModel = new Campanha($conn, $empresaId);
        $this->programacaoModel = new RelatorioProgramacao($conn);
        $this->emailService = new RelatorioEmailService($conn, $empresaId);
        $this->whatsAppService = new RelatorioWhatsAppService($conn, $empresaId);
    }

    public function listForPage(): array
    {
        $rows = $this->programacaoModel->listByEmpresa($this->empresaId);

        return array_map(fn(array $row): array => $this->normalizeForPage($row), $rows);
    }

    public function saveMany(array $items): int
    {
        $rows = [];

        foreach (array_values($items) as $index => $item) {
            if (!$this->hasMeaningfulData((array) $item)) {
                continue;
            }

            $rows[] = $this->validateAndNormalize((array) $item, $index + 1);
        }

        $this->programacaoModel->replaceAllByEmpresa($this->empresaId, $rows);

        return count($rows);
    }

    public function processDue(?DateTimeImmutable $now = null, int $limit = 50): array
    {
        $now = $now ?: new DateTimeImmutable('now');
        $programacoes = $this->programacaoModel->dueForProcessing($now, $limit);

        $processed = 0;
        $success = 0;
        $errors = 0;
        $messages = [];

        foreach ($programacoes as $programacao) {
            $processed++;
            $payload = $this->buildDeliveryPayload($programacao);
            $nextRun = $this->calculateNextRun(
                new DateTimeImmutable((string) $programacao['proximo_envio_em']),
                (int) $programacao['frequencia_dias'],
                $now
            );

            try {
                $results = [];

                if (!empty($programacao['enviar_email']) && !empty($programacao['destino_email'])) {
                    $results['email'] = $this->emailService->enviar($payload + [
                        'tipo_envio' => 'agendado_email',
                    ]);
                }

                if (!empty($programacao['enviar_whatsapp']) && !empty($programacao['destino_whatsapp'])) {
                    $results['whatsapp'] = $this->whatsAppService->enviar($payload + [
                        'tipo_envio' => 'agendado_whatsapp',
                    ]);
                }

                if ($results === []) {
                    throw new RuntimeException('Nenhum canal ativo com destino configurado para esta programacao.');
                }

                $failed = array_filter(
                    $results,
                    static fn(array $result): bool => empty($result['success'])
                );

                $status = $failed === [] ? 'sucesso' : 'erro';
                $parts = [];
                foreach ($results as $canal => $result) {
                    $parts[] = $canal . ': ' . (string) ($result['message'] ?? ($result['success'] ? 'ok' : 'erro'));
                }
                $message = implode(' | ', $parts);

                $this->programacaoModel->updateExecution((int) $programacao['id'], $this->empresaId, [
                    'proximo_envio_em' => $nextRun->format('Y-m-d H:i:s'),
                    'ultimo_envio_em' => $now->format('Y-m-d H:i:s'),
                    'ultimo_status' => $status,
                    'ultima_mensagem' => $message,
                ]);

                if ($status === 'sucesso') {
                    $success++;
                } else {
                    $errors++;
                }

                $messages[] = '#' . (int) $programacao['id'] . ' ' . $status . ' - ' . $message;
            } catch (Throwable $e) {
                $errors++;

                $this->programacaoModel->updateExecution((int) $programacao['id'], $this->empresaId, [
                    'proximo_envio_em' => $nextRun->format('Y-m-d H:i:s'),
                    'ultimo_envio_em' => $now->format('Y-m-d H:i:s'),
                    'ultimo_status' => 'erro',
                    'ultima_mensagem' => $e->getMessage(),
                ]);

                $messages[] = '#' . (int) $programacao['id'] . ' erro - ' . $e->getMessage();
            }
        }

        return [
            'processed' => $processed,
            'success' => $success,
            'errors' => $errors,
            'messages' => $messages,
        ];
    }

    private function hasMeaningfulData(array $item): bool
    {
        foreach (['destino_email', 'destino_whatsapp', 'destino_nome', 'cliente_id', 'conta_id', 'campanha_id', 'data_inicio', 'data_fim'] as $field) {
            if (trim((string) ($item[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function validateAndNormalize(array $item, int $position): array
    {
        $clienteId = $this->sanitizeId($item['cliente_id'] ?? null);
        $contaId = $this->sanitizeId($item['conta_id'] ?? null);
        $campanhaId = $this->sanitizeId($item['campanha_id'] ?? null);
        $campanhaStatus = strtoupper(trim((string) ($item['campanha_status'] ?? '')));
        $periodo = trim((string) ($item['periodo'] ?? '30'));
        $destinoEmail = mb_strtolower(trim((string) ($item['destino_email'] ?? '')));
        $destinoWhatsappRaw = trim((string) ($item['destino_whatsapp'] ?? ''));
        $destinoNome = trim((string) ($item['destino_nome'] ?? ''));
        $enviarEmail = !empty($item['enviar_email']) ? 1 : 0;
        $enviarWhatsapp = !empty($item['enviar_whatsapp']) ? 1 : 0;
        $frequenciaDias = (int) ($item['frequencia_dias'] ?? 7);
        $horarioEnvio = $this->normalizeTime((string) ($item['horario_envio'] ?? '07:00'));
        $dataInicioAgendamento = trim((string) ($item['data_inicio_agendamento'] ?? date('Y-m-d')));
        $ativo = !empty($item['ativo']) ? 1 : 0;
        $dataInicio = trim((string) ($item['data_inicio'] ?? ''));
        $dataFim = trim((string) ($item['data_fim'] ?? ''));
        $destinoWhatsapp = $destinoWhatsappRaw !== ''
            ? WhatsAppChannelService::normalizePhone($destinoWhatsappRaw)
            : '';

        $periodosPermitidos = ['1', '3', '7', '14', '15', '30', '90', '365', 'custom'];
        $frequenciasPermitidas = [1, 3, 7, 14, 30];
        $statusPermitidos = ['', 'ACTIVE', 'PAUSED', 'ARCHIVED', 'DELETED', 'WITH_ISSUES'];

        if (!$enviarEmail && !$enviarWhatsapp) {
            throw new RuntimeException('Programacao ' . $position . ': selecione ao menos um canal de envio.');
        }

        if ($enviarEmail && ($destinoEmail === '' || !filter_var($destinoEmail, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('Programacao ' . $position . ': informe um e-mail valido.');
        }

        if ($enviarWhatsapp && $destinoWhatsapp === '') {
            throw new RuntimeException('Programacao ' . $position . ': informe um WhatsApp valido.');
        }

        if (!in_array($frequenciaDias, $frequenciasPermitidas, true)) {
            throw new RuntimeException('Programacao ' . $position . ': frequencia invalida.');
        }

        if (!in_array($periodo, $periodosPermitidos, true)) {
            throw new RuntimeException('Programacao ' . $position . ': periodo invalido.');
        }

        if (!in_array($campanhaStatus, $statusPermitidos, true)) {
            throw new RuntimeException('Programacao ' . $position . ': status de campanha invalido.');
        }

        if ($dataInicioAgendamento === '' || !$this->isValidDate($dataInicioAgendamento)) {
            throw new RuntimeException('Programacao ' . $position . ': defina a data inicial do agendamento.');
        }

        if ($periodo === 'custom') {
            if (!$this->isValidDate($dataInicio) || !$this->isValidDate($dataFim)) {
                throw new RuntimeException('Programacao ' . $position . ': informe data inicial e final do periodo personalizado.');
            }

            if ($dataInicio > $dataFim) {
                throw new RuntimeException('Programacao ' . $position . ': a data inicial nao pode ser maior que a data final.');
            }
        } else {
            $dataInicio = null;
            $dataFim = null;
        }

        $cliente = $clienteId ? $this->clienteModel->getById($clienteId) : null;
        if ($clienteId && !$cliente) {
            throw new RuntimeException('Programacao ' . $position . ': cliente invalido.');
        }

        $conta = $contaId ? $this->contaModel->getById($contaId) : null;
        if ($contaId && !$conta) {
            throw new RuntimeException('Programacao ' . $position . ': conta invalida.');
        }

        if ($clienteId && $contaId && (int) ($conta['cliente_id'] ?? 0) !== $clienteId) {
            throw new RuntimeException('Programacao ' . $position . ': a conta selecionada nao pertence ao cliente informado.');
        }

        $campanha = $campanhaId ? $this->campanhaModel->getById($campanhaId) : null;
        if ($campanhaId && !$campanha) {
            throw new RuntimeException('Programacao ' . $position . ': campanha invalida.');
        }

        if ($contaId && $campanhaId && (int) ($campanha['conta_id'] ?? 0) !== $contaId) {
            throw new RuntimeException('Programacao ' . $position . ': a campanha selecionada nao pertence a conta informada.');
        }

        if (!$contaId && $campanhaId) {
            $contaId = (int) ($campanha['conta_id'] ?? 0) ?: null;
        }

        if (!$clienteId && $contaId) {
            $clienteId = (int) ($conta['cliente_id'] ?? 0) ?: null;
        }

        $nextRun = $this->calculateFirstRun($dataInicioAgendamento, $horarioEnvio, $frequenciaDias);

        return [
            'cliente_id' => $clienteId,
            'conta_id' => $contaId,
            'campanha_id' => $campanhaId,
            'campanha_status' => $campanhaStatus !== '' ? $campanhaStatus : null,
            'periodo' => $periodo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'destino_email' => $destinoEmail !== '' ? $destinoEmail : null,
            'destino_whatsapp' => $destinoWhatsapp !== '' ? $destinoWhatsapp : null,
            'destino_nome' => $destinoNome !== '' ? $destinoNome : null,
            'enviar_email' => $enviarEmail,
            'enviar_whatsapp' => $enviarWhatsapp,
            'frequencia_dias' => $frequenciaDias,
            'horario_envio' => $horarioEnvio,
            'data_inicio_agendamento' => $dataInicioAgendamento,
            'proximo_envio_em' => $nextRun->format('Y-m-d H:i:s'),
            'ultimo_envio_em' => null,
            'ultimo_status' => null,
            'ultima_mensagem' => null,
            'ativo' => $ativo,
        ];
    }

    private function buildDeliveryPayload(array $programacao): array
    {
        $clienteId = $this->sanitizeId($programacao['cliente_id'] ?? null);
        $contaId = $this->sanitizeId($programacao['conta_id'] ?? null);
        $campanhaId = $this->sanitizeId($programacao['campanha_id'] ?? null);
        $periodo = (string) ($programacao['periodo'] ?? '30');

        $cliente = $clienteId ? $this->clienteModel->getById($clienteId) : null;
        $conta = $contaId ? $this->contaModel->getById($contaId) : null;
        $campanha = $campanhaId ? $this->campanhaModel->getById($campanhaId) : null;

        [$dataInicio, $dataFim] = $this->resolveReportDates($periodo, (string) ($programacao['data_inicio'] ?? ''), (string) ($programacao['data_fim'] ?? ''));

        $query = [
            'cliente_id' => $clienteId ?: '',
            'conta_id' => $contaId ?: '',
            'campanha_id' => $campanhaId ?: '',
            'campanha_status' => (string) ($programacao['campanha_status'] ?? ''),
            'periodo' => $periodo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];

        return [
            'destino_email' => (string) $programacao['destino_email'],
            'destino_whatsapp' => (string) ($programacao['destino_whatsapp'] ?? ''),
            'destino_nome' => (string) ($programacao['destino_nome'] ?? ''),
            'cliente_id' => $clienteId ?: null,
            'cliente_nome' => $cliente['nome'] ?? 'Todos os clientes',
            'conta_nome' => $conta['nome'] ?? 'Todas as contas',
            'campanha_nome' => $campanha['nome'] ?? 'Todas as campanhas',
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'query' => $query,
        ];
    }

    private function normalizeForPage(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'cliente_id' => $this->sanitizeId($row['cliente_id'] ?? null),
            'conta_id' => $this->sanitizeId($row['conta_id'] ?? null),
            'campanha_id' => $this->sanitizeId($row['campanha_id'] ?? null),
            'campanha_status' => (string) ($row['campanha_status'] ?? ''),
            'periodo' => (string) ($row['periodo'] ?? '30'),
            'data_inicio' => (string) ($row['data_inicio'] ?? ''),
            'data_fim' => (string) ($row['data_fim'] ?? ''),
            'destino_email' => (string) ($row['destino_email'] ?? ''),
            'destino_whatsapp' => (string) ($row['destino_whatsapp'] ?? ''),
            'destino_nome' => (string) ($row['destino_nome'] ?? ''),
            'enviar_email' => !empty($row['enviar_email']) ? 1 : 0,
            'enviar_whatsapp' => !empty($row['enviar_whatsapp']) ? 1 : 0,
            'frequencia_dias' => (int) ($row['frequencia_dias'] ?? 7),
            'horario_envio' => substr((string) ($row['horario_envio'] ?? '07:00:00'), 0, 5),
            'data_inicio_agendamento' => (string) ($row['data_inicio_agendamento'] ?? ''),
            'proximo_envio_em' => (string) ($row['proximo_envio_em'] ?? ''),
            'ultimo_envio_em' => (string) ($row['ultimo_envio_em'] ?? ''),
            'ultimo_status' => (string) ($row['ultimo_status'] ?? ''),
            'ultima_mensagem' => (string) ($row['ultima_mensagem'] ?? ''),
            'ativo' => !empty($row['ativo']) ? 1 : 0,
        ];
    }

    private function sanitizeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '07:00:00';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) !== 1) {
            throw new RuntimeException('Horario invalido.');
        }

        return $value;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function calculateFirstRun(string $startDate, string $time, int $frequencyDays): DateTimeImmutable
    {
        $candidate = new DateTimeImmutable($startDate . ' ' . $time);
        $now = new DateTimeImmutable('now');

        while ($candidate < $now) {
            $candidate = $candidate->modify('+' . $frequencyDays . ' days');
        }

        return $candidate;
    }

    private function calculateNextRun(DateTimeImmutable $reference, int $frequencyDays, DateTimeImmutable $now): DateTimeImmutable
    {
        $candidate = $reference;

        do {
            $candidate = $candidate->modify('+' . $frequencyDays . ' days');
        } while ($candidate <= $now);

        return $candidate;
    }

    private function resolveReportDates(string $periodo, string $dataInicio, string $dataFim): array
    {
        if ($periodo === 'custom' && $this->isValidDate($dataInicio) && $this->isValidDate($dataFim)) {
            return [$dataInicio, $dataFim];
        }

        $dias = (int) $periodo;
        if ($dias <= 0) {
            $dias = 30;
        }

        $fim = new DateTimeImmutable('yesterday');
        $inicio = $fim->modify('-' . max(0, $dias - 1) . ' days');

        return [
            $inicio->format('Y-m-d'),
            $fim->format('Y-m-d'),
        ];
    }
}
