<?php

class PerfilPageService
{
    private Usuario $usuarioModel;

    public function __construct(PDO $conn)
    {
        $this->usuarioModel = new Usuario($conn);
    }

    public function getPageData(int $usuarioId): array
    {
        $usuario = $this->usuarioModel->findById($usuarioId);

        if (!$usuario) {
            throw new RuntimeException('Usuario nao encontrado.');
        }

        $old = $_SESSION['old'] ?? [];
        $errors = $_SESSION['errors'] ?? [];
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;

        unset($_SESSION['old'], $_SESSION['errors'], $_SESSION['flash_success'], $_SESSION['flash_error']);

        $fotoAtual = PerfilViewHelper::fotoUrl($old, $usuario);
        $iniciaisUsuario = PerfilViewHelper::iniciais((string) ($old['nome'] ?? $usuario['nome'] ?? ''));

        return [
            'usuario' => $usuario,
            'old' => $old,
            'errors' => $errors,
            'flash_success' => $flashSuccess,
            'flash_error' => $flashError,
            'foto_atual' => $fotoAtual,
            'iniciais_usuario' => $iniciaisUsuario,
        ];
    }
}
