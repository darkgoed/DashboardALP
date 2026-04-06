<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

Auth::start();
$redirect = Auth::check() ? routeUrl('dashboard') : routeUrl('login');

header('Location: ' . $redirect);
exit;
