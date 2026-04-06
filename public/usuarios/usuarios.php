<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';

header('Location: ' . routeUrl('usuarios'), true, 301);
exit;
