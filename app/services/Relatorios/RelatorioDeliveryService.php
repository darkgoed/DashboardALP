<?php

class RelatorioDeliveryService
{
    private int $empresaId;
    private Cliente $clienteModel;
    private ContaAds $contaModel;
    private Campanha $campanhaModel;
    private RelatorioEmailService $emailService;
    private RelatorioWhatsAppService $whatsAppService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->empresaId = $empresaId;
        $this->clienteModel = new Cliente($conn, $empresaId);
        $this->contaModel = new ContaAds($conn, $empresaId);
        $this->campanhaModel = new Campanha($conn, $empresaId);
        $this->emailService = new RelatorioEmailService($conn, $empresaId);
        $this->whatsAppService = new RelatorioWhatsAppService($conn, $empresaId);
    }

    public function send(array $input): array
    {
        $clienteId = isset($input['cliente_id']) && $input['cliente_id'] !== '' ? (int) $input['cliente_id'] : 0;
        $contaId = isset($input['conta_id']) && $input['conta_id'] !== '' ? (int) $input['conta_id'] : 0;
        $campanhaId = isset($input['campanha_id']) && $input['campanha_id'] !== '' ? (int) $input['campanha_id'] : 0;
        $campanhaStatus = isset($input['campanha_status']) && $input['campanha_status'] !== ''
            ? strtoupper(trim((string) $input['campanha_status']))
            : '';
        $periodo = trim((string) ($input['periodo'] ?? '30'));
        $dataInicio = trim((string) ($input['data_inicio'] ?? ''));
        $dataFim = trim((string) ($input['data_fim'] ?? ''));
        $canal = strtolower(trim((string) ($input['canal'] ?? 'email')));
        $destinoEmail = mb_strtolower(trim((string) ($input['destino_email'] ?? '')));
        $destinoWhatsapp = trim((string) ($input['destino_whatsapp'] ?? ''));
        $destinoNome = trim((string) ($input['destino_nome'] ?? ''));

        $periodosPermitidos = ['1', '3', '7', '14', '15', '30', '90', '365', 'custom'];
        if (!in_array($periodo, $periodosPermitidos, true)) {
            $periodo = '30';
        }

        if ($periodo !== 'custom') {
            $dias = (int) $periodo;
            if ($dias > 0) {
                $dataFim = date('Y-m-d', strtotime('-1 day'));
                $dataInicio = date('Y-m-d', strtotime($dataFim . ' -' . ($dias - 1) . ' days'));
            }
        }

        if ($dataInicio === '') {
            $dataInicio = date('Y-m-d', strtotime('-30 days'));
        }

        if ($dataFim === '') {
            $dataFim = date('Y-m-d', strtotime('-1 day'));
        }

        if (!in_array($canal, ['email', 'whatsapp'], true)) {
            throw new RuntimeException('Canal de envio invalido.');
        }

        if ($canal === 'email' && ($destinoEmail === '' || !filter_var($destinoEmail, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('Informe um e-mail válido para envio do relatório.');
        }

        if ($canal === 'whatsapp' && WhatsAppChannelService::normalizePhone($destinoWhatsapp) === '') {
            throw new RuntimeException('Informe um numero de WhatsApp valido para envio do relatorio.');
        }

        $cliente = $clienteId > 0 ? $this->clienteModel->getById($clienteId) : null;
        $conta = $contaId > 0 ? $this->contaModel->getById($contaId) : null;
        $campanha = $campanhaId > 0 ? $this->campanhaModel->getById($campanhaId) : null;

        $query = [
            'cliente_id' => $clienteId ?: '',
            'conta_id' => $contaId ?: '',
            'campanha_id' => $campanhaId ?: '',
            'campanha_status' => $campanhaStatus,
            'periodo' => $periodo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];

        $payload = [
            'destino_nome' => $destinoNome,
            'cliente_id' => $clienteId ?: null,
            'cliente_nome' => $cliente['nome'] ?? 'Todos os clientes',
            'conta_nome' => $conta['nome'] ?? 'Todas as contas',
            'campanha_nome' => $campanha['nome'] ?? 'Todas as campanhas',
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'query' => $query,
        ];

        if ($canal === 'whatsapp') {
            $payload['destino_whatsapp'] = $destinoWhatsapp;
            $payload['tipo_envio'] = 'manual_whatsapp';
            $resultado = $this->whatsAppService->enviar($payload);
        } else {
            $payload['destino_email'] = $destinoEmail;
            $payload['tipo_envio'] = 'manual_email';
            $resultado = $this->emailService->enviar($payload);
        }

        return [
            'query' => $query,
            'resultado' => $resultado,
            'canal' => $canal,
            'destino_email' => $destinoEmail,
            'destino_whatsapp' => $destinoWhatsapp,
        ];
    }
}
