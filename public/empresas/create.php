<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

if (!method_exists('Auth', 'isPlatformRoot') || !Auth::isPlatformRoot()) {
    header('Location: ' . routeUrl('dashboard'));
    exit;
}

$db = new Database();
$conn = $db->connect();
$pageData = (new EmpresaPageService($conn))->getCreateData();
$planos = $pageData['planos'];
$old = $pageData['old'];
$errors = $pageData['errors'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova empresa - Dashboard ALP</title>

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
                            <path d="M12 5v14"></path>
                            <path d="M5 12h14"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Administração</span>
                        <h1 class="page-title">Nova empresa</h1>
                        <p class="page-subtitle">
                            Cadastre uma nova empresa no SaaS, definindo plano, limites,
                            vigência da licença e parâmetros iniciais de operação.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('empresas')); ?>" class="btn btn-secondary">Voltar</a>
                </div>
            </section>

            <form action="<?= htmlspecialchars(routeUrl('empresas/store')); ?>" method="POST" class="form-stack">
                <?= Csrf::field() ?>
                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Dados da empresa</h3>
                            <p class="panel-subtitle">Informações cadastrais principais da conta cliente.</p>
                        </div>
                    </div>

                    <!-- LINHA 1 (3 colunas igual edit) -->
                    <div class="content-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                        <div class="field">
                            <label for="nome_fantasia">Nome fantasia *</label>
                            <input type="text" id="nome_fantasia" name="nome_fantasia"
                                value="<?= EmpresaPageHelper::oldValue($old, 'nome_fantasia'); ?>" required>
                            <?= EmpresaPageHelper::errorField($errors, 'nome_fantasia'); ?>
                        </div>

                        <div class="field">
                            <label for="razao_social">Razão Social *</label>
                            <input type="text" id="razao_social" name="razao_social"
                                value="<?= EmpresaPageHelper::oldValue($old, 'razao_social'); ?>" required>
                            <?= EmpresaPageHelper::errorField($errors, 'razao_social'); ?>
                        </div>

                        <div class="field">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email" value="<?= EmpresaPageHelper::oldValue($old, 'email'); ?>" required>
                            <?= EmpresaPageHelper::errorField($errors, 'email'); ?>
                        </div>
                    </div>

                    <!-- LINHA 2 (4 colunas igual edit) -->
                    <div class="content-grid"
                        style="grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 16px;">

                        <div class="field">
                            <label for="documento">Documento</label>
                            <input type="text" id="documento" name="documento" value="<?= EmpresaPageHelper::oldValue($old, 'documento'); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'documento'); ?>
                        </div>

                        <div class="field">
                            <label for="responsavel_nome">Nome do Responsável *</label>
                            <input type="text" id="responsavel_nome" name="responsavel_nome"
                                value="<?= EmpresaPageHelper::oldValue($old, 'responsavel_nome'); ?>" required>
                            <?= EmpresaPageHelper::errorField($errors, 'responsavel_nome'); ?>
                        </div>

                        <div class="field">
                            <label for="responsavel_email">E-mail do responsável *</label>
                            <input type="email" id="responsavel_email" name="responsavel_email"
                                value="<?= EmpresaPageHelper::oldValue($old, 'responsavel_email'); ?>" required>
                            <?= EmpresaPageHelper::errorField($errors, 'responsavel_email'); ?>
                        </div>

                        <div class="field">
                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone" value="<?= EmpresaPageHelper::oldValue($old, 'telefone'); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'telefone'); ?>
                        </div>

                        <div class="field">
                            <label for="slug">Slug *</label>
                            <input type="text" id="slug" name="slug" value="<?= EmpresaPageHelper::oldValue($old, 'slug'); ?>" required>
                            <small>Use slug único para identificar a empresa.</small>
                            <?= EmpresaPageHelper::errorField($errors, 'slug'); ?>
                        </div>
                    </div>
                </section>

                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Plano e operação</h3>
                            <p class="panel-subtitle">Defina o plano, status inicial e comportamento operacional.</p>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
                        <div class="field field-select">
                            <label for="plano">Plano textual</label>
                            <select id="plano" name="plano">
                                <option value="trial" <?= EmpresaPageHelper::isSelected($old, 'plano', 'trial', 'trial'); ?>>Trial</option>
                                <option value="basic" <?= EmpresaPageHelper::isSelected($old, 'plano', 'basic'); ?>>Basic</option>
                                <option value="pro" <?= EmpresaPageHelper::isSelected($old, 'plano', 'pro'); ?>>Pro</option>
                                <option value="enterprise" <?= EmpresaPageHelper::isSelected($old, 'plano', 'enterprise'); ?>>Enterprise</option>
                            </select>
                            <?= EmpresaPageHelper::errorField($errors, 'plano'); ?>
                        </div>

                        <div class="field field-select">
                            <label for="plano_id">Plano estruturado</label>
                            <select id="plano_id" name="plano_id">
                                <option value="">Selecione</option>
                                <?php foreach ($planos as $plano): ?>
                                    <option value="<?= (int) $plano['id']; ?>" <?= EmpresaPageHelper::isSelected($old, 'plano_id', (string) $plano['id']); ?>>
                                        <?= htmlspecialchars($plano['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= EmpresaPageHelper::errorField($errors, 'plano_id'); ?>
                        </div>

                        <div class="field field-select">
                            <label for="status">Status cadastral</label>
                            <select id="status" name="status">
                                <option value="ativa" <?= EmpresaPageHelper::isSelected($old, 'status', 'ativa', 'ativa'); ?>>Ativa</option>
                                <option value="inativa" <?= EmpresaPageHelper::isSelected($old, 'status', 'inativa'); ?>>Inativa</option>
                                <option value="suspensa" <?= EmpresaPageHelper::isSelected($old, 'status', 'suspensa'); ?>>Suspensa</option>
                                <option value="cancelada" <?= EmpresaPageHelper::isSelected($old, 'status', 'cancelada'); ?>>Cancelada</option>
                            </select>
                            <?= EmpresaPageHelper::errorField($errors, 'status'); ?>
                        </div>

                        <div class="field field-select">
                            <label for="tipo_cobranca">Tipo de cobrança</label>
                            <select id="tipo_cobranca" name="tipo_cobranca">
                                <option value="trial" <?= EmpresaPageHelper::isSelected($old, 'tipo_cobranca', 'trial', 'trial'); ?>>Trial
                                </option>
                                <option value="mensal" <?= EmpresaPageHelper::isSelected($old, 'tipo_cobranca', 'mensal'); ?>>Mensal</option>
                                <option value="trimestral" <?= EmpresaPageHelper::isSelected($old, 'tipo_cobranca', 'trimestral'); ?>>Trimestral
                                </option>
                                <option value="semestral" <?= EmpresaPageHelper::isSelected($old, 'tipo_cobranca', 'semestral'); ?>>Semestral
                                </option>
                                <option value="anual" <?= EmpresaPageHelper::isSelected($old, 'tipo_cobranca', 'anual'); ?>>Anual</option>
                                <option value="personalizado" <?= EmpresaPageHelper::isSelected($old, 'tipo_cobranca', 'personalizado'); ?>>
                                    Personalizado</option>
                            </select>
                            <?= EmpresaPageHelper::errorField($errors, 'tipo_cobranca'); ?>
                        </div>
                    </div>

                    <div class="content-grid"
                        style="grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 16px;">
                        <div class="field">
                            <label for="is_root">Empresa root</label>
                            <div class="switch-row">
                                <input type="checkbox" id="is_root" name="is_root" value="1" <?= !empty($old['is_root']) ? 'checked' : ''; ?>>
                                <span>Marcar como empresa principal da plataforma</span>
                            </div>
                            <?= EmpresaPageHelper::errorField($errors, 'is_root'); ?>
                        </div>

                        <div class="field">
                            <label for="bloqueio_manual">Bloqueio manual inicial</label>
                            <div class="switch-row">
                                <input type="checkbox" id="bloqueio_manual" name="bloqueio_manual" value="1"
                                    <?= !empty($old['bloqueio_manual']) ? 'checked' : ''; ?>>
                                <span>Criar já bloqueada manualmente</span>
                            </div>
                            <?= EmpresaPageHelper::errorField($errors, 'bloqueio_manual'); ?>
                        </div>

                        <div class="field">
                            <label for="bloqueio_manual_motivo">Motivo do bloqueio manual</label>
                            <input type="text" id="bloqueio_manual_motivo" name="bloqueio_manual_motivo"
                                value="<?= EmpresaPageHelper::oldValue($old, 'bloqueio_manual_motivo'); ?>">
                            <?= EmpresaPageHelper::errorField($errors, 'bloqueio_manual_motivo'); ?>
                        </div>
                    </div>
                </section>

                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Limites do SaaS</h3>
                            <p class="panel-subtitle">Defina os limites operacionais iniciais da empresa.</p>
                        </div>
                    </div>

                    <div class="content-grid" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
                        <div class="field">
                            <label for="limite_usuarios">Limite de usuários *</label>
                            <input type="number" min="1" id="limite_usuarios" name="limite_usuarios"
                                value="<?= oldValue('limite_usuarios', '1'); ?>" required>
                            <?= errorField('limite_usuarios'); ?>
                        </div>

                        <div class="field">
                            <label for="limite_contas_ads">Limite de contas ads *</label>
                            <input type="number" min="1" id="limite_contas_ads" name="limite_contas_ads"
                                value="<?= oldValue('limite_contas_ads', '1'); ?>" required>
                            <?= errorField('limite_contas_ads'); ?>
                        </div>

                        <div class="field">
                            <label for="valor_cobrado">Valor cobrado</label>
                            <input type="text" id="valor_cobrado" name="valor_cobrado"
                                value="<?= oldValue('valor_cobrado'); ?>">
                            <?= errorField('valor_cobrado'); ?>
                        </div>

                        <div class="field field-select">
                            <label for="status_assinatura">Status da assinatura</label>
                            <select id="status_assinatura" name="status_assinatura">
                                <option value="trial" <?= isSelected('status_assinatura', 'trial', 'trial'); ?>>Trial
                                </option>
                                <option value="ativa" <?= isSelected('status_assinatura', 'ativa'); ?>>Ativa</option>
                                <option value="vencida" <?= isSelected('status_assinatura', 'vencida'); ?>>Vencida
                                </option>
                                <option value="em_tolerancia" <?= isSelected('status_assinatura', 'em_tolerancia'); ?>>Em
                                    tolerância</option>
                                <option value="bloqueada" <?= isSelected('status_assinatura', 'bloqueada'); ?>>Bloqueada
                                </option>
                                <option value="cancelada" <?= isSelected('status_assinatura', 'cancelada'); ?>>Cancelada
                                </option>
                            </select>
                            <?= errorField('status_assinatura'); ?>
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
                            <input type="date" id="data_inicio" name="data_inicio"
                                value="<?= oldValue('data_inicio'); ?>" required>
                            <?= errorField('data_inicio'); ?>
                        </div>

                        <div class="field">
                            <label for="data_vencimento">Data de vencimento</label>
                            <input type="date" id="data_vencimento" name="data_vencimento"
                                value="<?= oldValue('data_vencimento'); ?>">
                            <?= errorField('data_vencimento'); ?>
                        </div>

                        <div class="field">
                            <label for="dias_tolerancia">Dias de tolerância</label>
                            <input type="number" min="0" id="dias_tolerancia" name="dias_tolerancia"
                                value="<?= oldValue('dias_tolerancia', '0'); ?>">
                            <?= errorField('dias_tolerancia'); ?>
                        </div>

                        <div class="field">
                            <label for="trial_ate">Trial até</label>
                            <input type="date" id="trial_ate" name="trial_ate" value="<?= oldValue('trial_ate'); ?>">
                            <?= errorField('trial_ate'); ?>
                        </div>
                    </div>

                    <div class="content-grid"
                        style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 16px;">
                        <div class="field">
                            <label for="assinatura_ate">Assinatura até</label>
                            <input type="date" id="assinatura_ate" name="assinatura_ate"
                                value="<?= oldValue('assinatura_ate'); ?>">
                            <?= errorField('assinatura_ate'); ?>
                        </div>

                        <div class="field">
                            <label for="data_bloqueio">Data de bloqueio</label>
                            <input type="date" id="data_bloqueio" name="data_bloqueio"
                                value="<?= oldValue('data_bloqueio'); ?>">
                            <?= errorField('data_bloqueio'); ?>
                        </div>
                    </div>
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
                                name="observacoes_internas"><?= oldValue('observacoes_internas'); ?></textarea>
                            <?= errorField('observacoes_internas'); ?>
                        </div>

                        <div class="field">
                            <label for="observacoes_empresa">Observações da empresa</label>
                            <textarea id="observacoes_empresa"
                                name="observacoes_empresa"><?= oldValue('observacoes_empresa'); ?></textarea>
                            <small>Use se você tiver campo próprio em `empresas`; caso não tenha, pode ignorar no
                                store.</small>
                            <?= errorField('observacoes_empresa'); ?>
                        </div>
                    </div>

                    <div class="form-actions" style="justify-content: flex-end; margin-top: 18px;">
                        <a href="<?= htmlspecialchars(routeUrl('empresas')); ?>" class="btn btn-ghost">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Salvar empresa</button>
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

    <script src="../assets/js/bootstrap.js"></script>

</body>

</html>
