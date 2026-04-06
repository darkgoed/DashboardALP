<?php

if (!function_exists('alp_campaign_goal_label')) {
    function alp_campaign_goal_label(?string $goal): string
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
}

if (!function_exists('alp_campaign_display_name')) {
    function alp_campaign_display_name(array $campaign): string
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

if (!function_exists('alp_nav_normalize')) {
    function alp_nav_normalize(string $path): string
    {
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

        if ($path === '') {
            return '';
        }

        if (str_ends_with($path, '.php')) {
            $path = substr($path, 0, -4);
        }

        if ($path === 'index') {
            return '';
        }

        if (str_ends_with($path, '/index')) {
            $path = substr($path, 0, -6);
        }

        return trim($path, '/');
    }
}

if (!function_exists('alp_nav_active')) {
    function alp_nav_active(array $paths, string $currentPath): string
    {
        $currentPath = alp_nav_normalize($currentPath);

        foreach ($paths as $path) {
            $normalized = alp_nav_normalize($path);

            if ($normalized === '') {
                if ($currentPath === '') {
                    return 'active';
                }

                continue;
            }

            if (
                $currentPath === $normalized ||
                str_starts_with($currentPath, $normalized . '/')
            ) {
                return 'active';
            }
        }

        return '';
    }
}

if (!function_exists('alp_nav_root_badge')) {
    function alp_nav_root_badge(): string
    {
        return '<span class="nav-root-badge" title="Somente root" aria-label="Somente root">'
            . '<i data-lucide="shield-check"></i>'
            . '</span>';
    }
}

if (!function_exists('alp_usuario_iniciais')) {
    function alp_usuario_iniciais(string $nome): string
    {
        $nome = trim($nome);

        if ($nome === '') {
            return 'U';
        }

        $partes = preg_split('/\s+/u', $nome) ?: [];
        $iniciais = '';

        foreach (array_slice($partes, 0, 2) as $parte) {
            $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
        }

        return $iniciais !== '' ? $iniciais : 'U';
    }
}
