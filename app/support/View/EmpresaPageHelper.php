<?php

class EmpresaPageHelper
{
    public static function oldValue(array $old, string $key, $default = ''): string
    {
        return htmlspecialchars((string) ($old[$key] ?? $default));
    }

    public static function isSelected(array $old, string $key, string $value, string $default = ''): string
    {
        $current = (string) ($old[$key] ?? $default);
        return $current === $value ? 'selected' : '';
    }

    public static function errorField(array $errors, string $key): string
    {
        return !empty($errors[$key]) ? '<div class="form-error">' . htmlspecialchars((string) $errors[$key]) . '</div>' : '';
    }

    public static function valueFromSource(array $old, array $empresa, ?array $assinatura, string $key, $default = ''): string
    {
        if (array_key_exists($key, $old)) {
            return htmlspecialchars((string) $old[$key]);
        }

        if ($assinatura && array_key_exists($key, $assinatura) && $assinatura[$key] !== null) {
            return htmlspecialchars((string) $assinatura[$key]);
        }

        if (array_key_exists($key, $empresa) && $empresa[$key] !== null) {
            return htmlspecialchars((string) $empresa[$key]);
        }

        return htmlspecialchars((string) $default);
    }

    public static function selectedEdit(array $old, array $empresa, ?array $assinatura, string $key, string $value, string $default = ''): string
    {
        if (array_key_exists($key, $old)) {
            return (string) $old[$key] === $value ? 'selected' : '';
        }

        if ($assinatura && array_key_exists($key, $assinatura) && $assinatura[$key] !== null) {
            return (string) $assinatura[$key] === $value ? 'selected' : '';
        }

        if (array_key_exists($key, $empresa) && $empresa[$key] !== null) {
            return (string) $empresa[$key] === $value ? 'selected' : '';
        }

        return $default === $value ? 'selected' : '';
    }

    public static function checkedEdit(array $old, array $empresa, ?array $assinatura, string $key): string
    {
        if (array_key_exists($key, $old)) {
            return !empty($old[$key]) ? 'checked' : '';
        }

        if ($assinatura && array_key_exists($key, $assinatura)) {
            return !empty($assinatura[$key]) ? 'checked' : '';
        }

        if (array_key_exists($key, $empresa)) {
            return !empty($empresa[$key]) ? 'checked' : '';
        }

        return '';
    }

    public static function formatDateTimeLocalValue(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return (new DateTime($value))->format('Y-m-d\TH:i');
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function formatarDataView(?string $data, bool $comHora = false): string
    {
        if (empty($data)) {
            return '—';
        }

        try {
            return (new DateTime($data))->format($comHora ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (Throwable $e) {
            return '—';
        }
    }

    public static function badgeClasseView(string $status): string
    {
        return match ($status) {
            'ativa', 'ativo', 'ok' => 'badge badge-green',
            'trial' => 'badge badge-purple',
            'em_tolerancia', 'vencida', 'warning', 'pendente', 'processando' => 'badge badge-orange',
            'bloqueada', 'cancelada', 'suspensa', 'erro', 'inativo' => 'badge badge-red',
            default => 'badge',
        };
    }

    public static function percentualConsumo(int $usado, int $limite): int
    {
        if ($limite <= 0) {
            return 0;
        }

        return min(100, (int) round(($usado / $limite) * 100));
    }
}
