<?php

class InsightsViewHelper
{
    public static function moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    public static function numero($valor, int $decimais = 0): string
    {
        return number_format((float) $valor, $decimais, ',', '.');
    }

    public static function referencia(array $item): string
    {
        if (($item['nivel'] ?? '') === 'campaign') {
            return $item['campanha_nome'] ?: 'Campanha #' . (int) ($item['campanha_id'] ?? 0);
        }

        if (($item['nivel'] ?? '') === 'adset') {
            return $item['conjunto_nome'] ?: 'Conjunto #' . (int) ($item['conjunto_id'] ?? 0);
        }

        if (($item['nivel'] ?? '') === 'ad') {
            return $item['anuncio_nome'] ?: 'Anúncio #' . (int) ($item['anuncio_id'] ?? 0);
        }

        if (($item['nivel'] ?? '') === 'account') {
            return $item['conta_nome'] ?: 'Conta #' . (int) ($item['conta_id'] ?? 0);
        }

        return '—';
    }
}
