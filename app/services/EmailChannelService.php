<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class EmailChannelService
{
    public static function testar(array $config, string $destinoTeste): array
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

            $mail->addAddress($destinoTeste);
            $mail->isHTML(true);
            $mail->Subject = 'Teste de conexão SMTP - Dashboard ALP';
            $mail->Body = '
                <div style="font-family: Inter, Arial, sans-serif; font-size:14px; color:#111827;">
                    <h2 style="margin:0 0 12px;">Conexão SMTP realizada com sucesso</h2>
                    <p style="margin:0;">Seu canal de email está pronto para envio de relatórios.</p>
                </div>
            ';
            $mail->AltBody = 'Conexão SMTP realizada com sucesso. Seu canal de email está pronto para envio de relatórios.';

            $mail->send();

            return [
                'success' => true,
                'message' => 'Email de teste enviado com sucesso.'
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $mail->ErrorInfo ?: $e->getMessage()
            ];
        }
    }
}