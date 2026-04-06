<?php

class WhatsAppChannelAjaxService
{
    private CanalWhatsapp $canalWhatsappModel;
    private int $empresaId;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->empresaId = $empresaId;
        $this->canalWhatsappModel = new CanalWhatsapp($conn, $empresaId);
    }

    public function save(array $input): array
    {
        $atual = $this->canalWhatsappModel->get();
        $nomeConexao = trim((string) ($input['nome_conexao'] ?? ''));
        $numeroTestePadrao = trim((string) ($input['numero_teste_padrao'] ?? ''));

        if ($nomeConexao === '') {
            throw new RuntimeException('Informe um nome para a conexao do WhatsApp.');
        }

        $normalizedPhone = $numeroTestePadrao !== ''
            ? WhatsAppChannelService::normalizePhone($numeroTestePadrao)
            : '';

        if ($numeroTestePadrao !== '' && $normalizedPhone === '') {
            throw new RuntimeException('Informe um numero padrao de teste em formato valido.');
        }

        $resolved = WhatsAppConnectionConfigResolver::resolve($this->empresaId, $atual);
        $config = $resolved + [
            'nome_conexao' => $nomeConexao,
            'numero_teste_padrao' => $normalizedPhone,
        ];

        WhatsAppChannelService::validateConfig($config);
        $this->canalWhatsappModel->save($config);

        return [
            'success' => true,
            'message' => 'Configuracao do WhatsApp salva com sucesso. A conexao tecnica do bridge permanece automatica.',
        ];
    }

    public function test(array $input): array
    {
        $config = WhatsAppConnectionConfigResolver::resolve($this->empresaId, $this->canalWhatsappModel->get());

        $resultado = WhatsAppChannelService::testar($config);

        if (!empty($resultado['success'])) {
            $this->canalWhatsappModel->updateStatus('ativo', null);
        } else {
            $this->canalWhatsappModel->updateStatus('erro', (string) ($resultado['message'] ?? 'Falha ao testar o WhatsApp.'));
        }

        return $resultado;
    }
}
