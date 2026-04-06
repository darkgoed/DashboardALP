<?php

class PerfilUpdateService
{
    private Usuario $usuarioModel;

    public function __construct(PDO $conn)
    {
        $this->usuarioModel = new Usuario($conn);
    }

    public function update(int $usuarioId, array $post, array $files): string
    {
        $usuario = $this->usuarioModel->findById($usuarioId);

        if (!$usuario) {
            throw new RuntimeException('Usuario nao encontrado.');
        }

        $nome = trim((string) ($post['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($post['email'] ?? '')));
        $telefone = trim((string) ($post['telefone'] ?? ''));
        $senhaAtual = (string) ($post['senha_atual'] ?? '');
        $novaSenha = (string) ($post['nova_senha'] ?? '');
        $confirmarNovaSenha = (string) ($post['confirmar_nova_senha'] ?? '');
        $removerFoto = ($post['remover_foto'] ?? '') === '1';
        $fotoUpload = $files['foto'] ?? null;

        $old = [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'foto' => $removerFoto ? '' : (string) ($usuario['foto'] ?? ''),
        ];

        $errors = [];
        $nomeAtual = trim((string) ($usuario['nome'] ?? ''));
        $emailAtual = mb_strtolower(trim((string) ($usuario['email'] ?? '')));
        $telefoneAtual = trim((string) ($usuario['telefone'] ?? ''));
        $houveAlteracaoFoto = $removerFoto;

        if ($fotoUpload && (int) ($fotoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $houveAlteracaoFoto = true;
        }

        $alterouDadosBasicos = $nome !== $nomeAtual || $email !== $emailAtual || $telefone !== $telefoneAtual;
        $alterouSenha = $novaSenha !== '' || $confirmarNovaSenha !== '';
        $exigeSenhaAtual = $alterouDadosBasicos || $alterouSenha;

        if ($nome === '') {
            $errors['nome'] = 'Informe seu nome.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail valido.';
        }

        if ($exigeSenhaAtual && $senhaAtual === '') {
            $errors['senha_atual'] = 'Informe sua senha atual para confirmar as alteracoes.';
        } elseif ($exigeSenhaAtual && !password_verify($senhaAtual, (string) ($usuario['senha_hash'] ?? ''))) {
            $errors['senha_atual'] = 'A senha atual informada nao confere.';
        }

        if ($novaSenha !== '' && mb_strlen($novaSenha) < 8) {
            $errors['nova_senha'] = 'A nova senha deve ter pelo menos 8 caracteres.';
        }

        if ($novaSenha !== '' && $novaSenha !== $confirmarNovaSenha) {
            $errors['confirmar_nova_senha'] = 'A confirmacao da nova senha nao confere.';
        }

        if ($novaSenha === '' && $confirmarNovaSenha !== '') {
            $errors['nova_senha'] = 'Informe a nova senha antes de confirmar.';
        }

        $fotoPath = $removerFoto ? '' : (string) ($usuario['foto'] ?? '');
        $fotoAnterior = (string) ($usuario['foto'] ?? '');
        $novoArquivoGerado = null;

        if ($fotoUpload && (int) ($fotoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int) ($fotoUpload['error'] ?? UPLOAD_ERR_OK);

            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors['foto'] = 'Nao foi possivel enviar a foto.';
            } else {
                $maxBytes = 3 * 1024 * 1024;
                $tmpName = (string) ($fotoUpload['tmp_name'] ?? '');
                $fileSize = (int) ($fotoUpload['size'] ?? 0);

                if ($fileSize <= 0 || $fileSize > $maxBytes) {
                    $errors['foto'] = 'A foto deve ter ate 3 MB.';
                } elseif (!is_uploaded_file($tmpName)) {
                    $errors['foto'] = 'Arquivo de foto invalido.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = (string) $finfo->file($tmpName);
                    $extensoesPermitidas = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];

                    if (!isset($extensoesPermitidas[$mimeType])) {
                        $errors['foto'] = 'Use uma imagem JPG, PNG ou WEBP.';
                    } else {
                        $uploadDir = BASE_PATH . '/public/uploads/usuarios';

                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                            $errors['foto'] = 'Nao foi possivel preparar o diretorio da foto.';
                        } else {
                            $nomeArquivo = sprintf(
                                'usuario-%d-%s.%s',
                                $usuarioId,
                                bin2hex(random_bytes(8)),
                                $extensoesPermitidas[$mimeType]
                            );
                            $destinoAbsoluto = $uploadDir . '/' . $nomeArquivo;

                            if (!move_uploaded_file($tmpName, $destinoAbsoluto)) {
                                $errors['foto'] = 'Nao foi possivel salvar a foto enviada.';
                            } else {
                                $fotoPath = '/uploads/usuarios/' . $nomeArquivo;
                                $old['foto'] = $fotoPath;
                                $novoArquivoGerado = $destinoAbsoluto;
                            }
                        }
                    }
                }
            }
        }

        if (empty($errors) && $this->usuarioModel->emailExistsForOtherUser($email, $usuarioId)) {
            $errors['email'] = 'Ja existe outro usuario com este e-mail.';
        }

        if (!empty($errors)) {
            if ($novoArquivoGerado && is_file($novoArquivoGerado)) {
                @unlink($novoArquivoGerado);
            }

            throw new FormValidationException($errors, $old);
        }

        $data = [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'foto' => $fotoPath,
        ];

        if ($novaSenha !== '') {
            $data['senha_hash'] = password_hash($novaSenha, PASSWORD_DEFAULT);
        }

        try {
            $salvo = $this->usuarioModel->updateProfile($usuarioId, $data);

            if (!$salvo) {
                throw new RuntimeException('Nao foi possivel salvar o perfil.');
            }

            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_email'] = $email;
            $_SESSION['usuario_foto'] = $fotoPath;

            if (
                $houveAlteracaoFoto
                && $fotoAnterior !== ''
                && $fotoAnterior !== $fotoPath
                && str_starts_with($fotoAnterior, '/uploads/usuarios/')
            ) {
                $arquivoAnterior = BASE_PATH . '/public' . $fotoAnterior;
                if (is_file($arquivoAnterior)) {
                    @unlink($arquivoAnterior);
                }
            }
        } catch (Throwable $e) {
            if ($novoArquivoGerado && is_file($novoArquivoGerado)) {
                @unlink($novoArquivoGerado);
            }

            throw $e;
        }

        return 'Perfil atualizado com sucesso.';
    }
}
