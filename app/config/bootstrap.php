<?php

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/app/config/env.php';

$envPath = BASE_PATH . '/.env';

if (!file_exists($envPath)) {
    die('Arquivo .env não encontrado em: ' . $envPath);
}

Env::load($envPath);

date_default_timezone_set('America/Sao_Paulo');

if (Env::get('APP_DEBUG') === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}

session_name(Env::get('SESSION_NAME', 'alp_session'));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/app/config/database.php';

spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . '/app/models/' . $class . '.php',
        BASE_PATH . '/app/helpers/' . $class . '.php',
        BASE_PATH . '/app/services/' . $class . '.php',
        BASE_PATH . '/app/config/' . $class . '.php',
    ];

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});