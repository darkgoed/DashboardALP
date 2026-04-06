<?php

class CampanhaViewHelper
{
    public static function goalLabel(?string $goal): string
    {
        $goal = strtoupper(trim((string) $goal));

        if ($goal === '') {
            return '';
        }

        $map = [
            'CONVERSIONS' => 'Conversoes',
            'OUTCOME_SALES' => 'Vendas',
            'OUTCOME_LEADS' => 'Leads',
            'LEAD_GENERATION' => 'Geracao de Leads',
            'LINK_CLICKS' => 'Cliques no Link',
            'TRAFFIC' => 'Trafego',
            'REACH' => 'Alcance',
            'ENGAGEMENT' => 'Engajamento',
            'OUTCOME_ENGAGEMENT' => 'Engajamento',
            'MESSAGES' => 'Mensagens',
            'OUTCOME_AWARENESS' => 'Reconhecimento',
            'APP_PROMOTION' => 'Promocao de App',
        ];

        return $map[$goal] ?? ucfirst(strtolower(str_replace('_', ' ', $goal)));
    }

    public static function displayName(array $campaign): string
    {
        $name = trim((string) ($campaign['nome'] ?? ''));

        if ($name === '' || $name === 'Post:""' || preg_match('/^Post:\s*"*"*\s*$/i', $name)) {
            $metaId = trim((string) ($campaign['meta_campaign_id'] ?? ''));
            if ($metaId !== '') {
                return 'Campanha #' . $metaId;
            }

            $id = (int) ($campaign['id'] ?? 0);
            return $id > 0 ? 'Campanha #' . $id : 'Campanha sem nome';
        }

        return $name;
    }
}
