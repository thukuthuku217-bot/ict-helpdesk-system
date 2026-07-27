<?php
require_once __DIR__ . '/env.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'helpdesk_db'));
define('FORCE_HTTPS', env('FORCE_HTTPS', true));

define('BIZ_START_HOUR', (int)env('BIZ_START_HOUR', 8));
define('BIZ_END_HOUR', (int)env('BIZ_END_HOUR', 17));
define('BIZ_DAYS', env('BIZ_DAYS', '1,2,3,4,5'));

define('APP_URL', rtrim(env('APP_URL', 'https://172.16.110.190'), '/'));
define('PUBLIC_TOKEN_SECRET', env('PUBLIC_TOKEN_SECRET', 'change-me'));

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) die('Database connection failed.');
    $conn->set_charset('utf8mb4');
    return $conn;
}
