<?php

class LoginPageService
{
    private PDO $conn;
    private Usuario $usuarioModel;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->usuarioModel = new Usuario($conn);
    }

    public function getAccessPolicy(): array
    {
        return [
            'sso_ativo' => false,
            'cadastro_publico_ativo' => false,
            'acesso_por_convite' => true,
        ];
    }

    public function resolveInitialRedirect(): ?string
    {
        return Auth::check() ? routeUrl('dashboard') : null;
    }

    public function authenticate(array $input): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        $senha = (string) ($input['senha'] ?? '');

        if ($email === '' || $senha === '') {
            return [
                'erro' => 'Preencha e-mail e senha.',
                'redirect' => null,
            ];
        }

        $auth = $this->usuarioModel->autenticar($email, $senha);

        if (!$auth) {
            return [
                'erro' => 'E-mail, senha ou vinculo com empresa invalido.',
                'redirect' => null,
            ];
        }

        Auth::login($auth['usuario'], $auth['empresa_id'], $auth['perfil'] ?? null);

        try {
            if (!Auth::isPlatformRoot()) {
                $licencaService = new EmpresaLicencaService($this->conn);
                $statusLicenca = $licencaService->sincronizarStatus((int) Auth::getEmpresaId());

                if (($statusLicenca['status_acesso'] ?? 'bloqueada') === 'bloqueada') {
                    $_SESSION['licenca_bloqueada'] = [
                        'motivo' => $statusLicenca['motivo'] ?? 'Sua licenca expirou.',
                        'status_assinatura' => $statusLicenca['status_assinatura'] ?? 'bloqueada',
                        'data_vencimento' => $statusLicenca['data_vencimento'] ?? null,
                        'data_limite_tolerancia' => $statusLicenca['data_limite_tolerancia'] ?? null,
                    ];

                    return [
                        'erro' => '',
                        'redirect' => routeUrl('licenca/bloqueada'),
                    ];
                }
            }
        } catch (Throwable $e) {
            Auth::logout();

            return [
                'erro' => 'Nao foi possivel validar o acesso da empresa.',
                'redirect' => null,
            ];
        }

        return [
            'erro' => '',
            'redirect' => routeUrl('dashboard'),
        ];
    }
}
