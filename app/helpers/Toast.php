class Toast
{
function alp_flash_js(): void
{
$flash = [
'success' => $_SESSION['flash_success'] ?? null,
'error' => $_SESSION['flash_error'] ?? null,
'warning' => $_SESSION['flash_warning'] ?? null,
'info' => $_SESSION['flash_info'] ?? null,
];

unset(
$_SESSION['flash_success'],
$_SESSION['flash_error'],
$_SESSION['flash_warning'],
$_SESSION['flash_info']
);

echo '
<script>';
    echo 'window.__ALP_FLASH__ = '.json_encode($flash, JSON_UNESCAPED_UNICODE). ';';
    echo '</script>';
}
}