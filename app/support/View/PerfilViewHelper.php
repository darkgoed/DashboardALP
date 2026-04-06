<?php

class PerfilViewHelper
{
    public static function oldValue(array $old, array $usuario, string $key): string
    {
        if (array_key_exists($key, $old)) {
            return htmlspecialchars((string) $old[$key]);
        }

        return htmlspecialchars((string) ($usuario[$key] ?? ''));
    }

    public static function errorHtml(array $errors, string $key): string
    {
        return !empty($errors[$key])
            ? '<div class="form-error">' . htmlspecialchars((string) $errors[$key]) . '</div>'
            : '';
    }

    public static function fotoUrl(array $old, array $usuario): string
    {
        if (array_key_exists('foto', $old)) {
            return (string) $old['foto'];
        }

        return (string) ($usuario['foto'] ?? '');
    }

    public static function iniciais(string $nome): string
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
