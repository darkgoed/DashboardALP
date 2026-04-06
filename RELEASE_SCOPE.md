# Release Scope

## Blocos que entram obrigatoriamente

### Harden de producao

- `.htaccess`
- `.env.example`
- `app/config/bootstrap.php`
- `app/helpers/Tenant.php`
- `public/router.php`
- `public/callback_meta.php`
- `public/uploads/.htaccess`
- `cron/check_operacao.php`
- `OPERACAO.md`

### Nova base obrigatoria

Esses arquivos novos nao podem ficar fora da release, porque ja existem referencias diretas no codigo:

- `app/helpers/Csrf.php`
- `app/helpers/flash.php`
- `app/helpers/url.php`
- `app/helpers/uuid.php`
- `app/support/Security/EmpresaAccessGuard.php`
- `app/support/MenuSidebarHelper.php`
- `app/support/Http/JsonResponse.php`
- `app/support/View/`
- `app/support/Metrics/`
- `app/support/Validation/`
- `public/assets/`
- `public/index.php`
- `public/perfil/`
- `public/empresas/`
- `public/usuarios/`
- `public/contas/`
- `public/api.php`
- `public/api_sync.php`
- `public/sync_dashboard.php`
- `public/sync_job_view.php`
- `public/sync_logs.php`
- `public/sync_requeue.php`
- `public/recuperar-senha.php`
- `public/relatorios_enviar.php`
- `public/licenca/`
- `public/termos/`
- `public/privacidade/`

### Servicos e modelos novos obrigatorios

Ja aparecem como dependencia funcional da base atual:

- `app/models/Empresa.php`
- `app/models/EmpresaAssinatura.php`
- `app/models/Plano.php`
- `app/models/ConviteEmpresa.php`
- `app/models/EnvioRelatorio.php`
- `app/services/Empresas/EmpresaLicencaService.php`
- `app/services/Empresas/EmpresaLimiteService.php`
- `app/services/ConviteEmpresaService.php`
- `app/services/PasswordResetService.php`
- `app/services/RelatorioEmailService.php`
- `app/services/RelatorioPublicLinkService.php`
- `app/services/MercadoPhone/MercadoPhoneService.php`
- `app/services/MercadoPhone/MercadoPhoneQueueService.php`
- `app/services/Meta/MetaSyncEnqueueService.php`
- `app/services/Empresas/EmpresaDeletionService.php`
- `app/services/EntityDeletionService.php`
- `app/services/Auth/`
- `app/services/Config/`
- `app/services/Contas/`
- `app/services/Dashboard/`
- `app/services/Empresas/`
- `app/services/Licenca/`
- `app/services/MercadoPhone/`
- `app/services/Meta/`
- `app/services/Perfil/`
- `app/services/Relatorios/`
- `app/services/Sync/`
- `app/services/System/`
- `app/services/Usuarios/`

## Mudancas que parecem parte de migracao planejada

Essas mudancas sao amplas e devem entrar juntas com a nova base, nao isoladas:

- `public/dashboard.php`
- `public/metricas.php`
- `public/relatorios.php`
- `public/relatorio_view.php`
- `public/conexoes.php`
- `public/clientes.php`
- `public/contas.php`
- `public/campanhas.php`
- `public/insights.php`
- `public/login.php`
- `public/logout.php`
- `public/integracoes_meta.php`
- `public/partials/menu_lateral.php`
- `app/models/Cliente.php`
- `app/models/ContaAds.php`
- `app/models/Campanha.php`
- `app/models/Usuario.php`
- `app/models/CanalEmail.php`
- `app/models/SyncJob.php`
- `app/services/Meta/MetaAdsService.php`
- `app/services/Meta/MetaSyncQueueService.php`
- `app/services/Meta/MetaSyncService.php`
- `app/services/MetricsService.php`
- `app/services/RelatorioService.php`
- `app/services/EmailChannelService.php`
- `cron/enqueue_meta_insights.php`
- `cron/enqueue_meta_structure.php`
- `cron/meta_maintenance.php`
- `cron/process_meta_sync_queue.php`
- `cron/sync_mercado_phone.php`
- `cron/crontab.example`

## Remocoes que so fazem sentido se a migracao nova entrar

- `assets/css/dashboard-metrics.css`
- `assets/css/dashboard.css`
- `assets/css/global.css`
- `assets/css/login.css`
- `assets/css/relatorio-view.css`
- `assets/js/dashboard.js`
- `assets/js/nav-config.js`
- `cron/enqueue_meta_reconcile.php`
- `cron/process_meta_queue.php`
- `cron/sync_meta.php`
- `public/process_queue.php`

## Nao recomendado para release parcial

Nao subir apenas os arquivos de hardening sem a base nova se o servidor de producao ainda estiver usando a estrutura antiga de helpers e assets.

Nao subir apenas as delecoes de `assets/` e scripts legados sem garantir que `public/assets/` e os novos entrypoints estejam versionados no mesmo release.

## Sequencia recomendada

1. Versionar todos os arquivos novos obrigatorios.
2. Incluir as mudancas rastreadas que compoem a nova base.
3. Incluir as remocoes dos caminhos antigos no mesmo release.
4. Rodar `php cron/check_operacao.php --json`.
5. Validar login, dashboard, relatorios, Meta e fila.

## Observacoes finais

- A base atual passou a aceitar organizacao por dominio em `app/services/*/` e `app/support/*/` via autoload no `bootstrap.php`.
- Os wrappers legados em `public/usuarios/usuario_*` e `public/usuarios/usuarios.php` foram mantidos por compatibilidade e podem ser removidos em uma limpeza futura controlada.
- Os entrypoints publicos foram reduzidos para bordas finas: auth/csrf, resolucao de service, flash/json e renderizacao/redirect.
