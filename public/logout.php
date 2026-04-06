<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . routeUrl('login'));
    exit;
}

if (!Csrf::isValid()) {
    Flash::error('Token CSRF invalido.');
    header('Location: ' . routeUrl('dashboard'));
    exit;
}

$result = (new LogoutActionService())->handle();
Flash::success($result['flash_success']);
header('Location: ' . $result['redirect']);
exit;
