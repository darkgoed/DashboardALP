<?php

class ConviteAcceptanceService
{
    private PDO $conn;
    private ConviteEmpresaService $conviteService;
    private ConviteEmpresa $conviteModel;
    private Usuario $usuarioModel;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->conviteService = new ConviteEmpresaService($conn);
        $this->conviteModel = new ConviteEmpresa($conn);
        $this->usuarioModel = new Usuario($conn);
    }

    public function getPageData(string $token): array
    {
        $erro = '';
        $convite = null;

        if ($token !== '') {
            $resultadoConvite = $this->conviteService->validarConvite($token);

            if (!empty($resultadoConvite['ok'])) {
                $convite = $resultadoConvite['convite'];
            } else {
                $erro = $resultadoConvite['message'] ?? 'Convite inválido.';
            }
        } else {
            $erro = 'Token do convite não informado.';
        }

        return [
            'erro' => $erro,
            'sucesso' => '',
            'convite' => $convite,
        ];
    }

    public function accept(string $token, array $data): array
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        $senha = (string) ($data['senha'] ?? '');
        $confirmarSenha = (string) ($data['confirmar_senha'] ?? '');
        $erro = '';
        $sucesso = '';

        $resultadoAtual = $this->conviteService->validarConvite($token);

        if (empty($resultadoAtual['ok'])) {
            return [
                'erro' => $resultadoAtual['message'] ?? 'Este convite não está mais disponível.',
                'sucesso' => '',
                'convite' => null,
            ];
        }

        $convite = $resultadoAtual['convite'];

        if ($nome === '') {
            $erro = 'Informe seu nome.';
        } elseif ($senha === '') {
            $erro = 'Informe sua senha.';
        } elseif (mb_strlen($senha) < 8) {
            $erro = 'A senha deve ter pelo menos 8 caracteres.';
        } elseif ($senha !== $confirmarSenha) {
            $erro = 'As senhas não conferem.';
        } else {
            try {
                $this->conn->beginTransaction();

                $email = mb_strtolower(trim((string) $convite['email']));
                $empresaId = (int) $convite['empresa_id'];
                $perfil = !empty($convite['perfil']) ? (string) $convite['perfil'] : 'admin';

                if ($perfil === 'owner') {
                    $perfil = 'admin';
                }

                $usuarioExistente = $this->usuarioModel->findByEmail($email);

                if ($usuarioExistente) {
                    if ($this->conn->inTransaction()) {
                        $this->conn->rollBack();
                    }

                    $erro = 'Já existe uma conta com este e-mail. Faça login ou use a recuperação de senha para acessar.';
                } else {
                    $usuarioId = $this->usuarioModel->create([
                        'uuid' => function_exists('uuidv4') ? uuidv4() : $this->uuidv4Local(),
                        'nome' => $nome,
                        'email' => $email,
                        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                        'status' => 'ativo',
                    ]);

                    $stmt = $this->conn->prepare("
                        INSERT INTO usuarios_empresas (
                            usuario_id,
                            empresa_id,
                            perfil,
                            status,
                            is_principal
                        ) VALUES (
                            :usuario_id,
                            :empresa_id,
                            :perfil,
                            'ativo',
                            1
                        )
                    ");
                    $stmt->execute([
                        ':usuario_id' => $usuarioId,
                        ':empresa_id' => $empresaId,
                        ':perfil' => $perfil,
                    ]);

                    $aceito = $this->conviteModel->markAsAccepted((int) $convite['id']);

                    if (!$aceito) {
                        throw new RuntimeException('Não foi possível concluir o aceite do convite.');
                    }

                    $this->conn->commit();
                    $sucesso = 'Conta criada com sucesso. Agora você já pode entrar no painel.';
                    $convite = null;
                }
            } catch (Throwable $e) {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }

                $erro = $e->getMessage();
            }
        }

        return [
            'erro' => $erro,
            'sucesso' => $sucesso,
            'convite' => $convite,
        ];
    }

    public static function empresaNome(array $convite): string
    {
        $nomeFantasia = trim((string) ($convite['nome_fantasia'] ?? ''));
        $razaoSocial = trim((string) ($convite['razao_social'] ?? ''));

        if ($nomeFantasia !== '') {
            return $nomeFantasia;
        }

        if ($razaoSocial !== '') {
            return $razaoSocial;
        }

        return 'Empresa convidada';
    }

    private function uuidv4Local(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
