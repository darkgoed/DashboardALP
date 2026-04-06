<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class EmailChannelService
{
    public static function globalConfigFromEnv(): ?array
    {
        $config = [
            'smtp_host' => trim((string) Env::get('MAIL_SMTP_HOST', '')),
            'smtp_port' => (int) Env::get('MAIL_SMTP_PORT', 0),
            'smtp_secure' => trim((string) Env::get('MAIL_SMTP_SECURE', 'tls')),
            'smtp_user' => trim((string) Env::get('MAIL_SMTP_USER', '')),
            'smtp_pass' => (string) Env::get('MAIL_SMTP_PASS', ''),
            'nome_remetente' => trim((string) Env::get('MAIL_FROM_NAME', 'Dashboard ALP')),
            'email_remetente' => trim((string) Env::get('MAIL_FROM_EMAIL', '')),
            'email_reply_to' => trim((string) Env::get('MAIL_REPLY_TO', '')),
        ];

        if (
            $config['smtp_host'] === ''
            || $config['smtp_port'] <= 0
            || $config['smtp_user'] === ''
            || $config['smtp_pass'] === ''
            || $config['email_remetente'] === ''
        ) {
            return null;
        }

        if (!in_array($config['smtp_secure'], ['none', 'tls', 'ssl'], true)) {
            $config['smtp_secure'] = 'tls';
        }

        if ($config['email_reply_to'] === '') {
            $config['email_reply_to'] = null;
        }

        return $config;
    }

    public static function enviar(array $config, array $message): array
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['smtp_user'];
            $mail->Password   = $config['smtp_pass'];
            $mail->Port       = (int) $config['smtp_port'];
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';

            if (!empty($config['smtp_secure']) && $config['smtp_secure'] !== 'none') {
                if ($config['smtp_secure'] === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($config['smtp_secure'] === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
            }

            $mail->setFrom($config['email_remetente'], $config['nome_remetente']);

            if (!empty($config['email_reply_to'])) {
                $mail->addReplyTo($config['email_reply_to'], $config['nome_remetente']);
            }

            $mail->addAddress($message['to_email'], $message['to_name'] ?? '');
            $mail->isHTML(true);
            $mail->Subject = (string) ($message['subject'] ?? '');
            $mail->Body = (string) ($message['html'] ?? '');
            $mail->AltBody = (string) ($message['text'] ?? strip_tags((string) ($message['html'] ?? '')));

            $mail->send();

            return [
                'success' => true,
                'message' => $message['success_message'] ?? 'Email enviado com sucesso.'
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $mail->ErrorInfo ?: $e->getMessage()
            ];
        }
    }

    public static function testar(array $config, string $destinoTeste): array
    {
        return self::enviar($config, [
            'to_email' => $destinoTeste,
            'subject' => 'Teste de conexao SMTP - Dashboard ALP',
            'html' => '
                <div style="font-family: Inter, Arial, sans-serif; font-size:14px; color:#111827;">
                    <h2 style="margin:0 0 12px;">Conexao SMTP realizada com sucesso</h2>
                    <p style="margin:0;">Seu canal de email esta pronto para envio de relatorios.</p>
                </div>
            ',
            'text' => 'Conexao SMTP realizada com sucesso. Seu canal de email esta pronto para envio de relatorios.',
            'success_message' => 'Email de teste enviado com sucesso.'
        ]);
    }
}
