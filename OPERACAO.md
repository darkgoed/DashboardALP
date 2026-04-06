# Operacao SaaS

## Comandos oficiais

Worker da fila principal:

```bash
php cron/process_meta_sync_queue.php 20
```

Enfileirador automatico do Mercado Phone:

```bash
php cron/sync_mercado_phone.php 100
```

Manutencao Meta:

```bash
php cron/meta_maintenance.php
```

Enqueues automaticos Meta:

```bash
php cron/enqueue_meta_insights.php
php cron/enqueue_meta_structure.php
```

Healthcheck operacional:

```bash
php cron/check_operacao.php
php cron/check_operacao.php --json
```

## Cron sugerido

```bash
0 * * * * /usr/bin/php8.5 /var/www/dashboardalp/cron/sync_mercado_phone.php 100 >> /var/www/dashboardalp/app/logs/mercado_phone.log 2>&1
* * * * * flock -n /tmp/process_meta_sync_queue.lock /usr/bin/php8.5 /var/www/dashboardalp/cron/process_meta_sync_queue.php 20 >> /var/www/dashboardalp/app/logs/process_meta_sync_queue.log 2>&1
15,45 * * * * /usr/bin/php8.5 /var/www/dashboardalp/cron/enqueue_meta_insights.php >> /var/www/dashboardalp/app/logs/enqueue_meta_insights.log 2>&1
0 3 * * * /usr/bin/php8.5 /var/www/dashboardalp/cron/enqueue_meta_structure.php >> /var/www/dashboardalp/app/logs/enqueue_meta_structure.log 2>&1
*/15 * * * * /usr/bin/php8.5 /var/www/dashboardalp/cron/meta_maintenance.php >> /var/www/dashboardalp/app/logs/meta_maintenance.log 2>&1
*/10 * * * * /usr/bin/php8.5 /var/www/dashboardalp/cron/check_operacao.php >> /var/www/dashboardalp/app/logs/check_operacao.log 2>&1
```

Template versionado:

```bash
cron/crontab.example
```

## Checklist de homologacao

1. Rodar `php cron/check_operacao.php`.
2. Se quiser integrar com automacao, rodar `php cron/check_operacao.php --json`.
3. Confirmar conexao com banco sem erro.
4. Confirmar variaveis `MAIL_*` globais quando onboarding por convite estiver ativo.
5. Confirmar ao menos um `canais_email` valido para empresas que enviam relatorio.
6. Confirmar jobs aparecendo em `sync_dashboard` e `sync_logs`.
7. Confirmar que `public/test_email.php` e `public/test_queue.php` respondem `404`.
8. Confirmar que `public/test_env.php` responde `404`.
9. Garantir que `public/phpmyadmin` nao esteja exposto no docroot.
10. Garantir bloqueio de execucao em `public/uploads`.
11. Garantir que nenhum dump SQL esteja na raiz operacional do projeto.

## Checklist de release

1. Confirmar `APP_ENV=production` e `APP_DEBUG=false` no `.env` do servidor.
2. Confirmar `APP_URL` final de producao com `https://`.
3. Confirmar `SESSION_NAME` exclusivo do ambiente e revisar `SESSION_SAMESITE`.
4. Rodar `php cron/check_operacao.php --json` e exigir resultado `OK`.
5. Rodar `php -l` nos arquivos PHP alterados no deploy.
6. Validar login, dashboard, integracao Meta, fila `sync_jobs` e envio de relatorio em homologacao.
7. Confirmar crontab oficial carregada a partir de `cron/crontab.example`.
8. Confirmar que `public/phpmyadmin` nao existe no docroot.
9. Confirmar que `public/test_env.php`, `public/test_email.php` e `public/test_queue.php` respondem `404`.
10. Confirmar permissao de escrita apenas onde necessario: `app/logs` e `public/uploads/usuarios`.

## Rollback

1. Restaurar o release anterior do codigo.
2. Restaurar o `.env` correspondente ao release anterior, se houve alteracao.
3. Reaplicar a crontab anterior, se houve alteracao operacional.
4. Rodar `php cron/check_operacao.php --json` novamente para validar retorno a estado `OK`.
5. Validar login, dashboard e processamento de fila antes de liberar o trafego.

## Observacoes

- O worker oficial processa Meta e Mercado Phone pela mesma fila `sync_jobs`.
- Os jobs Meta recorrentes dependem dos enqueues `enqueue_meta_insights.php` e `enqueue_meta_structure.php` para abastecer a fila automaticamente.
- O cron oficial de producao usa `php8.5`, caminho absoluto em `/var/www/dashboardalp` e `flock` no worker para evitar concorrencia duplicada.
- Se `cron/check_operacao.php` retornar `ERRO`, a homologacao nao deve ser considerada concluida.
