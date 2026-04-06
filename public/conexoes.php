<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

$pageData = (new ConexoesPageService($conn, $empresaId))->getPageData();
$emailConfig = $pageData['email_config'];
$statusConexao = $pageData['status_conexao'];
$ultimoTesteEm = $pageData['ultimo_teste_em'];
$observacaoErro = $pageData['observacao_erro'];
$statusBadge = $pageData['status_badge'];
$whatsappConfig = $pageData['whatsapp_config'];
$whatsappStatusConexao = $pageData['whatsapp_status_conexao'];
$whatsappUltimoTesteEm = $pageData['whatsapp_ultimo_teste_em'];
$whatsappObservacaoErro = $pageData['whatsapp_observacao_erro'];
$whatsappStatusBadge = $pageData['whatsapp_status_badge'];
$whatsappQrProxyUrl = routeUrl('conexoes_whatsapp_qr');

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
    <style>
        .page-integracoes .page-hero-actions {
            align-items: flex-start;
        }

        .page-integracoes .connections-board {
            display: grid;
            grid-template-columns: minmax(320px, 1fr) minmax(320px, 1fr);
            gap: 20px;
            margin-top: 20px;
            align-items: start;
        }

        .page-integracoes .connection-panel {
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .page-integracoes .connection-panel.email {
            border-top: 3px solid rgba(59, 130, 246, 0.65);
        }

        .page-integracoes .connection-panel.whatsapp {
            border-top: 3px solid rgba(16, 185, 129, 0.65);
        }

        .page-integracoes .connection-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .page-integracoes .connection-head h3 {
            margin: 0;
            font-size: 20px;
        }

        .page-integracoes .connection-head p {
            margin: 6px 0 0;
            color: var(--text-muted);
            line-height: 1.55;
        }

        .page-integracoes .connection-kicker {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-soft);
            margin-bottom: 8px;
        }

        .page-integracoes .connection-sections {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .page-integracoes .connection-block {
            padding: 18px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.38);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .page-integracoes .connection-block h4 {
            margin: 0;
            font-size: 15px;
        }

        .page-integracoes .connection-block p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .page-integracoes .connection-block-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .page-integracoes .connection-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .page-integracoes .connection-stat {
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(2, 6, 23, 0.34);
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        .page-integracoes .connection-stat span {
            display: block;
            font-size: 12px;
            color: var(--text-soft);
            margin-bottom: 6px;
        }

        .page-integracoes .connection-stat strong {
            display: block;
            font-size: 15px;
            line-height: 1.35;
            word-break: break-word;
        }

        .page-integracoes .connection-actions-inline {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-integracoes textarea {
            width: 100%;
            min-height: 92px;
            resize: vertical;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            background: var(--surface-elevated);
            color: var(--text-color);
            padding: 12px 14px;
            font: inherit;
        }

        .page-integracoes code {
            font-size: 12px;
        }

        .page-integracoes .whatsapp-qr-wrap {
            display: none;
        }

        @media (max-width: 1180px) {
            .page-integracoes .connections-board {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .page-integracoes .connection-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body class="page-integracoes">

    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <div class="topbar">
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
                            O WhatsApp é conectado por QR dentro desta tela. A parte técnica do bridge fica automática no backend.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <span class="badge <?= e($statusBadge['class']); ?>" id="badge-status-email-hero">
                        <?= e($statusBadge['label']); ?>
                    </span>
                </div>
            </section>

            <div class="cards-grid cols-4">
                <div class="metric-card">
                    <span>Email</span>
                    <strong id="stat-status"><?= e($statusBadge['label']); ?></strong>
                    <small>Status atual do canal SMTP</small>
                </div>

                <div class="metric-card">
                    <span>Remetente</span>
                    <strong id="stat-remetente"><?= e($emailConfig['email_remetente'] ?? '—'); ?></strong>
                    <small>Email usado nos relatórios</small>
                </div>

                <div class="metric-card">
                    <span>WhatsApp</span>
                    <strong id="whatsapp-info-status-top"><?= e($whatsappStatusBadge['label']); ?></strong>
                    <small>Status atual da sessão</small>
                </div>

                <div class="metric-card">
                    <span>Sessão</span>
                    <strong><?= e($whatsappConfig['session_name'] ?? '—'); ?></strong>
                    <small>Identificador automático por empresa</small>
                </div>
            </div>

            <div class="connections-board">
                <section class="panel connection-panel email">
                    <div class="connection-head">
                        <div>
                            <span class="connection-kicker">Canal E-mail</span>
                            <h3>Configuração SMTP</h3>
                            <p>
                                Organize aqui a credencial de envio e o teste operacional do e-mail usado nos relatórios.
                            </p>
                        </div>
                        <span class="badge <?= e($statusBadge['class']); ?>" id="badge-status-email"><?= e($statusBadge['label']); ?></span>
                    </div>

                    <div class="connection-sections">
                        <div class="connection-block">
                            <div class="connection-block-head">
                                <div>
                                    <h4>Dados técnicos</h4>
                                    <p>Dados persistidos da conta SMTP da empresa.</p>
                                </div>
                            </div>
                            <div id="alerta-email"></div>
                            <form id="form-email" class="form-stack" autocomplete="off">
                                <?= Csrf::field() ?>
                                <div class="field">
                                    <label for="nome_remetente">Nome do remetente</label>
                                    <input type="text" id="nome_remetente" name="nome_remetente" value="<?= e($emailConfig['nome_remetente'] ?? ''); ?>" placeholder="Ex: Dashboard ALP" required>
                                </div>
                                <div class="field">
                                    <label for="email_remetente">Email remetente</label>
                                    <input type="email" id="email_remetente" name="email_remetente" value="<?= e($emailConfig['email_remetente'] ?? ''); ?>" placeholder="Ex: relatorios@suaempresa.com" required>
                                </div>
                                <div class="field">
                                    <label for="email_reply_to">Email reply-to</label>
                                    <input type="email" id="email_reply_to" name="email_reply_to" value="<?= e($emailConfig['email_reply_to'] ?? ''); ?>" placeholder="Ex: contato@suaempresa.com">
                                    <div class="field-help">Opcional. Usado como email de resposta.</div>
                                </div>
                                <div class="row">
                                    <div class="field" style="flex: 1;">
                                        <label for="smtp_host">Host SMTP</label>
                                        <input type="text" id="smtp_host" name="smtp_host" value="<?= e($emailConfig['smtp_host'] ?? ''); ?>" placeholder="Ex: smtp.gmail.com" required>
                                    </div>
                                    <div class="field" style="width: 120px;">
                                        <label for="smtp_port">Porta</label>
                                        <input type="number" id="smtp_port" name="smtp_port" value="<?= e($emailConfig['smtp_port'] ?? '587'); ?>" placeholder="587" required>
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
                                    <input type="text" id="smtp_user" name="smtp_user" value="<?= e($emailConfig['smtp_user'] ?? ''); ?>" placeholder="Ex: relatorios@empresa.com" required>
                                </div>
                                <div class="field">
                                    <label for="smtp_pass">Senha SMTP</label>
                                    <input type="password" id="smtp_pass" name="smtp_pass" value="" placeholder="<?= $emailConfig ? 'Deixe em branco para manter a atual' : 'Digite a senha SMTP'; ?>">
                                    <div class="field-help"><?= $emailConfig ? 'Se deixar em branco, a senha atual será mantida.' : 'Use a senha SMTP ou senha de app do provedor.'; ?></div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary" id="btn-salvar-email">Salvar conexão</button>
                                </div>
                            </form>
                        </div>

                        <div class="connection-block">
                            <div class="connection-block-head">
                                <div>
                                    <h4>Teste e status</h4>
                                    <p>Valide o envio e acompanhe o último resultado operacional.</p>
                                </div>
                            </div>
                            <form id="form-teste-email" class="form-stack">
                                <?= Csrf::field() ?>
                                <div class="field">
                                    <label for="email_teste">Email de destino</label>
                                    <input type="email" id="email_teste" name="email_teste" placeholder="Ex: voce@gmail.com" required>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-success" id="btn-testar-email">Enviar email de teste</button>
                                </div>
                            </form>
                            <div class="connection-stats">
                                <div class="connection-stat">
                                    <span>Status</span>
                                    <strong id="info-status"><?= e($statusBadge['label']); ?></strong>
                                </div>
                                <div class="connection-stat">
                                    <span>Último teste</span>
                                    <strong id="info-ultimo-teste"><?= e($ultimoTesteEm ?: 'Nunca'); ?></strong>
                                </div>
                            </div>
                            <?php if ($statusConexao === 'erro' && !empty($observacaoErro)): ?>
                                <div class="callout callout-danger" id="bloco-erro-email">
                                    <strong>Último erro:</strong><br>
                                    <span id="texto-erro-email"><?= nl2br(e($observacaoErro)); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="callout callout-info" id="bloco-erro-email">
                                    <strong>Observação:</strong><br>
                                    <span id="texto-erro-email">Depois de salvar a conexão, faça um envio de teste antes de ativar os relatórios automáticos.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="panel connection-panel whatsapp">
                    <div class="connection-head">
                        <div>
                            <span class="connection-kicker">Canal WhatsApp</span>
                            <h3>Sessão e testes</h3>
                            <p>Conecte o número do gestor por QR, acompanhe o estado da sessão e valide o envio de mensagens.</p>
                        </div>
                        <span class="badge <?= e($whatsappStatusBadge['class']); ?>" id="whatsapp-badge-side"><?= e($whatsappStatusBadge['label']); ?></span>
                    </div>

                    <div class="connection-sections">
                        <div class="connection-block">
                            <div class="connection-block-head">
                                <div>
                                    <h4>Configuração visível ao gestor</h4>
                                    <p>O gestor só controla o nome da conexão e o número de teste.</p>
                                </div>
                            </div>
                            <div id="alerta-whatsapp"></div>
                            <form id="form-whatsapp" class="form-stack" autocomplete="off">
                                <?= Csrf::field() ?>
                                <div class="field">
                                    <label for="nome_conexao">Nome da conexão</label>
                                    <input type="text" id="nome_conexao" name="nome_conexao" value="<?= e($whatsappConfig['nome_conexao'] ?? 'WhatsApp relatórios'); ?>" placeholder="Ex: WhatsApp relatórios" required>
                                </div>
                                <div class="field">
                                    <label for="numero_teste_padrao">Número padrão de teste</label>
                                    <input type="text" id="numero_teste_padrao" name="numero_teste_padrao" value="<?= e($whatsappConfig['numero_teste_padrao'] ?? ''); ?>" placeholder="5511999999999">
                                </div>
                                <div class="callout callout-info">
                                    <strong>Conexão técnica automática</strong><br>
                                    Sessão técnica: <code><?= e($whatsappConfig['session_name'] ?? ''); ?></code>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary" id="btn-salvar-whatsapp">Salvar conexão</button>
                                </div>
                            </form>
                        </div>

                        <div class="connection-block">
                            <div class="connection-block-head">
                                <div>
                                    <h4>Status da sessão</h4>
                                    <p>Conecte ou troque o número aqui. O QR só aparece quando a sessão não está pronta.</p>
                                </div>
                            </div>
                            <div class="connection-stats">
                                <div class="connection-stat">
                                    <span>Status</span>
                                    <strong id="whatsapp-info-status"><?= e($whatsappStatusBadge['label']); ?></strong>
                                </div>
                                <div class="connection-stat">
                                    <span>Último teste</span>
                                    <strong id="whatsapp-info-ultimo-teste"><?= e($whatsappUltimoTesteEm ?: 'Nunca'); ?></strong>
                                </div>
                            </div>
                            <div class="connection-actions-inline">
                                <button type="button" class="btn btn-primary" id="btn-iniciar-sessao-whatsapp">Conectar ou trocar número</button>
                                <button type="button" class="btn btn-secondary" id="btn-atualizar-sessao-whatsapp">Atualizar status</button>
                                <form id="form-teste-whatsapp" class="form-stack" style="margin:0;">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn-secondary" id="btn-testar-whatsapp">Testar bridge</button>
                                </form>
                            </div>
                            <div class="callout callout-info" id="bloco-sessao-whatsapp">
                                <strong>Sessão conectada</strong><br>
                                <span id="texto-sessao-whatsapp">Consulte o status atual da sessão e conecte ou troque o número quando necessário.</span>
                            </div>
                            <div id="whatsapp-qr-wrap" class="whatsapp-qr-wrap">
                                <div class="field-help" style="margin-bottom: 12px;">
                                    O QR só aparece quando a sessão não estiver conectada. Depois de escanear, esta área some automaticamente.
                                </div>
                                <iframe id="whatsapp-qr-frame" title="QR Code do WhatsApp" src="about:blank" style="width:100%;min-height:520px;border:1px solid rgba(148,163,184,.2);border-radius:16px;background:#0f172a;"></iframe>
                            </div>
                            <?php if ($whatsappStatusConexao === 'erro' && !empty($whatsappObservacaoErro)): ?>
                                <div class="callout callout-danger" id="bloco-erro-whatsapp">
                                    <strong>Último erro:</strong><br>
                                    <span id="texto-erro-whatsapp"><?= nl2br(e($whatsappObservacaoErro)); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="callout callout-info" id="bloco-erro-whatsapp">
                                    <strong>Observação:</strong><br>
                                    <span id="texto-erro-whatsapp">Depois de salvar a conexão, valide o bridge. O envio manual e programado de relatórios por WhatsApp depende desse serviço.</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="connection-block">
                            <div class="connection-block-head">
                                <div>
                                    <h4>Teste de envio</h4>
                                    <p>Envie uma mensagem curta para um número de teste antes de liberar o uso diário.</p>
                                </div>
                            </div>
                            <form id="form-teste-envio-whatsapp" class="form-stack">
                                <?= Csrf::field() ?>
                                <div class="field">
                                    <label for="destino_whatsapp_teste">Número para teste</label>
                                    <input type="text" id="destino_whatsapp_teste" name="destino_whatsapp_teste" value="<?= e($whatsappConfig['numero_teste_padrao'] ?? ''); ?>" placeholder="5511999999999" required>
                                </div>
                                <div class="field">
                                    <label for="mensagem_whatsapp_teste">Mensagem de teste</label>
                                    <textarea id="mensagem_whatsapp_teste" name="mensagem_whatsapp_teste" rows="3" placeholder="Teste de envio do canal WhatsApp do Dashboard ALP.">Teste de envio do canal WhatsApp do Dashboard ALP.</textarea>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-success" id="btn-testar-envio-whatsapp">Enviar WhatsApp de teste</button>
                                </div>
                            </form>
                        </div>
                    </div>
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
            const badgeStatusHero = document.getElementById('badge-status-email-hero');
            const statStatus = document.getElementById('stat-status');
            const infoStatus = document.getElementById('info-status');
            const statRemetente = document.getElementById('stat-remetente');
            const infoUltimoTeste = document.getElementById('info-ultimo-teste');
            const whatsappInfoStatusTop = document.getElementById('whatsapp-info-status-top');
            const blocoErro = document.getElementById('bloco-erro-email');
            const textoErro = document.getElementById('texto-erro-email');
            const alertaWhatsapp = document.getElementById('alerta-whatsapp');
            const formWhatsapp = document.getElementById('form-whatsapp');
            const formTesteWhatsapp = document.getElementById('form-teste-whatsapp');
            const formTesteEnvioWhatsapp = document.getElementById('form-teste-envio-whatsapp');
            const btnSalvarWhatsapp = document.getElementById('btn-salvar-whatsapp');
            const btnTestarWhatsapp = document.getElementById('btn-testar-whatsapp');
            const btnTestarEnvioWhatsapp = document.getElementById('btn-testar-envio-whatsapp');
            const whatsappInfoStatus = document.getElementById('whatsapp-info-status');
            const whatsappInfoUltimoTeste = document.getElementById('whatsapp-info-ultimo-teste');
            const blocoErroWhatsapp = document.getElementById('bloco-erro-whatsapp');
            const textoErroWhatsapp = document.getElementById('texto-erro-whatsapp');
            const blocoSessaoWhatsapp = document.getElementById('bloco-sessao-whatsapp');
            const textoSessaoWhatsapp = document.getElementById('texto-sessao-whatsapp');
            const btnIniciarSessaoWhatsapp = document.getElementById('btn-iniciar-sessao-whatsapp');
            const btnAtualizarSessaoWhatsapp = document.getElementById('btn-atualizar-sessao-whatsapp');
            const whatsappQrWrap = document.getElementById('whatsapp-qr-wrap');
            const whatsappQrFrame = document.getElementById('whatsapp-qr-frame');
            const whatsappQrProxyUrl = <?= json_encode($whatsappQrProxyUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            let whatsappSessionPoll = null;

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

            function setWhatsappAlert(type, message) {
                const classMap = {
                    success: 'callout callout-success',
                    error: 'callout callout-danger',
                    warning: 'callout callout-warning',
                    info: 'callout callout-info'
                };

                alertaWhatsapp.innerHTML = `
            <div class="${classMap[type] || classMap.info}" style="margin-bottom: 14px;">
                ${message}
            </div>
        `;
            }

            function setStatus(status, label) {
                badgeStatus.className = 'badge ' + status;
                badgeStatus.textContent = label;
                if (badgeStatusHero) {
                    badgeStatusHero.className = 'badge ' + status;
                    badgeStatusHero.textContent = label;
                }
                statStatus.textContent = label;
                infoStatus.textContent = label;
            }

            function setErroBloco(type, html) {
                blocoErro.className = 'callout ' + type;
                textoErro.innerHTML = html;
            }

            function setWhatsappErroBloco(type, html) {
                blocoErroWhatsapp.className = 'callout ' + type;
                textoErroWhatsapp.innerHTML = html;
            }

            function setWhatsappSessionBlock(type, html) {
                blocoSessaoWhatsapp.className = 'callout ' + type;
                textoSessaoWhatsapp.innerHTML = html;
            }

            function getWhatsappCsrfToken() {
                const input = document.querySelector('#form-whatsapp input[name="csrf_token"]');
                if (!input || !input.value) {
                    throw new Error('Token CSRF do WhatsApp nao encontrado na tela.');
                }

                return input.value;
            }

            function refreshWhatsappQrFrame() {
                whatsappQrFrame.src = whatsappQrProxyUrl + '?t=' + Date.now();
            }

            function stopWhatsappPolling() {
                if (whatsappSessionPoll) {
                    clearInterval(whatsappSessionPoll);
                    whatsappSessionPoll = null;
                }
            }

            function scheduleWhatsappPolling() {
                stopWhatsappPolling();
                whatsappSessionPoll = setInterval(function() {
                    atualizarSessaoWhatsapp(false);
                }, 5000);
            }

            function applyWhatsappSessionState(data) {
                const session = data && data.session ? data.session : null;
                const status = session && session.status ? String(session.status) : 'indefinido';
                const ready = status === 'ready';
                const qrReady = status === 'qr_ready';
                const nowText = new Date().toLocaleString('pt-BR');

                whatsappInfoUltimoTeste.textContent = nowText;

                if (ready) {
                    whatsappInfoStatus.textContent = 'Conectado';
                    if (whatsappInfoStatusTop) {
                        whatsappInfoStatusTop.textContent = 'Conectado';
                    }
                    whatsappQrWrap.style.display = 'none';
                    setWhatsappSessionBlock(
                        'callout callout-success',
                        'Número conectado com sucesso nesta sessão. Para trocar o número, clique em <strong>Conectar ou trocar número</strong>.'
                    );
                    stopWhatsappPolling();
                    return;
                }

                if (qrReady) {
                    whatsappInfoStatus.textContent = 'Aguardando QR';
                    if (whatsappInfoStatusTop) {
                        whatsappInfoStatusTop.textContent = 'Aguardando QR';
                    }
                    whatsappQrWrap.style.display = 'block';
                    refreshWhatsappQrFrame();
                    setWhatsappSessionBlock(
                        'callout callout-warning',
                        'Escaneie o QR abaixo com o WhatsApp do gestor. Esta área atualiza automaticamente até a sessão ficar conectada.'
                    );
                    scheduleWhatsappPolling();
                    return;
                }

                whatsappInfoStatus.textContent = status;
                if (whatsappInfoStatusTop) {
                    whatsappInfoStatusTop.textContent = status;
                }
                whatsappQrWrap.style.display = 'none';

                const errorMessage = session && session.lastError ? '<br>' + session.lastError : '';
                setWhatsappSessionBlock(
                    status === 'error' || status === 'auth_failure' || status === 'disconnected'
                        ? 'callout callout-danger'
                        : 'callout callout-info',
                    'Status atual da sessão: <strong>' + status + '</strong>.' + errorMessage
                );
            }

            async function parseJsonResponse(response) {
                const raw = await response.text();

                try {
                    return JSON.parse(raw);
                } catch (error) {
                    const resumo = raw
                        .replace(/<[^>]*>/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim()
                        .slice(0, 220);

                    throw new Error(resumo || 'Resposta invalida do servidor.');
                }
            }

            async function atualizarSessaoWhatsapp(showAlert = true) {
                const formData = new FormData();
                formData.append('csrf_token', getWhatsappCsrfToken());

                const response = await fetch('router.php?path=ajax/whatsapp_session_status', {
                    method: 'POST',
                    body: formData
                });

                const data = await parseJsonResponse(response);

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Falha ao consultar a sessão do WhatsApp.');
                }

                applyWhatsappSessionState(data);

                if (showAlert) {
                    setWhatsappAlert('info', '<strong>Status atualizado.</strong><br>Sessão do WhatsApp consultada com sucesso.');
                }
            }

            async function iniciarSessaoWhatsapp() {
                const formData = new FormData();
                formData.append('csrf_token', getWhatsappCsrfToken());

                const response = await fetch('router.php?path=ajax/whatsapp_session_start', {
                    method: 'POST',
                    body: formData
                });

                const data = await parseJsonResponse(response);

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Falha ao iniciar a sessão do WhatsApp.');
                }

                applyWhatsappSessionState(data);
                setWhatsappAlert('success', '<strong>Sessão iniciada.</strong><br>Se necessário, escaneie o QR abaixo para conectar ou trocar o número.');
            }

            formEmail.addEventListener('submit', async function(e) {
                e.preventDefault();
                limparAlert();

                btnSalvar.disabled = true;
                btnSalvar.textContent = 'Salvando...';

                try {
                    const formData = new FormData(formEmail);

                    const response = await fetch('router.php?path=ajax/salvar_email', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await parseJsonResponse(response);

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Falha ao salvar a configuração.');
                    }

                    setAlert('success', '<strong>Conexão salva.</strong><br>' + data.message);

                    statRemetente.textContent = document.getElementById('email_remetente').value || '—';

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

                    const response = await fetch('router.php?path=ajax/testar_email', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await parseJsonResponse(response);

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Falha no teste de email.');
                    }

                    const agora = new Date();
                    const agoraTexto = agora.toLocaleString('pt-BR');

                    setAlert('success', '<strong>Email de teste enviado.</strong><br>' + data.message);
                    setStatus('badge-green', 'Conectado');

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

            formWhatsapp.addEventListener('submit', async function(e) {
                e.preventDefault();
                alertaWhatsapp.innerHTML = '';

                btnSalvarWhatsapp.disabled = true;
                btnSalvarWhatsapp.textContent = 'Salvando...';

                try {
                    const formData = new FormData(formWhatsapp);

                    const response = await fetch('router.php?path=ajax/salvar_whatsapp', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await parseJsonResponse(response);

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Falha ao salvar a configuração do WhatsApp.');
                    }

                    setWhatsappAlert('success', '<strong>Conexão salva.</strong><br>' + data.message);
                    setWhatsappErroBloco(
                        'callout-info',
                        'Configuração salva com sucesso. Agora teste o bridge e conecte a sessão pelo QR quando necessário.'
                    );
                } catch (error) {
                    setWhatsappAlert('error', '<strong>Não foi possível salvar.</strong><br>' + error.message);
                } finally {
                    btnSalvarWhatsapp.disabled = false;
                    btnSalvarWhatsapp.textContent = 'Salvar conexão';
                }
            });

            formTesteWhatsapp.addEventListener('submit', async function(e) {
                e.preventDefault();
                alertaWhatsapp.innerHTML = '';

                btnTestarWhatsapp.disabled = true;
                btnTestarWhatsapp.textContent = 'Testando...';

                try {
                    const formData = new FormData(formTesteWhatsapp);

                    const response = await fetch('router.php?path=ajax/testar_whatsapp', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await parseJsonResponse(response);

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Falha no teste do bridge do WhatsApp.');
                    }

                    const agora = new Date().toLocaleString('pt-BR');
                    whatsappInfoStatus.textContent = 'Conectado';
                    whatsappInfoUltimoTeste.textContent = agora;

                    setWhatsappAlert('success', '<strong>Bridge validado.</strong><br>' + data.message);
                    setWhatsappErroBloco(
                        'callout-success',
                        'Bridge validado com sucesso. O canal de WhatsApp está pronto. Se a sessão não estiver conectada, use o botão para abrir o QR.'
                    );
                } catch (error) {
                    whatsappInfoStatus.textContent = 'Com erro';
                    setWhatsappAlert('error', '<strong>Falha no teste.</strong><br>' + error.message);
                    setWhatsappErroBloco('callout-danger', error.message);
                } finally {
                    btnTestarWhatsapp.disabled = false;
                    btnTestarWhatsapp.textContent = 'Testar bridge';
                }
            });

            formTesteEnvioWhatsapp.addEventListener('submit', async function(e) {
                e.preventDefault();
                alertaWhatsapp.innerHTML = '';

                btnTestarEnvioWhatsapp.disabled = true;
                btnTestarEnvioWhatsapp.textContent = 'Enviando...';

                try {
                    const formData = new FormData(formTesteEnvioWhatsapp);

                    const response = await fetch('router.php?path=ajax/testar_whatsapp_envio', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await parseJsonResponse(response);

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Falha ao enviar WhatsApp de teste.');
                    }

                    const agora = new Date().toLocaleString('pt-BR');
                    whatsappInfoUltimoTeste.textContent = agora;
                    setWhatsappAlert('success', '<strong>WhatsApp enviado.</strong><br>' + (data.message || 'Mensagem de teste enviada com sucesso.'));
                    setWhatsappSessionBlock(
                        'callout callout-success',
                        'Teste de envio concluido. O numero e a sessao atual responderam corretamente.'
                    );
                } catch (error) {
                    setWhatsappAlert('error', '<strong>Falha no envio de teste.</strong><br>' + error.message);
                } finally {
                    btnTestarEnvioWhatsapp.disabled = false;
                    btnTestarEnvioWhatsapp.textContent = 'Enviar WhatsApp de teste';
                }
            });

            btnAtualizarSessaoWhatsapp.addEventListener('click', async function() {
                alertaWhatsapp.innerHTML = '';
                btnAtualizarSessaoWhatsapp.disabled = true;
                btnAtualizarSessaoWhatsapp.textContent = 'Atualizando...';

                try {
                    await atualizarSessaoWhatsapp(true);
                } catch (error) {
                    setWhatsappAlert('error', '<strong>Falha ao consultar sessão.</strong><br>' + error.message);
                } finally {
                    btnAtualizarSessaoWhatsapp.disabled = false;
                    btnAtualizarSessaoWhatsapp.textContent = 'Atualizar status';
                }
            });

            btnIniciarSessaoWhatsapp.addEventListener('click', async function() {
                alertaWhatsapp.innerHTML = '';
                btnIniciarSessaoWhatsapp.disabled = true;
                btnIniciarSessaoWhatsapp.textContent = 'Iniciando...';

                try {
                    await iniciarSessaoWhatsapp();
                } catch (error) {
                    setWhatsappAlert('error', '<strong>Falha ao iniciar sessão.</strong><br>' + error.message);
                } finally {
                    btnIniciarSessaoWhatsapp.disabled = false;
                    btnIniciarSessaoWhatsapp.textContent = 'Conectar ou trocar número';
                }
            });

            atualizarSessaoWhatsapp(false).catch(function() {
                setWhatsappSessionBlock(
                    'callout callout-info',
                    'Salve e teste o canal WhatsApp. Depois disso, a sessão poderá ser conectada por QR aqui nesta tela.'
                );
            });
        })();
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>

    <script src="../assets/js/bootstrap.js"></script>


</body>

</html>
