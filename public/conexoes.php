<?php

require_once __DIR__ . '/../app/config/bootstrap.php';
require_once __DIR__ . '/../app/models/CanalEmail.php';

Auth::requireLogin();

$empresaId = (int) Auth::getEmpresaId();
$usuarioId = (int) Auth::getUsuarioId();

$db = new Database();
$conn = $db->connect();

$canalEmailModel = new CanalEmail($conn, $empresaId);
$emailConfig = $canalEmailModel->get();

$statusConexao = $emailConfig['status_conexao'] ?? 'inativo';
$ultimoTesteEm = $emailConfig['ultimo_teste_em'] ?? null;
$observacaoErro = $emailConfig['observacao_erro'] ?? null;

function badgeStatusEmail(string $status): array
{
    switch ($status) {
        case 'ativo':
            return ['class' => 'badge-green', 'label' => 'Conectado'];
        case 'erro':
            return ['class' => 'badge-red', 'label' => 'Com erro'];
        default:
            return ['class' => 'badge-muted', 'label' => 'Não testado'];
    }
}

$statusBadge = badgeStatusEmail($statusConexao);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexões - Dashboard ALP</title>

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="page-integracoes">

    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <div class="topbar">
                <div>
                    <h1>Conexões</h1>
                    <p>Gerencie os canais de envio automáticos da sua operação.</p>
                </div>

                <div class="topbar-right">
                    <small>Empresa ID: <?= (int) $empresaId; ?></small>
                </div>
            </div>

            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 7.5v9A2.25 2.25 0 0119.5 18.75h-15A2.25 2.25 0 012.25 16.5v-9m19.5 0A2.25 2.25 0 0019.5 5.25h-15A2.25 2.25 0 002.25 7.5m19.5 0v.243a2.25 2.25 0 01-1.07 1.92l-7.5 4.615a2.25 2.25 0 01-2.36 0l-7.5-4.615a2.25 2.25 0 01-1.07-1.92V7.5" />
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Envios automáticos</span>
                        <h2 class="page-title">Conecte seu email de envio</h2>
                        <p class="page-subtitle">
                            Configure o SMTP da empresa para envio de relatórios automáticos por email.
                            O WhatsApp pode permanecer como rascunho até a etapa da VPS.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <span class="badge <?= e($statusBadge['class']); ?>" id="badge-status-email">
                        <?= e($statusBadge['label']); ?>
                    </span>
                </div>
            </section>

            <div class="cards-grid cols-4">
                <div class="metric-card">
                    <span>Status atual</span>
                    <strong id="stat-status"><?= e($statusBadge['label']); ?></strong>
                    <small>Canal SMTP da empresa</small>
                </div>

                <div class="metric-card">
                    <span>Remetente</span>
                    <strong id="stat-remetente"><?= e($emailConfig['email_remetente'] ?? '—'); ?></strong>
                    <small>Email configurado para envio</small>
                </div>

                <div class="metric-card">
                    <span>Servidor SMTP</span>
                    <strong id="stat-host"><?= e($emailConfig['smtp_host'] ?? '—'); ?></strong>
                    <small>Host ativo na conexão</small>
                </div>

                <div class="metric-card">
                    <span>Último teste</span>
                    <strong id="stat-ultimo-teste"><?= e($ultimoTesteEm ?: 'Nunca testado'); ?></strong>
                    <small>Atualizado após envio de teste</small>
                </div>
            </div>

            <div class="content-grid">
                <section class="panel form-panel">
                    <div class="panel-header">
                        <div>
                            <h3>SMTP da empresa</h3>
                            <p class="panel-subtitle">
                                Salve a credencial que será usada nos relatórios automáticos.
                            </p>
                        </div>
                    </div>

                    <div id="alerta-email"></div>

                    <form id="form-email" class="form-stack" autocomplete="off">
                        <div class="field">
                            <label for="nome_remetente">Nome do remetente</label>
                            <input
                                type="text"
                                id="nome_remetente"
                                name="nome_remetente"
                                value="<?= e($emailConfig['nome_remetente'] ?? ''); ?>"
                                placeholder="Ex: Dashboard ALP"
                                required>
                        </div>

                        <div class="field">
                            <label for="email_remetente">Email remetente</label>
                            <input
                                type="email"
                                id="email_remetente"
                                name="email_remetente"
                                value="<?= e($emailConfig['email_remetente'] ?? ''); ?>"
                                placeholder="Ex: relatorios@suaempresa.com"
                                required>
                        </div>

                        <div class="field">
                            <label for="email_reply_to">Email reply-to</label>
                            <input
                                type="email"
                                id="email_reply_to"
                                name="email_reply_to"
                                value="<?= e($emailConfig['email_reply_to'] ?? ''); ?>"
                                placeholder="Ex: contato@suaempresa.com">
                            <div class="field-help">Opcional. Usado como email de resposta.</div>
                        </div>

                        <div class="row">
                            <div class="field" style="flex: 1;">
                                <label for="smtp_host">Host SMTP</label>
                                <input
                                    type="text"
                                    id="smtp_host"
                                    name="smtp_host"
                                    value="<?= e($emailConfig['smtp_host'] ?? ''); ?>"
                                    placeholder="Ex: smtp.gmail.com"
                                    required>
                            </div>

                            <div class="field" style="width: 120px;">
                                <label for="smtp_port">Porta</label>
                                <input
                                    type="number"
                                    id="smtp_port"
                                    name="smtp_port"
                                    value="<?= e($emailConfig['smtp_port'] ?? '587'); ?>"
                                    placeholder="587"
                                    required>
                            </div>
                        </div>

                        <div class="field field-select">
                            <label for="smtp_secure">Criptografia</label>
                            <select id="smtp_secure" name="smtp_secure" required>
                                <option value="tls" <?= (($emailConfig['smtp_secure'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?= (($emailConfig['smtp_secure'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?= (($emailConfig['smtp_secure'] ?? '') === 'none') ? 'selected' : ''; ?>>Sem criptografia</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="smtp_user">Usuário SMTP</label>
                            <input
                                type="text"
                                id="smtp_user"
                                name="smtp_user"
                                value="<?= e($emailConfig['smtp_user'] ?? ''); ?>"
                                placeholder="Ex: arthurmuller07@gmail.com"
                                required>
                        </div>

                        <div class="field">
                            <label for="smtp_pass">Senha SMTP</label>
                            <input
                                type="password"
                                id="smtp_pass"
                                name="smtp_pass"
                                value=""
                                placeholder="<?= $emailConfig ? 'Deixe em branco para manter a atual' : 'Digite a senha SMTP'; ?>">
                            <div class="field-help">
                                <?= $emailConfig ? 'Se deixar em branco, a senha atual será mantida.' : 'Use a senha SMTP ou senha de app do provedor.'; ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="btn-salvar-email">
                                Salvar conexão
                            </button>
                        </div>
                    </form>
                </section>

                <section class="stack">
                    <section class="panel info-panel">
                        <div class="panel-header">
                            <div>
                                <h3>Teste de envio</h3>
                                <p class="panel-subtitle">
                                    Envie um email de teste para validar autenticação, host, porta e criptografia.
                                </p>
                            </div>
                        </div>

                        <form id="form-teste-email" class="form-stack">
                            <div class="field">
                                <label for="email_teste">Email de destino</label>
                                <input
                                    type="email"
                                    id="email_teste"
                                    name="email_teste"
                                    placeholder="Ex: voce@gmail.com"
                                    required>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-success" id="btn-testar-email">
                                    Enviar email de teste
                                </button>
                            </div>
                        </form>
                    </section>

                    <section class="panel info-panel">
                        <div class="panel-header">
                            <div>
                                <h3>Status e observações</h3>
                                <p class="panel-subtitle">
                                    Resumo operacional da conexão atual.
                                </p>
                            </div>
                        </div>

                        <div class="inline-stats">
                            <div class="inline-stat">
                                <span>Status</span>
                                <strong id="info-status"><?= e($statusBadge['label']); ?></strong>
                            </div>

                            <div class="inline-stat">
                                <span>Último teste</span>
                                <strong id="info-ultimo-teste"><?= e($ultimoTesteEm ?: 'Nunca'); ?></strong>
                            </div>
                        </div>

                        <div style="height: 12px;"></div>

                        <?php if ($statusConexao === 'erro' && !empty($observacaoErro)): ?>
                            <div class="callout callout-danger" id="bloco-erro-email">
                                <strong>Último erro:</strong><br>
                                <span id="texto-erro-email"><?= nl2br(e($observacaoErro)); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="callout callout-info" id="bloco-erro-email">
                                <strong>Observação:</strong><br>
                                <span id="texto-erro-email">
                                    Depois de salvar a conexão, faça um envio de teste antes de ativar os relatórios automáticos.
                                </span>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="panel info-panel">
                        <div class="panel-header">
                            <div>
                                <h3>WhatsApp</h3>
                                <p class="panel-subtitle">
                                    Estrutura reservada para a próxima etapa.
                                </p>
                            </div>
                        </div>

                        <div class="callout callout-warning">
                            <strong>Rascunho aguardando VPS.</strong><br>
                            A conexão via QR Code será implementada depois, com ambiente dedicado.
                        </div>
                    </section>
                </section>
            </div>
        </main>
    </div>

    <script>
        (function() {
            const alerta = document.getElementById('alerta-email');
            const formEmail = document.getElementById('form-email');
            const formTeste = document.getElementById('form-teste-email');

            const btnSalvar = document.getElementById('btn-salvar-email');
            const btnTestar = document.getElementById('btn-testar-email');

            const badgeStatus = document.getElementById('badge-status-email');
            const statStatus = document.getElementById('stat-status');
            const infoStatus = document.getElementById('info-status');
            const statRemetente = document.getElementById('stat-remetente');
            const statHost = document.getElementById('stat-host');
            const statUltimoTeste = document.getElementById('stat-ultimo-teste');
            const infoUltimoTeste = document.getElementById('info-ultimo-teste');
            const blocoErro = document.getElementById('bloco-erro-email');
            const textoErro = document.getElementById('texto-erro-email');

            function setAlert(type, message) {
                const classMap = {
                    success: 'callout callout-success',
                    error: 'callout callout-danger',
                    warning: 'callout callout-warning',
                    info: 'callout callout-info'
                };

                alerta.innerHTML = `
            <div class="${classMap[type] || classMap.info}" style="margin-bottom: 14px;">
                ${message}
            </div>
        `;
            }

            function limparAlert() {
                alerta.innerHTML = '';
            }

            function setStatus(status, label) {
                badgeStatus.className = 'badge ' + status;
                badgeStatus.textContent = label;
                statStatus.textContent = label;
                infoStatus.textContent = label;
            }

            function setErroBloco(type, html) {
                blocoErro.className = 'callout ' + type;
                textoErro.innerHTML = html;
            }

            formEmail.addEventListener('submit', async function(e) {
                e.preventDefault();
                limparAlert();

                btnSalvar.disabled = true;
                btnSalvar.textContent = 'Salvando...';

                try {
                    const formData = new FormData(formEmail);

                    const response = await fetch('ajax/salvar_email.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Falha ao salvar a configuração.');
                    }

                    setAlert('success', '<strong>Conexão salva.</strong><br>' + data.message);

                    statRemetente.textContent = document.getElementById('email_remetente').value || '—';
                    statHost.textContent = document.getElementById('smtp_host').value || '—';

                    setErroBloco(
                        'callout-info',
                        'Configuração salva com sucesso. Agora faça um envio de teste para validar a conexão SMTP.'
                    );
                } catch (error) {
                    setAlert('error', '<strong>Não foi possível salvar.</strong><br>' + error.message);
                } finally {
                    btnSalvar.disabled = false;
                    btnSalvar.textContent = 'Salvar conexão';
                }
            });

            formTeste.addEventListener('submit', async function(e) {
                e.preventDefault();
                limparAlert();

                btnTestar.disabled = true;
                btnTestar.textContent = 'Enviando...';

                try {
                    const formData = new FormData(formTeste);

                    const response = await fetch('ajax/testar_email.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Falha no teste de email.');
                    }

                    const agora = new Date();
                    const agoraTexto = agora.toLocaleString('pt-BR');

                    setAlert('success', '<strong>Email de teste enviado.</strong><br>' + data.message);
                    setStatus('badge-green', 'Conectado');

                    statUltimoTeste.textContent = agoraTexto;
                    infoUltimoTeste.textContent = agoraTexto;

                    setErroBloco(
                        'callout-success',
                        'Conexão validada com sucesso. O canal de email está pronto para ser usado nos relatórios automáticos.'
                    );
                } catch (error) {
                    setAlert('error', '<strong>Falha no teste.</strong><br>' + error.message);
                    setStatus('badge-red', 'Com erro');

                    setErroBloco(
                        'callout-danger',
                        error.message
                    );
                } finally {
                    btnTestar.disabled = false;
                    btnTestar.textContent = 'Enviar email de teste';
                }
            });
        })();
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>

    <script src="../assets/js/nav-config.js"></script>

</body>

</html>