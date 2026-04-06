<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
$location = routeUrl('usuarios/edit');

if (is_string($query) && $query !== '') {
    $location .= '?' . $query;
}

header('Location: ' . $location, true, 301);
exit;
