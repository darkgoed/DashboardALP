# Architecture Overview

## Estado atual

O projeto foi reorganizado para deixar `public/` como borda HTTP fina e concentrar regra de negocio em `app/services` e `app/support`.

## app/services

### Auth

- `app/services/Auth/LoginPageService.php`
- `app/services/Auth/LogoutActionService.php`
- `app/services/Auth/PasswordRecoveryPageService.php`
- `app/services/Auth/ConviteAcceptanceService.php`

### Config

- `app/services/Config/ConexoesPageService.php`
- `app/services/Config/MetricasConfigService.php`
- `app/services/Config/PersonalizacaoPageService.php`

### Contas

- `app/services/Contas/ContaManagementService.php`
- `app/services/Contas/ContaWriteService.php`
- `app/services/Contas/ContaSyncActionService.php`
- `app/services/Contas/ContaFullSyncActionService.php`

### Dashboard

- `app/services/Dashboard/DashboardPageService.php`
- `app/services/Dashboard/DashboardMetaSummaryService.php`

### Empresas

- `app/services/Empresas/EmpresaManagementService.php`
- `app/services/Empresas/EmpresaPageService.php`
- `app/services/Empresas/EmpresaWriteService.php`
- `app/services/Empresas/EmpresaAdminActionService.php`
- `app/services/Empresas/EmpresaLicencaService.php`
- `app/services/Empresas/EmpresaLimiteService.php`
- `app/services/Empresas/EmpresaDeletionService.php`

### Licenca

- `app/services/Licenca/LicencaBloqueadaPageService.php`

### MercadoPhone

- `app/services/MercadoPhone/MercadoPhoneService.php`
- `app/services/MercadoPhone/MercadoPhoneQueueService.php`
- `app/services/MercadoPhone/MercadoPhonePageService.php`
- `app/services/MercadoPhone/MercadoPhoneSyncActionService.php`

### Meta

- `app/services/Meta/MetaAdsService.php`
- `app/services/Meta/MetaCallbackService.php`
- `app/services/Meta/MetaSyncEnqueueService.php`
- `app/services/Meta/MetaSyncQueueService.php`
- `app/services/Meta/MetaSyncService.php`
- `app/services/Meta/IntegracaoMetaPageService.php`

### Perfil

- `app/services/Perfil/PerfilPageService.php`
- `app/services/Perfil/PerfilUpdateService.php`

### Relatorios

- `app/services/Relatorios/RelatorioPageService.php`
- `app/services/Relatorios/RelatorioDeliveryService.php`
- `app/services/Relatorios/RelatorioViewPageService.php`

### Sync

- `app/services/Sync/SyncMonitoringService.php`
- `app/services/Sync/SyncJobActionService.php`
- `app/services/Sync/SyncJobViewService.php`

### System

- `app/services/System/PublicRouterService.php`

### Usuarios

- `app/services/Usuarios/UsuarioManagementService.php`
- `app/services/Usuarios/UsuarioWriteService.php`

### Ainda flat, mas estaveis

- `app/services/ClienteManagementService.php`
- `app/services/ClienteWriteService.php`
- `app/services/CampanhaManagementService.php`
- `app/services/CampanhaWriteService.php`
- `app/services/InsightsPageService.php`
- `app/services/ConviteEmpresaService.php`
- `app/services/PasswordResetService.php`
- `app/services/RelatorioEmailService.php`
- `app/services/RelatorioPublicLinkService.php`
- `app/services/RelatorioService.php`
- `app/services/MetricsService.php`
- `app/services/EmailChannelService.php`
- `app/services/EntityDeletionService.php`

## app/support

### Http

- `app/support/Http/JsonResponse.php`

### Metrics

- `app/support/Metrics/DashboardMetricsHelper.php`
- `app/support/Metrics/RelatorioMetricsHelper.php`

### Security

- `app/support/Security/EmpresaAccessGuard.php`

### Validation

- `app/support/Validation/FormValidationException.php`

### View

- `app/support/View/CampanhaViewHelper.php`
- `app/support/View/EmpresaPageHelper.php`
- `app/support/View/InsightsViewHelper.php`
- `app/support/View/MercadoPhoneViewHelper.php`
- `app/support/View/PerfilViewHelper.php`
- `app/support/View/StaticContentPageRenderer.php`
- `app/support/View/SyncJobViewHelper.php`

### Flat helper mantido

- `app/support/MenuSidebarHelper.php`

## public

- Os entrypoints publicos agora fazem principalmente:
  - bootstrap
  - auth/permissao/csrf
  - instanciacao de service
  - flash/json
  - renderizacao ou redirect

## Legado mantido

- `public/usuarios/usuario_*`
- `public/usuarios/usuarios.php`

Esses wrappers foram mantidos por compatibilidade. A remocao deles deve acontecer apenas em uma limpeza futura controlada.
