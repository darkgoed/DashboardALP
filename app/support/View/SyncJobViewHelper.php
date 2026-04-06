<?php

class SyncJobViewHelper
{
    public static function prettyJson($value): string
    {
        if (empty($value)) {
            return '-';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $decoded = json_decode((string) $value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            'concluido', 'success' => 'badge-green',
            'erro', 'error' => 'badge-red',
            'processando' => 'badge-yellow',
            'cancelado' => 'badge-muted',
            default => 'badge-blue',
        };
    }

    public static function formatDate(?string $value): string
    {
        if (!$value) {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
    }

    public static function canRequeue(array $job): bool
    {
        if (!in_array($job['status'] ?? '', ['erro', 'cancelado'], true)) {
            return false;
        }

        return !str_contains((string) ($job['mensagem'] ?? ''), 'Reenfileirado manualmente no job #');
    }
}
