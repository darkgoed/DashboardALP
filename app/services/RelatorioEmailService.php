<?php

class RelatorioEmailService
{
    private PDO $conn;
    private int $empresaId;
    private CanalEmail $canalEmailModel;
    private EnvioRelatorio $envioModel;
    private RelatorioPublicLinkService $publicLinkService;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->canalEmailModel = new CanalEmail($conn, $empresaId);
        $this->envioModel = new EnvioRelatorio($conn);
        $this->publicLinkService = new RelatorioPublicLinkService();
    }

    public function enviar(array $payload): array
    {
        $destino = mb_strtolower(trim((string) ($payload['destino_email'] ?? '')));
        $destinoNome = trim((string) ($payload['destino_nome'] ?? ''));
        $clienteId = !empty($payload['cliente_id']) ? (int) $payload['cliente_id'] : null;

        if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Informe um e-mail de destino valido.',
            ];
        }

        $config = $this->canalEmailModel->get();

        if (!$config) {
            return [
                'success' => false,
                'message' => 'Nenhuma configuracao SMTP da empresa foi encontrada.',
            ];
        }

        $previewUrl = $this->buildReportUrl((array) ($payload['query'] ?? []));
        $subject = $this->buildSubject($payload);
        $tipoEnvio = trim((string) ($payload['tipo_envio'] ?? 'manual_email'));

        $resultado = EmailChannelService::enviar($config, [
            'to_email' => $destino,
            'to_name' => $destinoNome,
            'subject' => $subject,
            'html' => $this->buildHtml($payload, $previewUrl),
            'text' => $this->buildText($payload, $previewUrl),
            'success_message' => 'Relatorio enviado com sucesso.',
        ]);

        $this->envioModel->create([
            'empresa_id' => $this->empresaId,
            'cliente_id' => $clienteId,
            'tipo' => $tipoEnvio !== '' ? $tipoEnvio : 'manual_email',
            'status' => !empty($resultado['success']) ? 'sucesso' : 'erro',
            'mensagem' => !empty($resultado['success'])
                ? 'Relatorio enviado para ' . $destino
                : (string) ($resultado['message'] ?? 'Falha no envio do relatorio.'),
        ]);

        if (!empty($resultado['success'])) {
            $this->canalEmailModel->updateStatus('ativo', null);
        } else {
            $this->canalEmailModel->updateStatus('erro', (string) ($resultado['message'] ?? 'Falha no envio do relatorio.'));
        }

        return $resultado;
    }

    private function buildReportUrl(array $query): string
    {
        return $this->publicLinkService->generateUrl($this->empresaId, $query);
    }

    private function buildSubject(array $payload): string
    {
        $cliente = trim((string) ($payload['cliente_nome'] ?? ''));
        $inicio = trim((string) ($payload['data_inicio'] ?? ''));
        $fim = trim((string) ($payload['data_fim'] ?? ''));

        $base = 'Relatorio de performance - Dashboard ALP';

        if ($cliente !== '') {
            $base .= ' - ' . $cliente;
        }

        if ($inicio !== '' && $fim !== '') {
            $base .= ' - ' . $this->formatDate($inicio) . ' a ' . $this->formatDate($fim);
        }

        return $base;
    }

    private function buildHtml(array $payload, string $previewUrl): string
    {
        $cliente = htmlspecialchars((string) ($payload['cliente_nome'] ?? 'Todos os clientes'), ENT_QUOTES, 'UTF-8');
        $periodo = htmlspecialchars(
            $this->formatDate((string) ($payload['data_inicio'] ?? ''))
            . ' ate '
            . $this->formatDate((string) ($payload['data_fim'] ?? '')),
            ENT_QUOTES,
            'UTF-8'
        );
        $previewUrlEscaped = htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8');

        return '
            <div style="font-family: Inter, Arial, sans-serif; font-size:14px; line-height:1.6; color:#111827;">
                <p style="margin:0 0 16px;">Segue o link do relatorio de performance solicitado.</p>
                <p style="margin:0 0 6px;"><strong>Cliente:</strong> ' . $cliente . '</p>
                <p style="margin:0 0 20px;"><strong>Periodo:</strong> ' . $periodo . '</p>
                <p style="margin:0 0 24px;">
                    <a href="' . $previewUrlEscaped . '" style="display:inline-block;padding:12px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;">Abrir relatorio completo</a>
                </p>
                <p style="margin:0 0 8px;">Se preferir, acesse diretamente pelo link abaixo:</p>
                <p style="margin:0;word-break:break-all;"><a href="' . $previewUrlEscaped . '">' . $previewUrlEscaped . '</a></p>
            </div>
        ';
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
