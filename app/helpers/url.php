<?php

function cleanPath(string $path): string
{
    $fragment = '';
    $query = '';

    if (str_contains($path, '#')) {
        [$path, $fragment] = explode('#', $path, 2);
        $fragment = '#' . $fragment;
    }

    if (str_contains($path, '?')) {
        [$path, $query] = explode('?', $path, 2);
        $query = '?' . $query;
    }

    if ($path !== '/' && str_ends_with($path, '.php')) {
        $path = substr($path, 0, -4);
    }

    return $path . $query . $fragment;
}

function appUrl(string $path = ''): string
{
    $base = rtrim((string) Env::get('APP_URL', ''), '/');
    $path = ltrim(cleanPath($path), '/');

    return $base . '/' . $path;
}

function routeUrl(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim(cleanPath($path), '/');
}
