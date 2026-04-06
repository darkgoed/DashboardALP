<?php

class EmailChannelAjaxService
{
    private PDO $conn;
    private int $empresaId;
    private CanalEmail $canalEmail;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->canalEmail = new CanalEmail($conn, $empresaId);
    }

    public function save(array $input): array
    {
        $nomeRemetente = trim((string) ($input['nome_remetente'] ?? ''));
        $emailRemetente = trim((string) ($input['email_remetente'] ?? ''));
        $emailReplyTo = trim((string) ($input['email_reply_to'] ?? ''));
        $smtpHost = trim((string) ($input['smtp_host'] ?? ''));
        $smtpPort = (int) ($input['smtp_port'] ?? 587);
        $smtpSecure = trim((string) ($input['smtp_secure'] ?? 'tls'));
        $smtpUser = trim((string) ($input['smtp_user'] ?? ''));
        $smtpPass = trim((string) ($input['smtp_pass'] ?? ''));

        if ($nomeRemetente === '') {
            throw new RuntimeException('Informe o nome do remetente.');
        }

        if ($emailRemetente === '' || !filter_var($emailRemetente, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Informe um email remetente valido.');
        }

        if ($emailReplyTo !== '' && !filter_var($emailReplyTo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Informe um email de resposta valido.');
        }

        if ($smtpHost === '') {
            throw new RuntimeException('Informe o host SMTP.');
        }

        if ($smtpPort <= 0) {
            throw new RuntimeException('Informe uma porta SMTP valida.');
        }

        if (!in_array($smtpSecure, ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('Tipo de criptografia SMTP invalido.');
        }

        if ($smtpUser === '') {
            throw new RuntimeException('Informe o usuario SMTP.');
        }

        $atual = $this->canalEmail->get();

        if ($smtpPass === '') {
            if ($atual && !empty($atual['smtp_pass'])) {
                $smtpPass = (string) $atual['smtp_pass'];
            } else {
                throw new RuntimeException('Informe a senha SMTP.');
            }
        }

        $ok = $this->canalEmail->save([
            'nome_remetente' => $nomeRemetente,
            'email_remetente' => $emailRemetente,
            'email_reply_to' => $emailReplyTo !== '' ? $emailReplyTo : null,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_secure' => $smtpSecure,
            'smtp_user' => $smtpUser,
            'smtp_pass' => $smtpPass,
        ]);

        if (!$ok) {
            throw new RuntimeException('Nao foi possivel salvar a configuracao de email.');
        }

        return [
            'success' => true,
            'message' => 'Configuracao de email salva com sucesso.',
        ];
    }

    public function test(array $input): array
    {
        $destinoTeste = trim((string) ($input['email_teste'] ?? ''));

        if ($destinoTeste === '' || !filter_var($destinoTeste, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Informe um email de teste valido.');
        }

        $config = $this->canalEmail->get();

        if (!$config) {
            throw new RuntimeException('Nenhuma configuracao SMTP foi encontrada para esta empresa.');
        }

        $resultado = EmailChannelService::testar($config, $destinoTeste);

        if (!empty($resultado['success'])) {
            $this->canalEmail->updateStatus('ativo', null);

            return [
                'success' => true,
                'message' => (string) $resultado['message'],
            ];
        }

        $mensagem = (string) ($resultado['message'] ?? 'Falha no teste.');
        $this->canalEmail->updateStatus('erro', $mensagem);

        throw new RuntimeException($mensagem);
    }
}
