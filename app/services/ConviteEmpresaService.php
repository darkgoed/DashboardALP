<?php

class ConviteEmpresaService
{
    private PDO $conn;
    private ConviteEmpresa $conviteModel;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->conviteModel = new ConviteEmpresa($conn);
    }

    public function gerarToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function montarLink(string $token): string
    {
        return appUrl("aceitar-convite?token={$token}");
    }

    public function criarConviteAdmin(
        int $empresaId,
        string $nome,
        string $email,
        string $perfil = 'owner',
        int $duracaoDias = 7,
        bool $cancelarPendentesAnteriores = true
    ): array {
        $nome = trim($nome);
        $email = mb_strtolower(trim($email));

        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida para gerar convite.');
        }

        if ($nome === '') {
            throw new InvalidArgumentException('Nome do responsável é obrigatório.');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail do responsável inválido.');
        }

        if ($duracaoDias < 1) {
            $duracaoDias = 7;
        }

        if ($cancelarPendentesAnteriores) {
            $this->conviteModel->cancelPendingByEmpresaId($empresaId);
        }

        $token = $this->gerarToken();
        $expiresAt = (new DateTime("+{$duracaoDias} days"))->format('Y-m-d H:i:s');

        $conviteId = $this->conviteModel->create([
            'uuid' => uuidv4(),
            'empresa_id' => $empresaId,
            'nome' => $nome,
            'email' => $email,
            'token' => $token,
            'perfil' => $perfil,
            'status' => 'pendente',
            'expires_at' => $expiresAt,
        ]);

        $convite = $this->conviteModel->findById($conviteId);

        if (!$convite) {
            throw new RuntimeException('Convite criado, mas não foi possível recarregar os dados.');
        }

        return [
            'id' => (int) $convite['id'],
            'token' => $token,
            'link' => $this->montarLink($token),
            'expires_at' => $convite['expires_at'],
            'convite' => $convite,
        ];
    }


    public function validarConvite(string $token): array
    {
        $token = trim($token);

        if ($token === '') {
            return [
                'ok' => false,
                'message' => 'Token do convite não informado.',
            ];
        }

        $convite = $this->conviteModel->findByToken($token);

        if (!$convite) {
            return [
                'ok' => false,
                'message' => 'Convite não encontrado.',
            ];
        }

        if (($convite['status'] ?? '') !== 'pendente') {
            return [
                'ok' => false,
                'message' => 'Este convite não está mais disponível.',
                'convite' => $convite,
            ];
        }

        $expiraEm = strtotime((string) ($convite['expires_at'] ?? ''));

        if (!$expiraEm) {
            return [
                'ok' => false,
                'message' => 'Convite inválido.',
                'convite' => $convite,
            ];
        }

        if ($expiraEm < time()) {
            $this->conviteModel->markAsExpiredById((int) $convite['id']);

            $convite['status'] = 'expirado';

            return [
                'ok' => false,
                'message' => 'Este convite expirou.',
                'convite' => $convite,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Convite válido.',
            'convite' => $convite,
            'link' => $this->montarLink($token),
        ];
    }

    public function reenviarConviteAdmin(
        int $empresaId,
        string $nome,
        string $email,
        string $perfil = 'owner',
        int $duracaoDias = 7
    ): array {
        return $this->criarConviteAdmin(
            $empresaId,
            $nome,
            $email,
            $perfil,
            $duracaoDias,
            true
        );
    }

    public function cancelarConvite(int $conviteId): bool
    {
        if ($conviteId <= 0) {
            return false;
        }

        return $this->conviteModel->cancelById($conviteId);
    }

    public function expirarConvitesVencidosDaEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return $this->conviteModel->expirePendingsByEmpresaId($empresaId);
    }

    public function obterConvitePendenteDaEmpresa(int $empresaId): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        $this->expirarConvitesVencidosDaEmpresa($empresaId);

        return $this->conviteModel->findPendingByEmpresaId($empresaId);
    }

    public function enviarConviteAdminEmail(array $convite): array
    {
        $config = EmailChannelService::globalConfigFromEnv();

        if (!$config) {
            return [
                'success' => false,
                'message' => 'SMTP global nao configurado para envio automatico do convite.',
            ];
        }

        $email = mb_strtolower(trim((string) ($convite['email'] ?? '')));
        $nome = trim((string) ($convite['nome'] ?? ''));
        $empresa = trim((string) ($convite['nome_fantasia'] ?? ''));
        $empresa = $empresa !== '' ? $empresa : trim((string) ($convite['razao_social'] ?? ''));
        $link = trim((string) ($convite['link'] ?? ''));
        $expiresAt = trim((string) ($convite['expires_at'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'E-mail do convite invalido para envio.',
            ];
        }

        if ($link === '') {
            return [
                'success' => false,
                'message' => 'Link do convite nao foi gerado.',
            ];
        }

        return EmailChannelService::enviar($config, [
            'to_email' => $email,
            'to_name' => $nome,
            'subject' => 'Convite para acessar o Dashboard ALP',
            'html' => $this->buildInviteHtml($nome, $empresa, $link, $expiresAt),
            'text' => $this->buildInviteText($empresa, $link, $expiresAt),
            'success_message' => 'Convite enviado com sucesso.',
        ]);
    }

    private function buildInviteHtml(string $nome, string $empresa, string $link, string $expiresAt): string
    {
        $saudacao = $nome !== ''
            ? 'Ola, ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '.'
            : 'Ola.';
        $empresaLabel = $empresa !== ''
            ? htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8')
            : 'sua empresa';
        $linkEscaped = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $expiresLabel = $this->formatarExpiracao($expiresAt);
        $loginUrl = htmlspecialchars(appUrl('login'), ENT_QUOTES, 'UTF-8');

        return '
            <div style="font-family: Inter, Arial, sans-serif; font-size:14px; line-height:1.6; color:#111827;">
                <p style="margin:0 0 16px;">' . $saudacao . '</p>
                <p style="margin:0 0 16px;">Voce recebeu um convite para criar o usuario administrador da empresa <strong>' . $empresaLabel . '</strong> no Dashboard ALP.</p>
                <p style="margin:0 0 24px;">
                    <a href="' . $linkEscaped . '" style="display:inline-block;padding:12px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;">Aceitar convite</a>
                </p>
                <p style="margin:0 0 12px;">O link e individual e expira em ' . htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') . '.</p>
                <p style="margin:0 0 8px;">Se o botao nao funcionar, use este link:</p>
                <p style="margin:0 0 16px;word-break:break-all;"><a href="' . $linkEscaped . '">' . $linkEscaped . '</a></p>
                <p style="margin:0;">Se voce ja tiver uma conta vinculada a este e-mail, use a tela de login: <a href="' . $loginUrl . '">' . $loginUrl . '</a></p>
            </div>
        ';
    }

    private function buildInviteText(string $empresa, string $link, string $expiresAt): string
    {
        $empresaLabel = $empresa !== '' ? $empresa : 'sua empresa';

        return "Voce recebeu um convite para criar o usuario administrador da empresa {$empresaLabel} no Dashboard ALP.\n\n"
            . "Use o link abaixo para aceitar o convite:\n"
            . $link . "\n\n"
            . "O link e individual e expira em " . $this->formatarExpiracao($expiresAt) . ".\n\n"
            . "Login: " . appUrl('login');
    }

    private function formatarExpiracao(string $expiresAt): string
    {
        if ($expiresAt === '') {
            return 'breve';
        }

        try {
            return (new DateTime($expiresAt))->format('d/m/Y H:i');
        } catch (Throwable $e) {
            return $expiresAt;
        }
    }
}
