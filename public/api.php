<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();

EmpresaAccessGuard::assertPodeOperar($conn);

$empresaId = Auth::getEmpresaId();
$pageService = new MercadoPhonePageService($conn, $empresaId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::isValid()) {
        Flash::error('Token CSRF invalido.');
        header('Location: ' . routeUrl('api'));
        exit;
    }

    try {
        $message = $pageService->saveConfigs($_POST);
        if ($message === 'Nenhuma integracao valida foi enviada para salvar.') {
            Flash::info($message);
        } else {
            Flash::success($message);
        }
    } catch (Throwable $e) {
        Flash::error('Falha ao salvar configuracoes do Mercado Phone: ' . $e->getMessage());
    }

    header('Location: ' . routeUrl('api'));
    exit;
}
$pageData = $pageService->getPageData();
$integracoes = $pageData['integracoes'];
$jobsMercadoPhone = $pageData['jobs_mercado_phone'];
$integracoesJson = $pageData['integracoes_json'];
$clientesJson = $pageData['clientes_json'];
$contasPorClienteJson = $pageData['contas_por_cliente_json'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API - Dashboard ALP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/toast.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        .mp-config-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .mp-config-card {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 18px;
            background: rgba(15, 23, 42, 0.38);
            min-width: 0;
        }

        .mp-config-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .mp-config-title {
            margin: 0;
            font-size: 15px;
        }

        .mp-config-subtitle {
            margin: 6px 0 0;
            color: var(--text-muted);
            font-size: 12px;
        }

        .mp-config-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 14px;
            min-width: 0;
        }

        .mp-toggle-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(160px, 1fr));
            gap: 10px;
            margin-top: 14px;
            min-width: 0;
        }

        .mp-checkbox {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
        }

        .mp-card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mp-status-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            width: 100%;
            min-width: 0;
        }

        .mp-status-list .data-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            align-items: flex-start;
            gap: 16px;
            height: 100%;
            min-width: 0;
        }

        .mp-status-list .data-item-left,
        .mp-status-list .data-item-meta,
        .mp-status-list .data-item-title,
        .mp-status-list .field-help {
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .mp-status-list .data-item-right {
            display: flex;
            flex-direction: row;
            min-width: 0;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: wrap;
        }

        .mp-status-list .mp-card-actions {
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
            min-width: 0;
        }

        .mp-status-list .btn {
            width: auto;
        }

        .mp-status-list .data-item-meta span {
            display: inline-flex;
            align-items: center;
        }

        .mp-add-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        @media (max-width: 900px) {
            .mp-config-grid,
            .mp-toggle-grid {
                grid-template-columns: 1fr;
            }

            .mp-config-card {
                padding: 16px;
            }
        }

        @media (max-width: 1100px) {
            .mp-status-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .mp-config-head,
            .mp-add-row {
                flex-direction: column;
                align-items: stretch;
            }

            .mp-status-list .data-item {
                grid-template-columns: 1fr;
            }

            .mp-status-list .data-item-right,
            .mp-status-list .mp-card-actions {
                width: 100%;
                min-width: 0;
                justify-content: flex-start;
            }

            .mp-card-actions .btn,
            .mp-add-row .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body class="page page-integracoes">
    <div class="app">
        <?php require_once __DIR__ . '/partials/menu_lateral.php'; ?>

        <main class="main">
            <section class="page-hero">
                <div class="page-hero-left">
                    <div class="page-hero-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 3v18"></path>
                            <path d="M3 12h18"></path>
                            <path d="M7.5 7.5h9v9h-9z"></path>
                        </svg>
                    </div>

                    <div>
                        <span class="page-kicker">Integração opcional</span>
                        <h1 class="page-title">API Mercado Phone</h1>
                        <p class="page-subtitle">
                            Configure várias APIs por cliente e por conta de anúncio. Cada integração pode ser ativada separadamente e ainda escolher se entra no dashboard e nos relatórios.
                        </p>
                    </div>
                </div>

                <div class="page-hero-actions">
                    <a href="<?= htmlspecialchars(routeUrl('api')); ?>" class="btn btn-secondary">Atualizar</a>
                </div>
            </section>

            <section class="content-grid-wide">
                <div class="panel list-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Integrações configuradas</h3>
                            <p class="panel-subtitle">Cada linha representa um token do Mercado Phone vinculado a uma conta de anúncio.</p>
                        </div>
                    </div>

                    <?php if (empty($integracoes)): ?>
                        <div class="data-item-empty">Nenhuma API do Mercado Phone configurada ainda.</div>
                    <?php else: ?>
                        <div class="data-list mp-status-list">
                            <?php foreach ($integracoes as $integracao): ?>
                                <?php
                                $integracaoOperante = !empty($integracao['ativo']) && trim((string) ($integracao['api_token'] ?? '')) !== '';
                                $ultimaSyncProdutos = $integracao['ultima_sync_produtos_em'] ?? null;
                                $ultimaSyncClientes = $integracao['ultima_sync_clientes_em'] ?? null;
                                $ultimaSyncVendas = $integracao['ultima_sync_vendas_em'] ?? null;
                                $ultimoErroSync = trim((string) ($integracao['ultimo_erro_sync'] ?? ''));
                                ?>
                                <div class="data-item" id="integracao-<?= (int) $integracao['id'] ?>">
                                    <div class="data-item-left">
                                        <div class="data-item-title">
                                            <?= htmlspecialchars((string) ($integracao['cliente_nome'] ?? 'Cliente sem nome')) ?>
                                            <?php if (!empty($integracao['conta_nome'])): ?>
                                                <span style="opacity:.75;">/ <?= htmlspecialchars((string) $integracao['conta_nome']) ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="data-item-meta">
                                            <span><strong>Status:</strong> <?= $integracaoOperante ? 'Ativo' : (!empty($integracao['ativo']) ? 'Incompleto' : 'Inativo') ?></span>
                                            <span><strong>Token:</strong> <?= htmlspecialchars(MercadoPhoneViewHelper::mascaraToken((string) ($integracao['api_token'] ?? ''))) ?></span>
                                            <span><strong>Dashboard:</strong> <?= !empty($integracao['exibir_dashboard']) ? 'Exibir' : 'Ocultar' ?></span>
                                            <span><strong>Relatórios:</strong> <?= !empty($integracao['exibir_relatorios']) ? 'Exibir' : 'Ocultar' ?></span>
                                            <span><strong>Meta Account ID:</strong> <?= htmlspecialchars((string) ($integracao['meta_account_id'] ?? '-')) ?></span>
                                            <span><strong>Sync produtos:</strong> <?= MercadoPhoneViewHelper::e($ultimaSyncProdutos ?: 'Nunca') ?></span>
                                            <span><strong>Sync clientes:</strong> <?= MercadoPhoneViewHelper::e($ultimaSyncClientes ?: 'Nunca') ?></span>
                                            <span><strong>Sync vendas:</strong> <?= MercadoPhoneViewHelper::e($ultimaSyncVendas ?: 'Nunca') ?></span>
                                        </div>

                                        <?php if ($ultimoErroSync !== ''): ?>
                                            <div class="field-help" style="margin-top:8px; color: var(--danger, #ef4444);">
                                                Último erro: <?= MercadoPhoneViewHelper::e($ultimoErroSync) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="data-item-right">
                                        <?php if ($integracaoOperante): ?>
                                            <span class="badge badge-green">Ativo</span>
                                        <?php elseif (!empty($integracao['ativo'])): ?>
                                            <span class="badge badge-yellow">Pendente</span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Inativo</span>
                                        <?php endif; ?>

                                        <div class="mp-card-actions">
                                            <?php if ($integracaoOperante): ?>
                                                <form method="POST" action="<?= htmlspecialchars(routeUrl('api_sync')); ?>" style="display:inline;">
                                                    <?= Csrf::field() ?>
                                                    <input type="hidden" name="integracao_id" value="<?= (int) $integracao['id'] ?>">
                                                    <input type="hidden" name="modo_sync" value="incremental">
                                                    <button type="submit" class="btn btn-primary btn-sm">Sync incremental</button>
                                                </form>

                                                <form method="POST" action="<?= htmlspecialchars(routeUrl('api_sync')); ?>" style="display:inline;" onsubmit="return confirm('Enfileirar full sync do Mercado Phone para esta integração?');">
                                                    <?= Csrf::field() ?>
                                                    <input type="hidden" name="integracao_id" value="<?= (int) $integracao['id'] ?>">
                                                    <input type="hidden" name="modo_sync" value="full">
                                                    <button type="submit" class="btn btn-secondary btn-sm">Full sync</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel form-panel" style="margin-top: 18px;">
                <div class="panel-header">
                    <div>
                        <h3>Cadastro das APIs</h3>
                        <p class="panel-subtitle">
                            Selecione o cliente, a conta de anúncio vinculada e cole o token correspondente. Use o botão abaixo para adicionar quantas APIs forem necessárias.
                        </p>
                    </div>
                </div>

                <form method="POST" class="form-stack" autocomplete="off" id="mercado-phone-form">
                    <?= Csrf::field() ?>

                    <div class="mp-config-list" id="mp-config-list"></div>

                    <div class="mp-add-row">
                        <button type="button" class="btn btn-secondary" id="add-mp-config">Adicionar mais uma API</button>
                        <button type="submit" class="btn btn-primary">Salvar configurações</button>
                    </div>
                </form>
            </section>

            <section class="panel list-panel" style="margin-top: 18px;">
                <div class="panel-header">
                    <div>
                        <h3>Fila Mercado Phone</h3>
                        <p class="panel-subtitle">Últimos jobs do Mercado Phone para acompanhamento operacional.</p>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="<?= htmlspecialchars(routeUrl('sync_logs') . '?tipo=mercado_phone'); ?>" class="btn btn-secondary btn-sm">Ver fila completa</a>
                    </div>
                </div>

                <?php if (empty($jobsMercadoPhone)): ?>
                    <div class="data-item-empty">Nenhum job Mercado Phone encontrado.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente / Conta</th>
                                <th>Status</th>
                                <th>Origem</th>
                                <th>Mensagem</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobsMercadoPhone as $jobFila): ?>
                                <tr>
                                    <td>#<?= (int) $jobFila['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars((string) ($jobFila['cliente_nome'] ?? '-')) ?><br>
                                        <small><?= htmlspecialchars((string) (($jobFila['conta_nome'] ?? '-') . ' / ' . ($jobFila['meta_account_id'] ?? '-'))) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= MercadoPhoneViewHelper::jobBadge((string) $jobFila['status']) ?>">
                                            <?= htmlspecialchars((string) $jobFila['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($jobFila['origem'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) ($jobFila['mensagem'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) ($jobFila['finalizado_em'] ?: $jobFila['criado_em'] ?: '-')) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars(routeUrl('sync_job_view') . '?id=' . (int) $jobFila['id']); ?>" class="btn btn-secondary btn-sm">Detalhes</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <template id="mp-config-template">
        <div class="mp-config-card" data-config-row>
            <input type="hidden" name="integracao_id[]" value="">

            <div class="mp-config-head">
                <div>
                    <h4 class="mp-config-title">Nova API Mercado Phone</h4>
                    <p class="mp-config-subtitle">Vincule um token a um cliente e a uma conta de anúncio específica.</p>
                </div>

                <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remover linha</button>
            </div>

            <div class="mp-config-grid">
                <div class="field field-select">
                    <label>Cliente</label>
                    <select name="cliente_id[]" data-cliente-select></select>
                </div>

                <div class="field field-select">
                    <label>Conta de anúncio</label>
                    <select name="conta_id[]" data-conta-select></select>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label>Token da API</label>
                    <textarea name="api_token[]" rows="5" placeholder="Cole aqui o token da API do Mercado Phone" data-token-input></textarea>
                    <div class="field-help">A exibição no dashboard e nos relatórios respeita as opções marcadas abaixo.</div>
                </div>
            </div>

            <div class="mp-toggle-grid">
                <label class="mp-checkbox">
                    <input type="checkbox" name="mercado_phone_ativo[]" value="1" data-ativo-checkbox>
                    <span>
                        <strong>Ativar integração</strong><br>
                        <small>Se desmarcado, o sistema ignora esta API.</small>
                    </span>
                </label>

                <label class="mp-checkbox">
                    <input type="checkbox" name="exibir_dashboard[]" value="1" data-dashboard-checkbox>
                    <span>
                        <strong>Exibir no dashboard</strong><br>
                        <small>Inclui os dados desta API no dashboard.</small>
                    </span>
                </label>

                <label class="mp-checkbox">
                    <input type="checkbox" name="exibir_relatorios[]" value="1" data-relatorios-checkbox>
                    <span>
                        <strong>Exibir nos relatórios</strong><br>
                        <small>Inclui os dados desta API nos relatórios.</small>
                    </span>
                </label>
            </div>
        </div>
    </template>

    <?php Flash::renderScript(); ?>
    <script>
        const clientesMp = <?= $clientesJson ?: '[]'; ?>;
        const contasPorClienteMp = <?= $contasPorClienteJson ?: '{}'; ?>;
        const integracoesMp = <?= $integracoesJson ?: '[]'; ?>;

        const listEl = document.getElementById('mp-config-list');
        const templateEl = document.getElementById('mp-config-template');
        const addButton = document.getElementById('add-mp-config');

        function fillClienteOptions(select, selectedId) {
            select.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecione um cliente';
            select.appendChild(placeholder);

            clientesMp.forEach((cliente) => {
                const option = document.createElement('option');
                option.value = String(cliente.id);
                option.textContent = cliente.nome || `Cliente #${cliente.id}`;
                option.selected = Number(selectedId) === Number(cliente.id);
                select.appendChild(option);
            });
        }

        function fillContaOptions(select, clienteId, selectedContaId) {
            select.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = clienteId ? 'Selecione uma conta' : 'Selecione primeiro o cliente';
            select.appendChild(placeholder);

            const contas = contasPorClienteMp[String(clienteId)] || [];

            contas.forEach((conta) => {
                const option = document.createElement('option');
                option.value = String(conta.id);
                option.textContent = conta.meta_account_id
                    ? `${conta.nome} (${conta.meta_account_id})`
                    : conta.nome;
                option.selected = Number(selectedContaId) === Number(conta.id);
                select.appendChild(option);
            });
        }

        function updateCardTitle(card) {
            const clienteSelect = card.querySelector('[data-cliente-select]');
            const contaSelect = card.querySelector('[data-conta-select]');
            const title = card.querySelector('.mp-config-title');
            const subtitle = card.querySelector('.mp-config-subtitle');

            const clienteText = clienteSelect.options[clienteSelect.selectedIndex]?.textContent || 'Cliente';
            const contaText = contaSelect.options[contaSelect.selectedIndex]?.textContent || 'Conta';

            if (clienteSelect.value && contaSelect.value) {
                title.textContent = `${clienteText} / ${contaText}`;
                subtitle.textContent = 'Token, ativação e visibilidade desta integração.';
            } else {
                title.textContent = 'Nova API Mercado Phone';
                subtitle.textContent = 'Vincule um token a um cliente e a uma conta de anúncio específica.';
            }
        }

        function attachRowHandlers(card) {
            const clienteSelect = card.querySelector('[data-cliente-select]');
            const contaSelect = card.querySelector('[data-conta-select]');
            const removeButton = card.querySelector('[data-remove-row]');

            clienteSelect.addEventListener('change', () => {
                fillContaOptions(contaSelect, clienteSelect.value, 0);
                updateCardTitle(card);
            });

            contaSelect.addEventListener('change', () => {
                updateCardTitle(card);
            });

            removeButton.addEventListener('click', () => {
                card.remove();

                if (!listEl.querySelector('[data-config-row]')) {
                    addIntegrationRow();
                }
            });
        }

        function addIntegrationRow(data = {}) {
            const fragment = templateEl.content.cloneNode(true);
            const card = fragment.querySelector('[data-config-row]');
            const idInput = card.querySelector('input[name="integracao_id[]"]');
            const clienteSelect = card.querySelector('[data-cliente-select]');
            const contaSelect = card.querySelector('[data-conta-select]');
            const tokenInput = card.querySelector('[data-token-input]');
            const ativoCheckbox = card.querySelector('[data-ativo-checkbox]');
            const dashboardCheckbox = card.querySelector('[data-dashboard-checkbox]');
            const relatoriosCheckbox = card.querySelector('[data-relatorios-checkbox]');
            const removeButton = card.querySelector('[data-remove-row]');

            idInput.value = data.id ? String(data.id) : '';
            fillClienteOptions(clienteSelect, data.cliente_id || 0);
            fillContaOptions(contaSelect, data.cliente_id || 0, data.conta_id || 0);
            tokenInput.value = data.api_token || '';
            ativoCheckbox.checked = Boolean(data.ativo);
            dashboardCheckbox.checked = data.exibir_dashboard === undefined ? true : Boolean(data.exibir_dashboard);
            relatoriosCheckbox.checked = data.exibir_relatorios === undefined ? true : Boolean(data.exibir_relatorios);
            removeButton.style.display = data.id ? 'none' : '';

            attachRowHandlers(card);
            updateCardTitle(card);
            listEl.appendChild(card);
        }

        addButton.addEventListener('click', () => addIntegrationRow());

        if (integracoesMp.length > 0) {
            integracoesMp.forEach((integracao) => addIntegrationRow(integracao));
        } else {
            addIntegrationRow({
                ativo: true,
                exibir_dashboard: true,
                exibir_relatorios: true,
            });
        }
    </script>
    <script src="../assets/js/bootstrap.js"></script>
</body>

</html>
