<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$usuarioId = Auth::getUsuarioId();

$currentPage = 'relatorios';
$relatorioPageService = new RelatorioPageService($conn, $empresaId);
$pageData = $relatorioPageService->build($_GET, (string) Auth::getUsuarioEmail());

$clientes = $pageData['clientes'];
$contas = $pageData['contas'];
$campanhas = $pageData['campanhas'];
$clienteId = $pageData['cliente_id'];
$contaId = $pageData['conta_id'];
$campanhaId = $pageData['campanha_id'];
$campanhaStatus = $pageData['campanha_status'];
$periodo = $pageData['periodo'];
$dataInicio = $pageData['data_inicio'];
$dataFim = $pageData['data_fim'];
$previewUrl = $pageData['preview_url'];
$printUrl = $pageData['print_url'];
$enviosRecentes = $pageData['envios_recentes'];
$destinoPadrao = $pageData['destino_padrao'];
$contasTodas = $pageData['contas_todas'];
$campanhasTodas = $pageData['campanhas_todas'];
$programacoes = $pageData['programacoes'];
$queryData = $pageData['query_data'];
$returnQuery = http_build_query($queryData);
$destinoWhatsappPadrao = '';

foreach ($clientes as $clienteItem) {
    if ((int) ($clienteItem['id'] ?? 0) === (int) $clienteId) {
        $destinoWhatsappPadrao = trim((string) ($clienteItem['whatsapp'] ?? ''));
        break;
    }
}

