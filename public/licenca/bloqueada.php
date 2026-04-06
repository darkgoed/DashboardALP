<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

Auth::requireLogin();

$db = new Database();
$conn = $db->connect();
$pageData = (new LicencaBloqueadaPageService($conn))->getPageData();
$empresa = $pageData['empresa'];
$motivo = $pageData['motivo'];
$statusAssinatura = $pageData['status_assinatura'];
$dataVencimento = $pageData['data_vencimento'];
$dataLimiteTolerancia = $pageData['data_limite_tolerancia'];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licença bloqueada</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        .bloqueio-wrap {
            min-height: calc(100vh - 40px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }

        .bloqueio-card {
            width: 100%;
            max-width: 760px;
            background: var(--card, rgba(15, 23, 42, 0.72));
            border: 1px solid var(--line, rgba(255,255,255,0.08));
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.28);
        }

        .bloqueio-topo {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .bloqueio-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            flex-shrink: 0;
        }

        .bloqueio-topo h1 {
            margin: 0 0 8px;
            font-size: 28px;
            line-height: 1.1;
        }

        .bloqueio-topo p {
            margin: 0;
            color: var(--text-soft, rgba(255,255,255,0.72));
            font-size: 14px;
            line-height: 1.6;
        }

        .bloqueio-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 24px;
        }

        .bloqueio-item {
            border: 1px solid var(--line, rgba(255,255,255,0.08));
            border-radius: 18px;
            padding: 16px 18px;
            background: rgba(255,255,255,0.02);
        }

        .bloqueio-label {
            display: block;
            font-size: 12px;
            color: var(--text-soft, rgba(255,255,255,0.64));
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .bloqueio-valor {
            font-size: 15px;
            font-weight: 600;
            color: var(--text, #fff);
        }

        .bloqueio-aviso {
            margin-top: 24px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(245, 158, 11, 0.20);
            background: rgba(245, 158, 11, 0.08);
            color: #fbbf24;
            font-size: 14px;
            line-height: 1.6;
        }

        .bloqueio-acoes {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 26px;
        }

        .btn-bloqueio {
            height: 44px;
            border-radius: 14px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid transparent;
            transition: .18s ease;
        }

        .btn-bloqueio-primary {
            background: var(--primary, #4f46e5);
            color: #fff;
        }

        .btn-bloqueio-primary:hover {
            transform: translateY(-1px);
            opacity: .96;
        }

        .btn-bloqueio-secondary {
            background: transparent;
            color: var(--text, #fff);
            border-color: var(--line, rgba(255,255,255,0.10));
        }

        .btn-bloqueio-secondary:hover {
            background: rgba(255,255,255,0.04);
        }

        @media (max-width: 720px) {
            .bloqueio-grid {
                grid-template-columns: 1fr;
            }

            .bloqueio-card {
                padding: 22px;
            }

            .bloqueio-topo h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body class="page">
    <div class="app">

        <main class="main">
            <section class="bloqueio-wrap">
                <div class="bloqueio-card">
                    <div class="bloqueio-topo">
                        <div class="bloqueio-icon">
                            <i data-lucide="shield-alert"></i>
                        </div>

                        <div>
                            <h1>Licença expirada ou bloqueada</h1>
                            <p>
                                O acesso da sua empresa ao Dashboard ALP está temporariamente indisponível.
                                Regularize a assinatura para voltar a utilizar o sistema normalmente.
                            </p>
                        </div>
                    </div>

                    <div class="bloqueio-grid">
                        <div class="bloqueio-item">
                            <span class="bloqueio-label">Empresa</span>
                            <div class="bloqueio-valor">
                                <?= htmlspecialchars($empresa['nome_fantasia'] ?? 'Empresa não identificada'); ?>
                            </div>
                        </div>

                        <div class="bloqueio-item">
                            <span class="bloqueio-label">Status da licença</span>
                            <div class="bloqueio-valor">
                                <?= htmlspecialchars($statusAssinatura); ?>
                            </div>
                        </div>

                        <div class="bloqueio-item">
                            <span class="bloqueio-label">Vencimento</span>
                            <div class="bloqueio-valor">
                                <?= htmlspecialchars(LicencaBloqueadaPageService::formatDate($dataVencimento)); ?>
                            </div>
                        </div>

                        <div class="bloqueio-item">
                            <span class="bloqueio-label">Limite da tolerância</span>
                            <div class="bloqueio-valor">
                                <?= htmlspecialchars(LicencaBloqueadaPageService::formatDate($dataLimiteTolerancia)); ?>
                            </div>
                        </div>
                    </div>

                    <div class="bloqueio-aviso">
                        <strong>Motivo:</strong>
                        <?= htmlspecialchars($motivo); ?>
                        <br>
                        Entre em contato com o responsável pela plataforma para renovar ou reativar sua empresa.
                    </div>

                    <div class="bloqueio-acoes">
                        <a href="https://wa.me/5512996062155" target="_blank" class="btn-bloqueio btn-bloqueio-primary">
                            <i data-lucide="message-circle"></i>
                            <span>Falar sobre renovação</span>
                        </a>

                        <a href="<?= htmlspecialchars(routeUrl('dashboard')); ?>" class="btn-bloqueio btn-bloqueio-secondary">
                            <i data-lucide="arrow-left"></i>
                            <span>Tentar novamente</span>
                        </a>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>

</body>
</html>
