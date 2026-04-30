<?php
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
    return;
}
define('BASE_URL', '/~u202202670/vaultgg');

if (!function_exists('getConnection')) {
    function getConnection() {
        $dbc = mysqli_connect('20.74.143.233', 'u202202670', 'asdASD123!', 'db202202670');
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            die('Connection failed');
        }
        return $dbc;
    }
}

define('ITEMS_PER_PAGE', 9);
define('SITE_URL', '20.74.143.233/~u202202670/vaultgg');