function isSelected($value, $current): string
{
    return (string) $value === (string) $current ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatórios - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">

    <style>
        .page-relatorios .main {
            min-width: 0;
        }

        .page-relatorios .content-area {
            display: flex;
            flex-direction: column;
            gap: 18px;
            min-width: 0;
        }

        .page-relatorios .panel {
            width: 100%;
            min-width: 0;
        }

        .page-relatorios .filters-panel {
            padding: 18px;
        }

        .page-relatorios .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .page-relatorios .section-head h2 {
            margin: 0;
            font-size: 15px;
            line-height: 1.2;
        }

        .page-relatorios .section-head p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .page-relatorios .filters-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .page-relatorios .filters-grid {
            display: grid;
            grid-template-columns: repeat(8, minmax(140px, 1fr));
            gap: 12px;
            align-items: end;
            width: 100%;
        }

        .page-relatorios .filters-grid > * {
            min-width: 0;
        }

        .page-relatorios .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .page-relatorios .field label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-soft);
            line-height: 1.2;
            white-space: nowrap;
        }

        .page-relatorios .field select,
        .page-relatorios .field input[type="date"] {
            width: 100%;
        }

        .page-relatorios .actions-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .page-relatorios .btn {
            min-height: 40px;
        }

        .page-relatorios .btn-link-clean {
            text-decoration: none;
        }

        .page-relatorios .preview-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .page-relatorios .preview-head h2 {
            margin: 0;
            font-size: 15px;
            line-height: 1.2;
        }

        .page-relatorios .preview-head p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 12px;
        }

        .page-relatorios .preview-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-relatorios .preview-frame-wrap {
            width: 100%;
            min-width: 0;
            border-radius: 18px;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .page-relatorios .preview-frame {
            width: 100%;
            min-height: 1080px;
            border: none;
            display: block;
            background: #ffffff;
        }

        .page-relatorios .help-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .page-relatorios .email-inline-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
            width: 100%;
        }

        .page-relatorios .email-inline-form .field {
            min-width: 220px;
            flex: 1 1 220px;
        }

        .page-relatorios .history-list {
            display: grid;
            gap: 10px;
        }

        .page-relatorios .history-item {
            padding: 14px 16px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.02);
        }

        .page-relatorios .history-item strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text-color);
            font-size: 12.5px;
        }

        .page-relatorios .history-item span {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .page-relatorios .schedule-stack {
            display: grid;
            gap: 14px;
        }

        .page-relatorios .schedule-card {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
            display: grid;
            gap: 14px;
        }

        .page-relatorios .schedule-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .page-relatorios .schedule-card-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .page-relatorios .schedule-card-title strong {
            font-size: 13px;
        }

        .page-relatorios .schedule-card-title span,
        .page-relatorios .schedule-meta {
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .page-relatorios .schedule-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .page-relatorios .schedule-grid .field {
            min-width: 0;
        }

        .page-relatorios .schedule-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 6px;
        }

        .page-relatorios .schedule-actions .btn {
            min-height: 38px;
        }

        .page-relatorios .help-card {
            padding: 15px 15px 14px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.02);
        }

        .page-relatorios .help-card strong {
            display: block;
            margin-bottom: 6px;
            color: var(--text-color);
            font-size: 12.5px;
        }

        .page-relatorios .help-card span {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 1480px) {
            .page-relatorios .filters-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 1180px) {
            .page-relatorios .filters-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .page-relatorios .schedule-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 980px) {
            .page-relatorios .filters-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .page-relatorios .help-grid {
                grid-template-columns: 1fr;
            }

            .page-relatorios .schedule-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .page-relatorios .filters-panel {
                padding: 16px;
            }

            .page-relatorios .section-head,
            .page-relatorios .preview-head {
                flex-direction: column;
                align-items: stretch;
            }

            .page-relatorios .filters-grid,
            .page-relatorios .help-grid {
                grid-template-columns: 1fr;
            }

            .page-relatorios .actions-row,
            .page-relatorios .preview-actions,
            .page-relatorios .email-inline-form,
            .page-relatorios .schedule-actions,
            .page-relatorios .schedule-card-head {
                flex-direction: column;
                align-items: stretch;
            }

            .page-relatorios .email-inline-form .field {
                min-width: 0;
                flex-basis: auto;
            }

            .page-relatorios .actions-row .btn,
            .page-relatorios .preview-actions .btn,
            .page-relatorios .preview-actions a,
            .page-relatorios .email-inline-form .btn {
                width: 100%;
                justify-content: center;
            }

            .page-relatorios .preview-frame {
                min-height: 860px;
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

<body class="page page-relatorios">
    <div class="layout">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <div class="content-area">

                <section class="page-hero">
                    <div class="page-hero-left">

                        <div class="page-hero-icon">
                            <i data-lucide="scroll-text"></i>
                        </div>

                        <div>
                            <span class="page-kicker">Análise</span>
                            <h1 class="page-title">Relatórios</h1>
                            <p class="page-subtitle">
                                Visualize, filtre e exporte relatórios completos com base nos dados das campanhas.
                                Acompanhe desempenho, tendências e resultados de forma clara e estruturada.
                            </p>
                        </div>

                    </div>

                    <div class="page-hero-actions">
                        <a href="<?= htmlspecialchars(routeUrl('relatorios')); ?>" class="btn btn-secondary">Atualizar tela</a>
                    </div>
                </section>

                <section class="relatorios-grid-top">
                    <div class="panel filters-panel">
                        <div class="section-head">
                            <div>
                                <h2>Filtros do relatório</h2>
                                <p>Defina o escopo do relatório e gere a visualização estruturada abaixo.</p>
                            </div>
                        </div>

                        <form method="GET" action="" class="filters-form">
                            <div class="filters-grid">

                                <div class="field">
                                    <label for="cliente_id">Cliente</label>
                                    <select name="cliente_id" id="cliente_id" onchange="this.form.submit()">
                                        <option value="">Todos</option>
                                        <?php foreach ($clientes as $cliente): ?>
                                            <option value="<?= (int) $cliente['id']; ?>" <?= isSelected($cliente['id'], $clienteId); ?>>
                                                <?= htmlspecialchars($cliente['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="conta_id">Conta</label>
                                    <select name="conta_id" id="conta_id" onchange="this.form.submit()">
                                        <option value="">Todas</option>
                                        <?php foreach ($contas as $conta): ?>
                                            <option value="<?= (int) $conta['id']; ?>" <?= isSelected($conta['id'], $contaId); ?>>
                                                <?= htmlspecialchars($conta['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="campanha_id">Campanha</label>
                                    <select name="campanha_id" id="campanha_id">
                                        <option value="">Todas</option>
                                        <?php foreach ($campanhas as $campanha): ?>
                                            <?php
                                            $campanhaLabel = function_exists('alp_campaign_display_name')
                                                ? alp_campaign_display_name($campanha)
                                                : trim((string) ($campanha['nome'] ?? 'Campanha sem nome'));

                                            $objetivoLabel = function_exists('alp_campaign_goal_label')
                                                ? alp_campaign_goal_label($campanha['objetivo'] ?? '')
                                                : trim((string) ($campanha['objetivo'] ?? ''));

                                            if ($objetivoLabel !== '') {
                                                $campanhaLabel .= ' - ' . $objetivoLabel;
                                            }
                                            ?>
                                            <option value="<?= (int) $campanha['id']; ?>" <?= isSelected($campanha['id'], $campanhaId); ?>>
                                                <?= htmlspecialchars($campanhaLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="campanha_status">Status da campanha</label>
                                    <select name="campanha_status" id="campanha_status">
                                        <option value="">Todos</option>
                                        <option value="ACTIVE" <?= isSelected('ACTIVE', $campanhaStatus); ?>>Ativas</option>
                                        <option value="PAUSED" <?= isSelected('PAUSED', $campanhaStatus); ?>>Pausadas</option>
                                        <option value="DELETED" <?= isSelected('DELETED', $campanhaStatus); ?>>Deletadas</option>
                                        <option value="ARCHIVED" <?= isSelected('ARCHIVED', $campanhaStatus); ?>>Arquivadas</option>
                                        <option value="WITH_ISSUES" <?= isSelected('WITH_ISSUES', $campanhaStatus); ?>>Com problemas</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="periodo">Período</label>
                                    <select name="periodo" id="periodo" onchange="toggleCustomPeriod()">
                                        <option value="1" <?= isSelected('1', $periodo); ?>>Hoje</option>
                                        <option value="3" <?= isSelected('3', $periodo); ?>>Últimos 3 dias</option>
                                        <option value="7" <?= isSelected('7', $periodo); ?>>Últimos 7 dias</option>
                                        <option value="14" <?= isSelected('14', $periodo); ?>>Últimos 14 dias</option>
                                        <option value="15" <?= isSelected('15', $periodo); ?>>Últimos 15 dias</option>
                                        <option value="30" <?= isSelected('30', $periodo); ?>>Últimos 30 dias</option>
                                        <option value="90" <?= isSelected('90', $periodo); ?>>Últimos 90 dias</option>
                                        <option value="custom" <?= isSelected('custom', $periodo); ?>>Personalizado</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="data_inicio">Data início</label>
                                    <input
                                        type="date"
                                        name="data_inicio"
                                        id="data_inicio"
                                        value="<?= htmlspecialchars($dataInicio); ?>">
                                </div>

                                <div class="field">
                                    <label for="data_fim">Data fim</label>
                                    <input
                                        type="date"
                                        name="data_fim"
                                        id="data_fim"
                                        value="<?= htmlspecialchars($dataFim); ?>">
                                </div>

                            </div>

                            <div class="actions-row">
                                <button type="submit" class="btn btn-primary">Gerar relatório</button>
                                <a href="<?= htmlspecialchars($previewUrl); ?>" target="_blank" class="btn btn-secondary btn-link-clean">
                                    Abrir em nova aba
                                </a>
                                <a href="<?= htmlspecialchars($printUrl); ?>" target="_blank" class="btn btn-secondary btn-link-clean">
                                    Imprimir
                                </a>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="panel filters-panel">
                    <div class="section-head">
                        <div>
                            <h2>Enviar por e-mail</h2>
                            <p>Disparo manual imediato com um link publico assinado para o relatorio completo.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(routeUrl('relatorios_enviar')); ?>" class="email-inline-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="cliente_id" value="<?= htmlspecialchars((string) ($clienteId ?: '')); ?>">
                        <input type="hidden" name="conta_id" value="<?= htmlspecialchars((string) ($contaId ?: '')); ?>">
                        <input type="hidden" name="campanha_id" value="<?= htmlspecialchars((string) ($campanhaId ?: '')); ?>">
                        <input type="hidden" name="campanha_status" value="<?= htmlspecialchars($campanhaStatus); ?>">
                        <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo); ?>">
                        <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($dataInicio); ?>">
                        <input type="hidden" name="data_fim" value="<?= htmlspecialchars($dataFim); ?>">

                        <div class="field">
                            <label for="destino_nome">Nome do destinatÃ¡rio</label>
                            <input type="text" id="destino_nome" name="destino_nome" placeholder="Opcional">
                        </div>

                        <div class="field">
                            <label for="destino_email">E-mail de destino</label>
                            <input type="email" id="destino_email" name="destino_email" value="<?= htmlspecialchars($destinoPadrao); ?>" placeholder="voce@empresa.com" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Enviar relatÃ³rio</button>
                        <a href="<?= htmlspecialchars(routeUrl('conexoes')); ?>" class="btn btn-secondary btn-link-clean">Configurar canais</a>
                    </form>
                </section>

                <section class="panel filters-panel">
                    <div class="section-head">
                        <div>
                            <h2>Enviar por WhatsApp</h2>
                            <p>Disparo manual imediato com o mesmo link publico do relatorio, usando o canal conectado na area de conexoes.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(routeUrl('relatorios_enviar')); ?>" class="email-inline-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="canal" value="whatsapp">
                        <input type="hidden" name="cliente_id" value="<?= htmlspecialchars((string) ($clienteId ?: '')); ?>">
                        <input type="hidden" name="conta_id" value="<?= htmlspecialchars((string) ($contaId ?: '')); ?>">
                        <input type="hidden" name="campanha_id" value="<?= htmlspecialchars((string) ($campanhaId ?: '')); ?>">
                        <input type="hidden" name="campanha_status" value="<?= htmlspecialchars($campanhaStatus); ?>">
                        <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo); ?>">
                        <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($dataInicio); ?>">
                        <input type="hidden" name="data_fim" value="<?= htmlspecialchars($dataFim); ?>">

                        <div class="field">
                            <label for="destino_nome_whatsapp">Nome do destinatário</label>
                            <input type="text" id="destino_nome_whatsapp" name="destino_nome" placeholder="Opcional">
                        </div>

                        <div class="field">
                            <label for="destino_whatsapp">WhatsApp de destino</label>
                            <input type="text" id="destino_whatsapp" name="destino_whatsapp" value="<?= htmlspecialchars($destinoWhatsappPadrao); ?>" placeholder="5511999999999" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Enviar relatÃ³rio</button>
                        <a href="<?= htmlspecialchars(routeUrl('conexoes')); ?>" class="btn btn-secondary btn-link-clean">Configurar canais</a>
                    </form>
                </section>

                <section class="panel filters-panel" id="programacoes">
                    <div class="section-head">
                        <div>
                            <h2>Programações automáticas</h2>
                            <p>Crie várias rotinas com filtros, datas, frequência e destinatários diferentes para o mesmo relatório.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(routeUrl('relatorios_programacoes_salvar')); ?>" class="filters-form" id="form-programacoes">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery); ?>">

                        <div class="schedule-stack" id="schedule-list"></div>
                        <div id="schedule-hidden-fields"></div>

                        <div class="schedule-actions">
                            <button type="button" class="btn btn-secondary" id="btn-add-programacao">Adicionar mais uma programação...</button>
                            <div class="preview-actions">
                                <a href="<?= htmlspecialchars(routeUrl('conexoes')); ?>" class="btn btn-secondary btn-link-clean">Configurar canais</a>
                                <button type="submit" class="btn btn-primary">Salvar programações</button>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="panel">
                    <div class="preview-head">
                        <div>
                            <h2>Preview do relatório</h2>
                            <p>Visualização incorporada da versão final em tema claro.</p>
                        </div>

                        <div class="preview-actions">
                            <a href="<?= htmlspecialchars($previewUrl); ?>" target="_blank" class="btn btn-secondary btn-link-clean">
                                Abrir relatório
                            </a>
                            <a href="<?= htmlspecialchars($printUrl); ?>" target="_blank" class="btn btn-secondary btn-link-clean">
                                Versão de impressão
                            </a>
                        </div>
                    </div>

                    <div class="preview-frame-wrap">
                        <iframe
                            src="<?= htmlspecialchars($previewUrl); ?>"
                            class="preview-frame"
                            loading="lazy"></iframe>
                    </div>
                </section>

                <section class="panel">
                    <div class="section-head">
                        <div>
                            <h2>HistÃ³rico recente de envios</h2>
                            <p>Auditoria bÃ¡sica dos disparos manuais de relatÃ³rio por empresa.</p>
                        </div>
                    </div>

                    <?php if (empty($enviosRecentes)): ?>
                        <div class="help-card">
                            <strong>Nenhum envio registrado</strong>
                            <span>Os prÃ³ximos disparos manuais aparecerÃ£o aqui.</span>
                        </div>
                    <?php else: ?>
                        <div class="history-list">
                            <?php foreach ($enviosRecentes as $envio): ?>
                                <div class="history-item">
                                    <strong><?= htmlspecialchars((string) ($envio['status'] ?? 'indefinido')); ?></strong>
                                    <span><?= htmlspecialchars((string) ($envio['mensagem'] ?? '')); ?></span>
                                    <span><?= !empty($envio['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $envio['created_at']))) : ''; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <div class="section-head">
                        <div>
                            <h2>Próxima estrutura do relatório</h2>
                            <p>Organização correta para não perder padrão conforme o módulo crescer.</p>
                        </div>
                    </div>

                    <div class="help-grid">
                        <div class="help-card">
                            <strong>Resumo executivo</strong>
                            <span>Cards principais, tendência, comparação com período anterior e leitura rápida.</span>
                        </div>

                        <div class="help-card">
                            <strong>Insights estruturados</strong>
                            <span>Blocos por categoria: desempenho, alerta, oportunidade e recomendação.</span>
                        </div>

                        <div class="help-card">
                            <strong>Saída profissional</strong>
                            <span>Versão clara, imprimível, exportável e depois pronta para e-mail e WhatsApp.</span>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <script>
        function toggleCustomPeriod() {
            const periodo = document.getElementById('periodo');
            const dataInicio = document.getElementById('data_inicio');
            const dataFim = document.getElementById('data_fim');

            if (!periodo || !dataInicio || !dataFim) return;

            const isCustom = periodo.value === 'custom';

            dataInicio.disabled = !isCustom;
            dataFim.disabled = !isCustom;
        }

        toggleCustomPeriod();

        (function() {
            const form = document.querySelector('.filters-form');
            const cliente = document.getElementById('cliente_id');
            const conta = document.getElementById('conta_id');
            const campanha = document.getElementById('campanha_id');

            if (!form) {
                return;
            }

            if (cliente) {
                cliente.addEventListener('change', function() {
                    if (conta) {
                        conta.value = '';
                    }
                    if (campanha) {
                        campanha.value = '';
                    }
                    form.submit();
                });
            }

            if (conta) {
                conta.addEventListener('change', function() {
                    if (campanha) {
                        campanha.value = '';
                    }
                    form.submit();
                });
            }
        })();

        (function() {
            const clientes = <?= json_encode(array_map(static function (array $cliente): array {
                return [
                    'id' => (int) ($cliente['id'] ?? 0),
                    'nome' => (string) ($cliente['nome'] ?? ''),
                ];
            }, $clientes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const contas = <?= json_encode(array_map(static function (array $conta): array {
                return [
                    'id' => (int) ($conta['id'] ?? 0),
                    'cliente_id' => (int) ($conta['cliente_id'] ?? 0),
                    'nome' => (string) ($conta['nome'] ?? ''),
                ];
            }, $contasTodas), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const campanhas = <?= json_encode(array_map(static function (array $campanha): array {
                $campanhaLabel = function_exists('alp_campaign_display_name')
                    ? alp_campaign_display_name($campanha)
                    : trim((string) ($campanha['nome'] ?? 'Campanha sem nome'));
                $objetivoLabel = function_exists('alp_campaign_goal_label')
                    ? alp_campaign_goal_label($campanha['objetivo'] ?? '')
                    : trim((string) ($campanha['objetivo'] ?? ''));

                if ($objetivoLabel !== '') {
                    $campanhaLabel .= ' - ' . $objetivoLabel;
                }

                return [
                    'id' => (int) ($campanha['id'] ?? 0),
                    'conta_id' => (int) ($campanha['conta_id'] ?? 0),
                    'nome' => $campanhaLabel,
                ];
            }, $campanhasTodas), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const initialSchedules = <?= json_encode($programacoes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const defaultSchedule = {
                cliente_id: <?= (int) $clienteId ?> || '',
                conta_id: <?= (int) $contaId ?> || '',
                campanha_id: <?= (int) $campanhaId ?> || '',
                campanha_status: <?= json_encode((string) $campanhaStatus, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                periodo: <?= json_encode((string) $periodo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                data_inicio: <?= json_encode((string) $dataInicio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                data_fim: <?= json_encode((string) $dataFim, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                destino_email: <?= json_encode((string) $destinoPadrao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                destino_whatsapp: <?= json_encode((string) $destinoWhatsappPadrao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                destino_nome: '',
                enviar_email: 1,
                enviar_whatsapp: <?= json_encode($destinoWhatsappPadrao !== '' ? 1 : 0, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                frequencia_dias: 7,
                horario_envio: '07:00',
                data_inicio_agendamento: <?= json_encode(date('Y-m-d'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                proximo_envio_em: '',
                ultimo_envio_em: '',
                ultimo_status: '',
                ultima_mensagem: '',
                ativo: 1
            };

            const list = document.getElementById('schedule-list');
            const addButton = document.getElementById('btn-add-programacao');
            const formProgramacoes = document.getElementById('form-programacoes');
            const hiddenFields = document.getElementById('schedule-hidden-fields');

            if (!list || !addButton || !formProgramacoes || !hiddenFields) {
                return;
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function optionList(items, selectedValue, emptyLabel) {
                const options = [`<option value="">${escapeHtml(emptyLabel)}</option>`];

                items.forEach(function(item) {
                    const selected = String(item.id) === String(selectedValue || '') ? ' selected' : '';
                    options.push(`<option value="${item.id}"${selected}>${escapeHtml(item.nome)}</option>`);
                });

                return options.join('');
            }

            function statusOptions(selectedValue) {
                const options = [
                    ['', 'Todos'],
                    ['ACTIVE', 'Ativas'],
                    ['PAUSED', 'Pausadas'],
                    ['ARCHIVED', 'Arquivadas'],
                    ['DELETED', 'Deletadas'],
                    ['WITH_ISSUES', 'Com problemas']
                ];

                return options.map(function(item) {
                    const selected = item[0] === String(selectedValue || '') ? ' selected' : '';
                    return `<option value="${escapeHtml(item[0])}"${selected}>${escapeHtml(item[1])}</option>`;
                }).join('');
            }

            function periodOptions(selectedValue) {
                const options = [
                    ['1', 'Último dia'],
                    ['3', 'Últimos 3 dias'],
                    ['7', 'Últimos 7 dias'],
                    ['14', 'Últimos 14 dias'],
                    ['15', 'Últimos 15 dias'],
                    ['30', 'Últimos 30 dias'],
                    ['90', 'Últimos 90 dias'],
                    ['365', 'Último ano'],
                    ['custom', 'Personalizado']
                ];

                return options.map(function(item) {
                    const selected = item[0] === String(selectedValue || '') ? ' selected' : '';
                    return `<option value="${escapeHtml(item[0])}"${selected}>${escapeHtml(item[1])}</option>`;
                }).join('');
            }

            function frequencyOptions(selectedValue) {
                const options = [
                    [1, 'Todo dia'],
                    [3, 'A cada 3 dias'],
                    [7, 'A cada 7 dias'],
                    [14, 'A cada 14 dias'],
                    [30, 'A cada 30 dias']
                ];

                return options.map(function(item) {
                    const selected = String(item[0]) === String(selectedValue || 7) ? ' selected' : '';
                    return `<option value="${item[0]}"${selected}>${escapeHtml(item[1])}</option>`;
                }).join('');
            }

            function formatDateTime(value) {
                if (!value) {
                    return 'Ainda não definido';
                }

                const date = new Date(value.replace(' ', 'T'));
                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString('pt-BR');
            }

            function syncCardOptions(card) {
                const clienteSelect = card.querySelector('[data-field="cliente_id"]');
                const contaSelect = card.querySelector('[data-field="conta_id"]');
                const campanhaSelect = card.querySelector('[data-field="campanha_id"]');
                const periodoSelect = card.querySelector('[data-field="periodo"]');
                const dataInicio = card.querySelector('[data-field="data_inicio"]');
                const dataFim = card.querySelector('[data-field="data_fim"]');

                const clienteId = clienteSelect ? clienteSelect.value : '';
                const availableContas = contas.filter(function(conta) {
                    return !clienteId || String(conta.cliente_id) === String(clienteId);
                });
                const currentConta = contaSelect ? contaSelect.value : '';

                if (contaSelect) {
                    contaSelect.innerHTML = optionList(availableContas, currentConta, 'Todas');
                }

                const contaId = contaSelect ? contaSelect.value : '';
                const availableCampanhas = campanhas.filter(function(campanha) {
                    return !contaId || String(campanha.conta_id) === String(contaId);
                });
                const currentCampanha = campanhaSelect ? campanhaSelect.value : '';

                if (campanhaSelect) {
                    campanhaSelect.innerHTML = optionList(availableCampanhas, currentCampanha, 'Todas');
                }

                const isCustom = periodoSelect && periodoSelect.value === 'custom';
                if (dataInicio) {
                    dataInicio.disabled = !isCustom;
                }
                if (dataFim) {
                    dataFim.disabled = !isCustom;
                }
            }

            function reindexCards() {
                list.querySelectorAll('[data-schedule-card]').forEach(function(card, index) {
                    card.querySelectorAll('[data-name]').forEach(function(field) {
                        field.name = `programacoes[${index}][${field.getAttribute('data-name')}]`;
                    });

                    const title = card.querySelector('[data-schedule-title]');
                    if (title) {
                        title.textContent = 'Programação ' + (index + 1);
                    }
                });
            }

            function serializeSchedules() {
                hiddenFields.innerHTML = '';

                list.querySelectorAll('[data-schedule-card]').forEach(function(card, index) {
                    card.querySelectorAll('[data-name]').forEach(function(field) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `programacoes[${index}][${field.getAttribute('data-name')}]`;
                        input.value = field.value || '';
                        hiddenFields.appendChild(input);
                    });
                });
            }

            function buildCard(schedule) {
                const card = document.createElement('div');
                card.className = 'schedule-card';
                card.setAttribute('data-schedule-card', '1');

                card.innerHTML = `
                    <div class="schedule-card-head">
                        <div class="schedule-card-title">
                            <strong data-schedule-title>Programação</strong>
                            <span>Email ${escapeHtml(schedule.destino_email || 'não definido')} | WhatsApp ${escapeHtml(schedule.destino_whatsapp || 'não definido')}</span>
                        </div>
                        <button type="button" class="btn btn-secondary" data-remove-schedule>Remover</button>
                    </div>
                    <div class="schedule-meta">
                        Próximo envio: ${escapeHtml(formatDateTime(schedule.proximo_envio_em))} | Último envio: ${escapeHtml(formatDateTime(schedule.ultimo_envio_em))}
                        ${schedule.ultimo_status ? ` | Último status: ${escapeHtml(schedule.ultimo_status)}` : ''}
                        ${schedule.ultima_mensagem ? `<br>${escapeHtml(schedule.ultima_mensagem)}` : ''}
                    </div>
                    <div class="schedule-grid">
                        <div class="field">
                            <label>Nome do destinatário</label>
                            <input type="text" value="${escapeHtml(schedule.destino_nome || '')}" data-name="destino_nome">
                        </div>
                        <div class="field">
                            <label>E-mail de destino</label>
                            <input type="email" value="${escapeHtml(schedule.destino_email || '')}" placeholder="voce@empresa.com" data-name="destino_email">
                        </div>
                        <div class="field">
                            <label>WhatsApp de destino</label>
                            <input type="text" value="${escapeHtml(schedule.destino_whatsapp || '')}" placeholder="5511999999999" data-name="destino_whatsapp">
                        </div>
                        <div class="field">
                            <label>Canal e-mail</label>
                            <select data-name="enviar_email">
                                <option value="1"${Number(schedule.enviar_email || 0) === 1 ? ' selected' : ''}>Ativo</option>
                                <option value="0"${Number(schedule.enviar_email || 0) === 0 ? ' selected' : ''}>Desligado</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Canal WhatsApp</label>
                            <select data-name="enviar_whatsapp">
                                <option value="1"${Number(schedule.enviar_whatsapp || 0) === 1 ? ' selected' : ''}>Ativo</option>
                                <option value="0"${Number(schedule.enviar_whatsapp || 0) === 0 ? ' selected' : ''}>Desligado</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Frequência do envio</label>
                            <select data-name="frequencia_dias">${frequencyOptions(schedule.frequencia_dias)}</select>
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <select data-name="ativo">
                                <option value="1"${Number(schedule.ativo || 0) === 1 ? ' selected' : ''}>Ativa</option>
                                <option value="0"${Number(schedule.ativo || 0) === 0 ? ' selected' : ''}>Pausada</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Data inicial do agendamento</label>
                            <input type="date" value="${escapeHtml(schedule.data_inicio_agendamento || '')}" data-name="data_inicio_agendamento">
                        </div>
                        <div class="field">
                            <label>Horário</label>
                            <input type="time" value="${escapeHtml(schedule.horario_envio || '07:00')}" data-name="horario_envio">
                        </div>
                        <div class="field">
                            <label>Cliente</label>
                            <select data-field="cliente_id" data-name="cliente_id">${optionList(clientes, schedule.cliente_id, 'Todos')}</select>
                        </div>
                        <div class="field">
                            <label>Conta</label>
                            <select data-field="conta_id" data-name="conta_id"></select>
                        </div>
                        <div class="field">
                            <label>Campanha</label>
                            <select data-field="campanha_id" data-name="campanha_id"></select>
                        </div>
                        <div class="field">
                            <label>Status da campanha</label>
                            <select data-name="campanha_status">${statusOptions(schedule.campanha_status)}</select>
                        </div>
                        <div class="field">
                            <label>Período do relatório</label>
                            <select data-field="periodo" data-name="periodo">${periodOptions(schedule.periodo)}</select>
                        </div>
                        <div class="field">
                            <label>Data início do relatório</label>
                            <input type="date" value="${escapeHtml(schedule.data_inicio || '')}" data-field="data_inicio" data-name="data_inicio">
                        </div>
                        <div class="field">
                            <label>Data fim do relatório</label>
                            <input type="date" value="${escapeHtml(schedule.data_fim || '')}" data-field="data_fim" data-name="data_fim">
                        </div>
                    </div>
                `;

                const clienteSelect = card.querySelector('[data-field="cliente_id"]');
                const contaSelect = card.querySelector('[data-field="conta_id"]');
                const campanhaSelect = card.querySelector('[data-field="campanha_id"]');
                const periodoSelect = card.querySelector('[data-field="periodo"]');

                if (clienteSelect) {
                    clienteSelect.addEventListener('change', function() {
                        if (contaSelect) {
                            contaSelect.value = '';
                        }
                        if (campanhaSelect) {
                            campanhaSelect.value = '';
                        }
                        syncCardOptions(card);
                    });
                }

                if (contaSelect) {
                    contaSelect.addEventListener('change', function() {
                        if (campanhaSelect) {
                            campanhaSelect.value = '';
                        }
                        syncCardOptions(card);
                    });
                }

                if (periodoSelect) {
                    periodoSelect.addEventListener('change', function() {
                        syncCardOptions(card);
                    });
                }

                const removeButton = card.querySelector('[data-remove-schedule]');
                if (removeButton) {
                    removeButton.addEventListener('click', function() {
                        card.remove();
                        reindexCards();
                    });
                }

                list.appendChild(card);

                if (contaSelect) {
                    contaSelect.value = schedule.conta_id || '';
                }
                syncCardOptions(card);
                if (campanhaSelect) {
                    campanhaSelect.value = schedule.campanha_id || '';
                }
                syncCardOptions(card);
                reindexCards();
            }

            addButton.addEventListener('click', function() {
                buildCard(defaultSchedule);
            });

            formProgramacoes.addEventListener('submit', function() {
                serializeSchedules();
            });

            if (Array.isArray(initialSchedules) && initialSchedules.length > 0) {
                initialSchedules.forEach(function(schedule) {
                    buildCard(schedule);
                });
            } else {
                buildCard(defaultSchedule);
            }
        })();
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <?php Flash::renderScript(); ?>
    <script>
        lucide.createIcons();

        function resetContaEEnviar() {
            const conta = document.getElementById('conta_id');
            if (conta) {
                conta.value = '';
            }
            document.getElementById('filtrosInsights').submit();
        }
    </script>

    <script src="../assets/js/bootstrap.js"></script>

</body>

</html>



