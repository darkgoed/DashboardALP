<?php

class StaticContentPageService
{
    public function getLegalPage(string $slug): array
    {
        $pages = [
            'privacidade' => [
                'title' => 'Politica de Privacidade - Dashboard ALP',
                'heading' => 'Politica de Privacidade',
                'subtitle' => 'Resumo operacional de tratamento de dados na plataforma.',
                'sections' => [
                    [
                        'title' => '1. Dados tratados',
                        'text' => 'A plataforma trata dados de usuario, empresa, clientes, campanhas, integracoes e registros operacionais necessarios para autenticacao, relatorios e sincronizacoes.',
                    ],
                    [
                        'title' => '2. Finalidade',
                        'text' => 'Os dados sao utilizados para permitir acesso ao painel, gerar relatorios, operar integracoes, validar licenca, auditar envios e manter a seguranca do ambiente.',
                    ],
                    [
                        'title' => '3. Compartilhamento',
                        'text' => 'Dados podem transitar com provedores externos usados pela propria operacao, como SMTP, Meta e outros conectores habilitados pela empresa, sempre dentro do fluxo funcional contratado.',
                    ],
                    [
                        'title' => '4. Seguranca',
                        'text' => 'Credenciais, acessos e integracoes devem ser configurados de forma segura. Logs e registros de erro podem ser armazenados para auditoria, diagnostico e prevencao de incidente.',
                    ],
                    [
                        'title' => '5. Ajustes cadastrais',
                        'text' => 'Atualizacoes de perfil, acesso inicial e redefinicao de senha devem seguir o fluxo oficial da plataforma: convite, login e recuperacao de senha.',
                    ],
                ],
            ],
            'termos' => [
                'title' => 'Termos de Uso - Dashboard ALP',
                'heading' => 'Termos de Uso',
                'subtitle' => 'Regra operacional atual da plataforma em ambiente SaaS.',
                'sections' => [
                    [
                        'title' => '1. Regra de acesso',
                        'text' => 'O acesso ao Dashboard ALP ocorre por convite da empresa ou por conta previamente ativa. Cadastro publico e SSO nao estao habilitados nesta operacao.',
                    ],
                    [
                        'title' => '2. Responsabilidade de credenciais',
                        'text' => 'Cada usuario e responsavel por manter sua senha em sigilo e por utilizar apenas a conta vinculada ao e-mail convidado pela empresa.',
                    ],
                    [
                        'title' => '3. Uso da plataforma',
                        'text' => 'O sistema deve ser usado apenas para fins autorizados pela empresa contratante, respeitando limites de licenca, integracoes habilitadas e regras internas de operacao.',
                    ],
                    [
                        'title' => '4. Integracoes e envios',
                        'text' => 'Envios por e-mail, sincronizacoes e integracoes dependem de configuracao tecnica valida. Falhas externas de provedores ou credenciais podem interromper essas funcoes.',
                    ],
                    [
                        'title' => '5. Restricao de acesso',
                        'text' => 'O acesso pode ser restringido por expiracao de licenca, bloqueio administrativo, violacao de uso ou necessidade operacional de seguranca.',
                    ],
                ],
            ],
        ];

        return $pages[$slug] ?? $pages['privacidade'];
    }
}
