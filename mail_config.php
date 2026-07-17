<?php
require_once __DIR__ . '/env.php';





define('MAIL_ENABLED',    env('MAIL_ENABLED', true));
define('SMTP_HOST',       env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT',       (int)env('SMTP_PORT', 587));
define('SMTP_USERNAME',   env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD',   env('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', env('SMTP_USERNAME', '')));
define('SMTP_FROM_NAME',  env('SMTP_FROM_NAME', 'ICT Help Desk'));
define('MAIL_DEBUG',      env('MAIL_DEBUG', false));
define('BREVO_API_KEY',   env('BREVO_API_KEY', ''));
