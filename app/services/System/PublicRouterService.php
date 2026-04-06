<?php

class PublicRouterService
{
    private string $publicRoot;

    public function __construct(string $publicRoot)
    {
        $resolvedRoot = realpath($publicRoot);
        $this->publicRoot = $resolvedRoot !== false ? $resolvedRoot : $publicRoot;
    }

    public function resolve(string $path): ?string
    {
        $path = trim($path, '/');

        if ($path === '') {
            return $this->resolveEntry('index.php');
        }

        $segments = explode('/', $path);
        $hasInvalidSegment = preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/', $path) !== 1
            || in_array('.', $segments, true)
            || in_array('..', $segments, true);

        if ($hasInvalidSegment) {
            return null;
        }

        $candidates = [
            $path . '.php',
            $path . '/index.php',
        ];

        foreach ($candidates as $candidate) {
            $resolved = $this->resolveEntry($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveEntry(string $relativePath): ?string
    {
        $resolved = realpath($this->publicRoot . DIRECTORY_SEPARATOR . $relativePath);

        if ($resolved === false || !is_file($resolved)) {
            return null;
        }

        $prefix = $this->publicRoot . DIRECTORY_SEPARATOR;
        if (strncmp($resolved, $prefix, strlen($prefix)) !== 0) {
            return null;
        }

        return $resolved;
    }
}
