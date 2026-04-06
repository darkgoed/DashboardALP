<?php

define('BASE_PATH', dirname(__DIR__, 2));

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

require_once BASE_PATH . '/app/config/env.php';

$envPath = BASE_PATH . '/.env';

if (!file_exists($envPath)) {
    throw new RuntimeException('Arquivo .env não encontrado em: ' . $envPath);
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

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');

$appUrl = (string) Env::get('APP_URL', '');
$appScheme = '';

if ($appUrl !== '') {
    $appScheme = (string) parse_url($appUrl, PHP_URL_SCHEME);
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || $appScheme === 'https';

$sameSite = trim((string) Env::get('SESSION_SAMESITE', 'Lax'));
$allowedSameSite = ['Lax', 'Strict', 'None'];
if (!in_array($sameSite, $allowedSameSite, true)) {
    $sameSite = 'Lax';
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => $sameSite,
]);

session_name(Env::get('SESSION_NAME', 'alp_session'));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/app/config/database.php';
require_once __DIR__ . '/../helpers/flash.php';
require_once __DIR__ . '/../helpers/uuid.php';
require_once __DIR__ . '/../helpers/url.php';

spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . '/app/models/' . $class . '.php',
        BASE_PATH . '/app/helpers/' . $class . '.php',
        BASE_PATH . '/app/services/' . $class . '.php',
        BASE_PATH . '/app/config/' . $class . '.php',
        BASE_PATH . '/app/support/' . $class . '.php',
    ];

    $nestedRoots = [
        BASE_PATH . '/app/services',
        BASE_PATH . '/app/support',
    ];

    foreach ($nestedRoots as $root) {
        foreach (glob($root . '/*/' . $class . '.php') ?: [] as $file) {
            $paths[] = $file;
        }
    }

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});


