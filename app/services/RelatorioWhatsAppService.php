<?php

class RelatorioWhatsAppService
{
    private int $empresaId;
    private CanalWhatsapp $canalWhatsappModel;
    private EnvioRelatorio $envioModel;
    private RelatorioPublicLinkService $publicLinkService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->empresaId = $empresaId;
        $this->canalWhatsappModel = new CanalWhatsapp($conn, $empresaId);
        $this->envioModel = new EnvioRelatorio($conn);
        $this->publicLinkService = new RelatorioPublicLinkService();
    }

    public function enviar(array $payload): array
    {
        $destinoWhatsapp = WhatsAppChannelService::normalizePhone((string) ($payload['destino_whatsapp'] ?? ''));
        $clienteId = !empty($payload['cliente_id']) ? (int) $payload['cliente_id'] : null;

        if ($destinoWhatsapp === '') {
            return [
                'success' => false,
                'message' => 'Informe um numero de WhatsApp valido.',
            ];
        }

        $config = $this->canalWhatsappModel->get();
        if (!$config) {
            return [
                'success' => false,
                'message' => 'Nenhuma configuracao do canal WhatsApp foi encontrada.',
            ];
        }

        $previewUrl = $this->publicLinkService->generateUrl($this->empresaId, (array) ($payload['query'] ?? []));
        $resultado = WhatsAppChannelService::enviar($config, [
            'to' => $destinoWhatsapp,
            'message' => $this->buildText($payload, $previewUrl),
        ]);

        $this->envioModel->create([
            'empresa_id' => $this->empresaId,
            'cliente_id' => $clienteId,
            'tipo' => (string) ($payload['tipo_envio'] ?? 'manual_whatsapp'),
            'status' => !empty($resultado['success']) ? 'sucesso' : 'erro',
            'mensagem' => !empty($resultado['success'])
                ? 'Relatorio enviado para WhatsApp ' . $destinoWhatsapp
                : (string) ($resultado['message'] ?? 'Falha no envio do relatorio por WhatsApp.'),
        ]);

        if (!empty($resultado['success'])) {
            $this->canalWhatsappModel->updateStatus('ativo', null);
        } else {
            $this->canalWhatsappModel->updateStatus('erro', (string) ($resultado['message'] ?? 'Falha no envio do relatorio por WhatsApp.'));
        }

        return $resultado;
    }

    private function buildText(array $payload, string $previewUrl): string
    {
        return "Segue o link do relatorio de performance solicitado.\n\n"
            . "Cliente: " . (string) ($payload['cliente_nome'] ?? 'Todos os clientes') . "\n"
            . "Periodo: " . $this->formatDate((string) ($payload['data_inicio'] ?? '')) . " ate " . $this->formatDate((string) ($payload['data_fim'] ?? '')) . "\n\n"
            . "Relatorio completo: " . $previewUrl;
    }

    private function formatDate(string $value): string
    {
        if ($value === '') {
            return '-';
        }

        try {
            return (new DateTime($value))->format('d/m/Y');
        } catch (Throwable $e) {
            return $value;
        }
    }
}
