<?php

class ConexoesPageService
{
    private CanalEmail $canalEmailModel;
    private CanalWhatsapp $canalWhatsappModel;
    private int $empresaId;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->empresaId = $empresaId;
        $this->canalEmailModel = new CanalEmail($conn, $empresaId);
        $this->canalWhatsappModel = new CanalWhatsapp($conn, $empresaId);
    }

    public function getPageData(): array
    {
        $emailConfig = $this->canalEmailModel->get();
        $whatsappConfig = WhatsAppConnectionConfigResolver::resolve(
            $this->empresaId,
            $this->canalWhatsappModel->get()
        );
        $statusConexao = $emailConfig['status_conexao'] ?? 'inativo';
        $ultimoTesteEm = $emailConfig['ultimo_teste_em'] ?? null;
        $observacaoErro = $emailConfig['observacao_erro'] ?? null;
        $statusWhatsapp = $whatsappConfig['status_conexao'] ?? 'inativo';
        $ultimoTesteWhatsapp = $whatsappConfig['ultimo_teste_em'] ?? null;
        $observacaoWhatsapp = $whatsappConfig['observacao_erro'] ?? null;

        return [
            'email_config' => $emailConfig,
            'status_conexao' => $statusConexao,
            'ultimo_teste_em' => $ultimoTesteEm,
            'observacao_erro' => $observacaoErro,
            'status_badge' => $this->resolveBadge($statusConexao),
            'whatsapp_config' => $whatsappConfig,
            'whatsapp_status_conexao' => $statusWhatsapp,
            'whatsapp_ultimo_teste_em' => $ultimoTesteWhatsapp,
            'whatsapp_observacao_erro' => $observacaoWhatsapp,
            'whatsapp_status_badge' => $this->resolveBadge($statusWhatsapp),
        ];
    }

    private function resolveBadge(string $status): array
    {
        return match ($status) {
            'ativo' => ['class' => 'badge-green', 'label' => 'Conectado'],
            'erro' => ['class' => 'badge-red', 'label' => 'Com erro'],
            default => ['class' => 'badge-muted', 'label' => 'Não testado'],
        };
    }
}
