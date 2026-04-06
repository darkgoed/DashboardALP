<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$service = new PublicRouterService(__DIR__);
$resolved = $service->resolve((string) ($_GET['path'] ?? ''));

if ($resolved !== null) {
    require $resolved;
    exit;
}

http_response_code(404);
echo '404 Not Found';
