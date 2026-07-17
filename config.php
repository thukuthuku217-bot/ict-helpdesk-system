<?php
require_once __DIR__ . '/env.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'helpdesk_db'));
define('FORCE_HTTPS', env('FORCE_HTTPS', true));

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) die('Database connection failed.');
    $conn->set_charset('utf8mb4');
    return $conn;
}
