<?php

class PasswordRecoveryPageService
{
    private PasswordResetService $passwordResetService;

    public function __construct(PDO $conn)
    {
        $this->passwordResetService = new PasswordResetService($conn);
    }

    public function getInitialState(string $token): array
    {
        return [
            'token' => $token,
            'erro' => '',
            'sucesso' => '',
            'modo' => $token !== '' ? 'reset' : 'request',
            'token_valido' => false,
            'usuario_token' => null,
        ];
    }

    public function handle(array $state, array $post): array
    {
        if ($state['modo'] === 'request') {
            $email = mb_strtolower(trim((string) ($post['email'] ?? '')));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $state['erro'] = 'Informe um e-mail valido.';
                return $state;
            }

            $this->passwordResetService->enviarLink($email);
            $state['sucesso'] = 'Se existir uma conta vinculada a este e-mail, enviamos um link de recuperacao.';

            return $state;
        }

        $senha = (string) ($post['senha'] ?? '');
        $confirmarSenha = (string) ($post['confirmar_senha'] ?? '');

        if ($senha === '') {
            $state['erro'] = 'Informe a nova senha.';
        } elseif (mb_strlen($senha) < 8) {
            $state['erro'] = 'A senha deve ter pelo menos 8 caracteres.';
        } elseif ($senha !== $confirmarSenha) {
            $state['erro'] = 'As senhas nao conferem.';
        } else {
            $resultado = $this->passwordResetService->redefinirSenha($state['token'], $senha);

            if (!empty($resultado['ok'])) {
                $state['sucesso'] = $resultado['message'] ?? 'Senha atualizada com sucesso.';
            } else {
                $state['erro'] = $resultado['message'] ?? 'Nao foi possivel redefinir a senha.';
            }
        }

        return $state;
    }

    public function loadResetValidation(array $state): array
    {
        if ($state['modo'] === 'reset' && $state['sucesso'] === '') {
            $validacaoToken = $this->passwordResetService->validarToken($state['token']);

            if (!empty($validacaoToken['ok'])) {
                $state['token_valido'] = true;
                $state['usuario_token'] = $validacaoToken['usuario'];
            } elseif ($state['erro'] === '') {
                $state['erro'] = $validacaoToken['message'] ?? 'Link de recuperacao invalido.';
            }
        }

        return $state;
    }
}
