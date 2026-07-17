<?php
require_once __DIR__ . '/env.php';











define('IMAP_ENABLED',  env('IMAP_ENABLED', true));
define('IMAP_HOST',     env('IMAP_HOST', 'imap.gmail.com'));
define('IMAP_PORT',     (int)env('IMAP_PORT', 993));
define('IMAP_ENCRYPTION', env('IMAP_ENCRYPTION', '/imap/ssl'));
define('IMAP_USERNAME', env('IMAP_USERNAME', ''));
define('IMAP_PASSWORD', env('IMAP_PASSWORD', ''));
define('IMAP_FOLDER_INBOX',     env('IMAP_FOLDER_INBOX', 'INBOX'));
define('IMAP_FOLDER_PROCESSED', env('IMAP_FOLDER_PROCESSED', 'Processed'));
