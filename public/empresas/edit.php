<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if (!method_exists('Auth', 'isPlatformRoot') || !Auth::isPlatformRoot()) {
    header('Location: ' . routeUrl('dashboard'));
    exit;
}

$db = new Database();
$conn = $db->connect();
$pageService = new EmpresaPageService($conn);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
try {
    $pageData = $pageService->getEditData($id);
    $empresa = $pageData['empresa'];
    $assinatura = $pageData['assinatura'];
    $consumo = $pageData['consumo'];
    $old = $pageData['old'];
    $errors = $pageData['errors'];
    $flashSuccess = $pageData['flash_success'];
    $flashError = $pageData['flash_error'];
    $flashConviteAdmin = $pageData['flash_convite_admin'];
    $convitePendente = $pageData['convite_pendente'];
    $empresaJaPossuiUsuario = $pageData['empresa_ja_possui_usuario'];
    $usuariosUsados = $pageData['usuarios_usados'];
    $usuariosLimiteReal = $pageData['usuarios_limite_real'];
    $usuariosPercentual = $pageData['usuarios_percentual'];
    $contasUsadas = $pageData['contas_usadas'];
    $contasLimiteReal = $pageData['contas_limite_real'];
    $contasPercentual = $pageData['contas_percentual'];
} catch (Throwable $e) {
    Flash::error($e->getMessage());
    header('Location: ' . routeUrl('empresas'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar empresa - Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/global.css">
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body class="page page-insights">
    <div class="app">
        <?php require_once __DIR__ . '/../partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Administração</span>
                        <h1 class="page-title">Editar empresa</h1>
                        <p class="page-subtitle">
                            Atualize cadastro, assinatura, limites e parâmetros operacionais
                            da empresa no ambiente SaaS.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('empresas')); ?>" class="btn btn-secondary">Voltar</a>
                </div>
            </section>

            <?php if ($flashSuccess): ?>
                <div class="panel" style="margin-bottom: 18px;">
                    <div class="badge badge-green" style="margin-bottom: 10px;">Sucesso</div>
                    <p style="margin:0;"><?= htmlspecialchars($flashSuccess); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="panel" style="margin-bottom: 18px;">
                    <div class="badge badge-red" style="margin-bottom: 10px;">Erro</div>
                    <p style="margin:0;"><?= htmlspecialchars($flashError); ?></p>
                </div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="metric-card">
                    <span>Empresa</span>
                    <strong><?= htmlspecialchars($empresa['nome_fantasia'] ?? '—'); ?></strong>
                    <small>ID #<?= (int) $empresa['id']; ?> · Slug
                        <?= htmlspecialchars($empresa['slug'] ?? '—'); ?></small>
                </div>

                <div class="metric-card">
                    <span>Usuários</span>
                    <strong><?= $usuariosUsados; ?>/<?= $usuariosLimiteReal; ?></strong>
                    <small><?= (int) ($consumo['usuarios']['disponivel'] ?? 0); ?> disponível(is)</small>
                    <div
                        style="width:100%; height:8px; border-radius:999px; overflow:hidden; background:var(--border-color, rgba(255,255,255,.08)); margin-top:10px;">
                        <div
                            style="width: <?= $usuariosPercentual; ?>%; height:100%; border-radius:999px; background: var(--primary);">
                        </div>
                    </div>
                </div>

                <div class="metric-card metric-accent">
                    <span>Contas ads</span>
                    <strong><?= $contasUsadas; ?>/<?= $contasLimiteReal; ?></strong>
                    <small><?= (int) ($consumo['contas_ads']['disponivel'] ?? 0); ?> disponível(is)</small>
                    <div
                        style="width:100%; height:8px; border-radius:999px; overflow:hidden; background:var(--border-color, rgba(255,255,255,.08)); margin-top:10px;">
                        <div
                            style="width: <?= $contasPercentual; ?>%; height:100%; border-radius:999px; background: var(--primary);">
                        </div>
                    </div>
                </div>

                <div class="metric-card">
                    <span>Status</span>
                    <strong><?= htmlspecialchars($empresa['status'] ?? '—'); ?></strong>
                    <small><?= !empty($empresa['is_root']) ? 'Empresa principal da plataforma.' : 'Empresa cliente do SaaS.'; ?></small>
                </div>
            </section>

            <form action="<?= htmlspecialchars(routeUrl('empresas/update')); ?>" method="POST" class="form-stack" style="margin-top: 20px;">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $empresa['id']; ?>">

                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Dados da empresa</h3>
                            <p class="panel-subtitle">Informações cadastrais principais da conta cliente.</p>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                        <div class="field">
                            <label for="nome_fantasia">Nome fantasia *</label>
                            <input type="text" id="nome_fantasia" name="nome_fantasia"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'nome_fantasia'); ?>" required>
                            <?= EmpresaPageHelper::errorField($errors, 'nome_fantasia'); ?>
                        </div>

                        <div class="field">
                            <label for="razao_social">Razão Social *</label>
                            <input type="text" id="razao_social" name="razao_social"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'razao_social'); ?>" required>
                            <?= EmpresaPageHelper::errorField($errors, 'razao_social'); ?>
                        </div>

                        <div class="field">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'email'); ?>" required>
                            <?= EmpresaPageHelper::errorField($errors, 'email'); ?>
                        </div>
                    </div>

                    <div class="content-grid"
                        style="grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 16px;">
                        <div class="field">
                            <label for="documento">Documento</label>
                            <input type="text" id="documento" name="documento"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'documento'); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'documento'); ?>
                        </div>

                        <div class="field">
                            <label for="responsavel_nome">Nome do Responsável *</label>
                            <input type="text" id="responsavel_nome" name="responsavel_nome"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'responsavel_nome', $empresa['razao_social'] ?? ''); ?>"
                                required>
                            <?= EmpresaPageHelper::errorField($errors, 'responsavel_nome'); ?>
                        </div>

                        <div class="field">
                            <label for="responsavel_email">E-mail do responsável *</label>
                            <input type="email" id="responsavel_email" name="responsavel_email"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'responsavel_email', $empresa['email'] ?? ''); ?>"
                                required>
                            <?= EmpresaPageHelper::errorField($errors, 'responsavel_email'); ?>
                        </div>

                        <div class="field">
                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'telefone'); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'telefone'); ?>
                        </div>

                        <div class="field">
                            <label for="slug">Slug *</label>
                            <input type="text" id="slug" name="slug"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'slug'); ?>" required>
                            <small>Use slug único para identificar a empresa.</small>
                            <?= EmpresaPageHelper::errorField($errors, 'slug'); ?>
                        </div>
                    </div>

                    <div class="content-grid"
                        style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 16px;">
                        <div class="field">
                            <label>ID da empresa</label>
                            <input type="text" value="#<?= (int) $empresa['id']; ?>" disabled>
                        </div>

                        <div class="field">
                            <label>UUID</label>
                            <input type="text" value="<?= htmlspecialchars($empresa['uuid'] ?? '—'); ?>" disabled>
                        </div>
                    </div>
                </section>

                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Plano e operação</h3>
                            <p class="panel-subtitle">Defina plano, status e comportamento operacional.</p>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
                        <div class="field field-select">
                            <label for="plano">Plano textual</label>
                            <select id="plano" name="plano">
                                <option value="trial" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'plano', 'trial', 'trial'); ?>>Trial</option>
                                <option value="basic" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'plano', 'basic'); ?>>Basic</option>
                                <option value="pro" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'plano', 'pro'); ?>>Pro
                                </option>
                                <option value="enterprise" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'plano', 'enterprise'); ?>>Enterprise</option>
                            </select>
                            <?= EmpresaPageHelper::errorField($errors, 'plano'); ?>
                        </div>

                        <div class="field">
                            <label for="plano_id">Plano estruturado</label>
                            <input type="number" id="plano_id" name="plano_id"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'plano_id'); ?>">
                            <small>Use o ID do plano se a tabela `planos` estiver ativa.</small>
                            <?= EmpresaPageHelper::errorField($errors, 'plano_id'); ?>
                        </div>

                        <div class="field field-select">
                            <label for="status">Status cadastral</label>
                            <select id="status" name="status">
                                <option value="ativa" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status', 'ativa', 'ativa'); ?>>Ativa</option>
                                <option value="inativa" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status', 'inativa'); ?>>Inativa</option>
                                <option value="suspensa" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status', 'suspensa'); ?>>Suspensa</option>
                                <option value="cancelada" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status', 'cancelada'); ?>>Cancelada</option>
                            </select>
                            <?= EmpresaPageHelper::errorField($errors, 'status'); ?>
                        </div>

                        <div class="field field-select">
                            <label for="tipo_cobranca">Tipo de cobrança</label>
                            <select id="tipo_cobranca" name="tipo_cobranca">
                                <option value="trial" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'tipo_cobranca', 'trial', 'trial'); ?>>Trial</option>
                                <option value="mensal" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'tipo_cobranca', 'mensal'); ?>>Mensal</option>
                                <option value="trimestral" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'tipo_cobranca', 'trimestral'); ?>>Trimestral</option>
                                <option value="semestral" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'tipo_cobranca', 'semestral'); ?>>Semestral</option>
                                <option value="anual" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'tipo_cobranca', 'anual'); ?>>Anual</option>
                                <option value="personalizado" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'tipo_cobranca', 'personalizado'); ?>>Personalizado</option>
                            </select>
                            <?= EmpresaPageHelper::errorField($errors, 'tipo_cobranca'); ?>
                        </div>
                    </div>

                    <div class="content-grid"
                        style="grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 16px;">
                        <div class="field">
                            <label for="is_root">Empresa root</label>
                            <div class="switch-row">
                                <input type="checkbox" id="is_root" name="is_root" value="1" <?= EmpresaPageHelper::checkedEdit($old, $empresa, $assinatura, 'is_root'); ?>>
                                <span>Marcar como empresa principal da plataforma</span>
                            </div>
                            <?= EmpresaPageHelper::errorField($errors, 'is_root'); ?>
                        </div>

                        <div class="field">
                            <label for="bloqueio_manual">Bloqueio manual</label>
                            <div class="switch-row">
                                <input type="checkbox" id="bloqueio_manual" name="bloqueio_manual" value="1"
                                    <?= EmpresaPageHelper::checkedEdit($old, $empresa, $assinatura, 'bloqueio_manual'); ?>>
                                <span>Bloquear manualmente esta empresa</span>
                            </div>
                            <?= EmpresaPageHelper::errorField($errors, 'bloqueio_manual'); ?>
                        </div>

                        <div class="field">
                            <label for="bloqueio_manual_motivo">Motivo do bloqueio manual</label>
                            <input type="text" id="bloqueio_manual_motivo" name="bloqueio_manual_motivo"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'bloqueio_manual_motivo'); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'bloqueio_manual_motivo'); ?>
                        </div>
                    </div>
                </section>

                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Limites do SaaS</h3>
                            <p class="panel-subtitle">Atualize os limites operacionais atuais da empresa.</p>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
                        <div class="field">
                            <label for="limite_usuarios">Limite de usuários *</label>
                            <input type="number" min="1" id="limite_usuarios" name="limite_usuarios"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'limite_usuarios', '1'); ?>"
                                required>
                            <?= EmpresaPageHelper::errorField($errors, 'limite_usuarios'); ?>
                        </div>

                        <div class="field">
                            <label for="limite_contas_ads">Limite de contas ads *</label>
                            <input type="number" min="1" id="limite_contas_ads" name="limite_contas_ads"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'limite_contas_ads', '1'); ?>"
                                required>
                            <?= EmpresaPageHelper::errorField($errors, 'limite_contas_ads'); ?>
                        </div>

                        <div class="field">
                            <label for="valor_cobrado">Valor cobrado</label>
                            <input type="text" id="valor_cobrado" name="valor_cobrado"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'valor_cobrado'); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'valor_cobrado'); ?>
                        </div>

                        <div class="field field-select">
                            <label for="status_assinatura">Status da assinatura</label>
                            <select id="status_assinatura" name="status_assinatura">
                                <option value="trial" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status_assinatura', 'trial', 'trial'); ?>>Trial</option>
                                <option value="ativa" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status_assinatura', 'ativa'); ?>>Ativa</option>
                                <option value="vencida" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status_assinatura', 'vencida'); ?>>Vencida</option>
                                <option value="em_tolerancia" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status_assinatura', 'em_tolerancia'); ?>>Em tolerância</option>
                                <option value="bloqueada" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status_assinatura', 'bloqueada'); ?>>Bloqueada</option>
                                <option value="cancelada" <?= EmpresaPageHelper::selectedEdit($old, $empresa, $assinatura, 'status_assinatura', 'cancelada'); ?>>Cancelada</option>
                            </select>
                            <?= EmpresaPageHelper::errorField($errors, 'status_assinatura'); ?>
                        </div>
                    </div>
                </section>

                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Licença e vigência</h3>
                            <p class="panel-subtitle">Controle de início, vencimento, tolerância e trial.</p>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
                        <div class="field">
                            <label for="data_inicio">Data de início *</label>
                            <input type="datetime-local" id="data_inicio" name="data_inicio"
                                value="<?= htmlspecialchars($old['data_inicio'] ?? EmpresaPageHelper::formatDateTimeLocalValue($assinatura['data_inicio'] ?? null)); ?>"
                                required>
                            <?= EmpresaPageHelper::errorField($errors, 'data_inicio'); ?>
                        </div>

                        <div class="field">
                            <label for="data_vencimento">Data de vencimento</label>
                            <input type="datetime-local" id="data_vencimento" name="data_vencimento"
                                value="<?= htmlspecialchars($old['data_vencimento'] ?? EmpresaPageHelper::formatDateTimeLocalValue($assinatura['data_vencimento'] ?? null)); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'data_vencimento'); ?>
                        </div>

                        <div class="field">
                            <label for="dias_tolerancia">Dias de tolerância</label>
                            <input type="number" min="0" id="dias_tolerancia" name="dias_tolerancia"
                                value="<?= EmpresaPageHelper::valueFromSource($old, $empresa, $assinatura, 'dias_tolerancia', '0'); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'dias_tolerancia'); ?>
                        </div>

                        <div class="field">
                            <label for="trial_ate">Trial até</label>
                            <input type="datetime-local" id="trial_ate" name="trial_ate"
                                value="<?= htmlspecialchars($old['trial_ate'] ?? EmpresaPageHelper::formatDateTimeLocalValue($empresa['trial_ate'] ?? null)); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'trial_ate'); ?>
                        </div>
                    </div>

                    <div class="content-grid"
                        style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 16px;">
                        <div class="field">
                            <label for="assinatura_ate">Assinatura até</label>
                            <input type="datetime-local" id="assinatura_ate" name="assinatura_ate"
                                value="<?= htmlspecialchars($old['assinatura_ate'] ?? EmpresaPageHelper::formatDateTimeLocalValue($empresa['assinatura_ate'] ?? null)); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'assinatura_ate'); ?>
                        </div>

                        <div class="field">
                            <label for="data_bloqueio">Data de bloqueio</label>
                            <input type="datetime-local" id="data_bloqueio" name="data_bloqueio"
                                value="<?= htmlspecialchars($old['data_bloqueio'] ?? EmpresaPageHelper::formatDateTimeLocalValue($assinatura['data_bloqueio'] ?? null)); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'data_bloqueio'); ?>
                        </div>
                    </div>
                </section>

                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Convite do administrador</h3>
                            <p class="panel-subtitle">Envie este link para o responsável criar o usuário admin da
                                empresa.</p>
                        </div>
                    </div>

                    <?php
                    $conviteExibicao = $flashConviteAdmin;

                    if (!$conviteExibicao && $convitePendente) {
                        $conviteExibicao = [
                            'empresa_id' => $id,
                            'empresa_nome' => $empresa['nome_fantasia'] ?? '',
                            'nome' => $convitePendente['nome'] ?? '',
                            'email' => $convitePendente['email'] ?? '',
                            'link' => appUrl('aceitar-convite?token=' . rawurlencode((string) ($convitePendente['token'] ?? ''))),
                            'expires_at' => $convitePendente['expires_at'] ?? '',
                            'email_sent' => null,
                            'email_message' => '',
                        ];
                    }
                    ?>

                    <?php if ($conviteExibicao): ?>
                        <div class="content-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                            <div class="field">
                                <label>Responsável</label>
                                <input type="text"
                                    value="<?= htmlspecialchars((string) ($conviteExibicao['nome'] ?? '')); ?>" readonly>
                            </div>

                            <div class="field">
                                <label>E-mail</label>
                                <input type="text"
                                    value="<?= htmlspecialchars((string) ($conviteExibicao['email'] ?? '')); ?>" readonly>
                            </div>

                            <div class="field">
                                <label>Expira em</label>
                                <input type="text"
                                    value="<?= !empty($conviteExibicao['expires_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $conviteExibicao['expires_at']))) : ''; ?>"
                                    readonly>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="link_convite_admin">Link do convite</label>
                                <input type="text" id="link_convite_admin"
                                    value="<?= htmlspecialchars((string) ($conviteExibicao['link'] ?? '')); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 18px; display:flex; gap:12px; flex-wrap:wrap;">
                            <button type="button" class="btn btn-primary" onclick="copiarConviteAdmin()">
                                Copiar link
                            </button>

                            <?php if (array_key_exists('email_sent', $conviteExibicao)): ?>
                                <div class="panel" style="width:100%;">
                                    <div class="badge <?= !empty($conviteExibicao['email_sent']) ? 'badge-green' : 'badge-yellow'; ?>" style="margin-bottom: 10px;">
                                        <?= !empty($conviteExibicao['email_sent']) ? 'Envio automatico realizado' : 'Envio automatico pendente'; ?>
                                    </div>
                                    <p style="margin:0;">
                                        <?= htmlspecialchars((string) (($conviteExibicao['email_message'] ?? '') !== '' ? $conviteExibicao['email_message'] : 'Use o link abaixo se precisar compartilhar manualmente.')); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <button type="submit"
                                class="btn btn-secondary"
                                formaction="<?= htmlspecialchars(routeUrl('empresas/reenviar-convite')); ?>"
                                formmethod="POST"
                                <?= $empresaJaPossuiUsuario ? 'disabled aria-disabled="true"' : ''; ?>>
                                Gerar novo convite
                            </button>
                            <?php if ($empresaJaPossuiUsuario): ?>
                                <p style="margin:0;">A empresa já possui usuário criado. O reenvio de convite foi bloqueado.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 20px 0 8px;">
                            <p>
                                <?= $empresaJaPossuiUsuario
                                    ? 'A empresa já possui usuário criado. Não é possível gerar convite.'
                                    : 'Nenhum convite pendente encontrado para esta empresa.'; ?>
                            </p>
                        </div>

                        <div class="form-actions" style="margin-top: 12px;">
                            <button type="submit"
                                class="btn btn-primary"
                                formaction="<?= htmlspecialchars(routeUrl('empresas/reenviar-convite')); ?>"
                                formmethod="POST"
                                <?= $empresaJaPossuiUsuario ? 'disabled aria-disabled="true"' : ''; ?>>
                                Gerar convite
                            </button>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Observações</h3>
                            <p class="panel-subtitle">Anotações internas administrativas da empresa e da assinatura.</p>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label for="observacoes_internas">Observações internas</label>
                            <textarea id="observacoes_internas"
                                name="observacoes_internas"><?= htmlspecialchars($old['observacoes_internas'] ?? ($assinatura['observacoes_internas'] ?? '')); ?></textarea>
                            <?= EmpresaPageHelper::errorField($errors, 'observacoes_internas'); ?>
                        </div>

                        <div class="field">
                            <label for="observacoes_empresa">Observações da empresa</label>
                            <textarea id="observacoes_empresa"
                                name="observacoes_empresa"><?= htmlspecialchars($old['observacoes_empresa'] ?? ''); ?></textarea>
                            <small>Opcional. Use apenas se sua tabela `empresas` tiver esse campo.</small>
                            <?= EmpresaPageHelper::errorField($errors, 'observacoes_empresa'); ?>
                        </div>
                    </div>

                    <div class="form-actions" style="justify-content: flex-end; margin-top: 18px;">
                        <a href="<?= htmlspecialchars(routeUrl('empresas')); ?>" class="btn btn-ghost">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </div>
                </section>
            </form>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        const nomeFantasiaInput = document.getElementById('nome_fantasia');
        const slugInput = document.getElementById('slug');

        function slugify(value) {
            return value
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
        }

        let slugTouchedManually = false;

        slugInput.addEventListener('input', function () {
            slugTouchedManually = this.value.trim() !== '';
        });

        nomeFantasiaInput.addEventListener('input', function () {
            if (!slugTouchedManually || slugInput.value.trim() === '') {
                slugInput.value = slugify(this.value);
            }
        });
    </script>


    <script>
        function copiarConviteAdmin() {
            const input = document.getElementById('link_convite_admin');

            if (!input) {
                alert('Link do convite não encontrado.');
                return;
            }

            input.removeAttribute('disabled');
            input.select();
            input.setSelectionRange(0, 99999);

            navigator.clipboard.writeText(input.value)
                .then(() => {
                    alert('Link copiado com sucesso.');
                })
                .catch(() => {
                    try {
                        document.execCommand('copy');
                        alert('Link copiado com sucesso.');
                    } catch (error) {
                        alert('Não foi possível copiar o link.');
                    }
                });
        }
    </script>

    <?php
    if (isset($_SESSION['flash_convite_admin']) && (int) ($_SESSION['flash_convite_admin']['empresa_id'] ?? 0) === $id) {
        unset($_SESSION['flash_convite_admin']);
    }
    ?>
    <script src="../assets/js/bootstrap.js"></script>
</body>

</html>
