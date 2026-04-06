<?php

class MercadoPhoneViewHelper
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function jobBadge(string $status): string
    {
        return match ($status) {
            'concluido' => 'badge-green',
            'erro' => 'badge-red',
            'processando' => 'badge-yellow',
            'cancelado' => 'badge-muted',
            default => 'badge-blue',
        };
    }

    public static function mascaraToken(string $token): string
    {
        $token = trim($token);

        if ($token === '') {
            return 'Nao informado';
        }

        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 4) . str_repeat('*', max(4, strlen($token) - 8)) . substr($token, -4);
    }
}
