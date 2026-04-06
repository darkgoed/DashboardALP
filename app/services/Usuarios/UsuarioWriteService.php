<?php

class UsuarioWriteService
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function create(int $empresaId, array $data): string
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $telefone = trim((string) ($data['telefone'] ?? ''));
        $senha = (string) ($data['senha'] ?? '');
        $perfil = trim((string) ($data['perfil'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'ativo'));

        $perfisPermitidos = ['admin', 'gestor', 'analista', 'financeiro', 'visualizador'];
        $statusPermitidos = ['ativo', 'pendente', 'bloqueado', 'inativo'];

        if ($nome === '' || $email === '' || $senha === '') {
            throw new RuntimeException('Preencha os campos obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-mail inválido.');
        }

        if (!in_array($perfil, $perfisPermitidos, true)) {
            throw new RuntimeException('Perfil inválido.');
        }

        if (!in_array($status, $statusPermitidos, true)) {
            throw new RuntimeException('Status inválido.');
        }

        $limiteService = new EmpresaLimiteService($this->conn);
        $limiteService->validarNovoUsuario($empresaId);

        $this->conn->beginTransaction();

        try {
            $emailNormalizado = mb_strtolower($email);

            $stmt = $this->conn->prepare("
                SELECT id
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $emailNormalizado]);
            $usuarioExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuarioExistente) {
                $novoUsuarioId = (int) $usuarioExistente['id'];

                $stmt = $this->conn->prepare("
                    SELECT id
                    FROM usuarios_empresas
                    WHERE usuario_id = :usuario_id
                      AND empresa_id = :empresa_id
                    LIMIT 1
                ");
                $stmt->execute([
                    ':usuario_id' => $novoUsuarioId,
                    ':empresa_id' => $empresaId,
                ]);

                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new RuntimeException('Este usuário já está vinculado à empresa atual.');
                }
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO usuarios (
                        uuid, nome, email, telefone, senha_hash, status
                    ) VALUES (
                        :uuid, :nome, :email, :telefone, :senha_hash, :status
                    )
                ");

                $stmt->execute([
                    ':uuid' => uuidv4(),
                    ':nome' => $nome,
                    ':email' => $emailNormalizado,
                    ':telefone' => $telefone !== '' ? $telefone : null,
                    ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    ':status' => $status,
                ]);

                $novoUsuarioId = (int) $this->conn->lastInsertId();
            }

            $stmt = $this->conn->prepare("
                INSERT INTO usuarios_empresas (
                    usuario_id, empresa_id, perfil, status, is_principal
                ) VALUES (
                    :usuario_id, :empresa_id, :perfil, 'ativo', 0
                )
            ");

            $stmt->execute([
                ':usuario_id' => $novoUsuarioId,
                ':empresa_id' => $empresaId,
                ':perfil' => $perfil,
            ]);

            $this->conn->commit();

            return $usuarioExistente
                ? 'Usuário vinculado à empresa com sucesso.'
                : 'Usuário criado com sucesso.';
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function update(int $empresaId, array $data): string
    {
        $id = (int) ($data['id'] ?? 0);
        $nome = trim((string) ($data['nome'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $telefone = trim((string) ($data['telefone'] ?? ''));
        $senha = (string) ($data['senha'] ?? '');
        $perfil = trim((string) ($data['perfil'] ?? ''));
        $status = trim((string) ($data['status'] ?? ''));

        $perfisPermitidos = ['owner', 'admin', 'gestor', 'analista', 'financeiro', 'visualizador'];
        $statusPermitidos = ['ativo', 'pendente', 'bloqueado', 'inativo'];

        if ($id <= 0 || $nome === '' || $email === '') {
            throw new RuntimeException('Dados inválidos.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-mail inválido.');
        }

        if (!in_array($perfil, $perfisPermitidos, true)) {
            throw new RuntimeException('Perfil inválido.');
        }

        if (!in_array($status, $statusPermitidos, true)) {
            throw new RuntimeException('Status inválido.');
        }

        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare("
                SELECT
                    u.id,
                    u.email,
                    ue.perfil,
                    ue.is_principal
                FROM usuarios u
                INNER JOIN usuarios_empresas ue
                    ON ue.usuario_id = u.id
                   AND ue.empresa_id = :empresa_id
                WHERE u.id = :id
                LIMIT 1
            ");
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':id' => $id,
            ]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                throw new RuntimeException('Usuário não encontrado.');
            }

            $stmt = $this->conn->prepare("
                SELECT id
                FROM usuarios
                WHERE email = :email
                  AND id <> :id
                LIMIT 1
            ");
            $stmt->execute([
                ':email' => $email,
                ':id' => $id,
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Já existe outro usuário com este e-mail.');
            }

            if ((int) $usuario['is_principal'] === 1 && $perfil !== $usuario['perfil']) {
                throw new RuntimeException('Não é permitido alterar o perfil do usuário principal.');
            }

            $sql = "
                UPDATE usuarios
                SET
                    nome = :nome,
                    email = :email,
                    telefone = :telefone,
                    status = :status
            ";

            $params = [
                ':nome' => $nome,
                ':email' => $email,
                ':telefone' => $telefone !== '' ? $telefone : null,
                ':status' => $status,
                ':id' => $id,
            ];

            if ($senha !== '') {
                $sql .= ", senha_hash = :senha_hash";
                $params[':senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            $stmt = $this->conn->prepare("
                UPDATE usuarios_empresas
                SET
                    perfil = :perfil,
                    atualizado_em = NOW()
                WHERE usuario_id = :id
                  AND empresa_id = :empresa_id
            ");
            $stmt->execute([
                ':perfil' => $perfil,
                ':id' => $id,
                ':empresa_id' => $empresaId,
            ]);

            $this->conn->commit();

            return 'Usuário atualizado com sucesso.';
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function delete(int $empresaId, int $usuarioIdLogado, int $id): string
    {
        if ($id <= 0) {
            throw new RuntimeException('Usuário inválido.');
        }

        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare("
                SELECT
                    u.id,
                    ue.is_principal
                FROM usuarios u
                INNER JOIN usuarios_empresas ue
                    ON ue.usuario_id = u.id
                   AND ue.empresa_id = :empresa_id
                WHERE u.id = :id
                LIMIT 1
            ");
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':id' => $id,
            ]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                throw new RuntimeException('Usuário não encontrado.');
            }

            if ((int) $usuario['id'] === $usuarioIdLogado) {
                throw new RuntimeException('Você não pode excluir seu próprio usuário.');
            }

            if ((int) $usuario['is_principal'] === 1) {
                throw new RuntimeException('Não é permitido excluir o usuário principal.');
            }

            $stmt = $this->conn->prepare("
                DELETE FROM usuarios_empresas
                WHERE usuario_id = :id
                  AND empresa_id = :empresa_id
            ");
            $stmt->execute([
                ':id' => $id,
                ':empresa_id' => $empresaId,
            ]);

            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM usuarios_empresas
                WHERE usuario_id = :id
            ");
            $stmt->execute([':id' => $id]);
            $restantes = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            if ($restantes === 0) {
                $stmt = $this->conn->prepare("DELETE FROM usuarios WHERE id = :id");
                $stmt->execute([':id' => $id]);
            }

            $this->conn->commit();

            return 'Usuário excluído com sucesso.';
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }
}
