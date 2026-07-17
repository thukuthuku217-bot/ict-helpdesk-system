<?php



























require_once __DIR__ . '/config.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/imap_config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/escalation_engine.php';




if (!function_exists('notify')) {
    function notify($db, $userId, $title, $message, $link = '', $type = 'ticket') {
        $stmt = $db->prepare("INSERT INTO notifications (user_id,type,title,message,link) VALUES (?,?,?,?,?)");
        $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
        $stmt->execute();
    }
}
if (!function_exists('notifyRole')) {
    function notifyRole($db, $role, $title, $message, $link = '', $type = 'ticket') {
        $r   = $db->real_escape_string($role);
        $res = $db->query("SELECT id FROM users WHERE role='$r'");
        while ($row = $res->fetch_assoc()) notify($db, (int)$row['id'], $title, $message, $link, $type);
    }
}

$db = getDB();
$before = microtime(true);
runEscalationCheck($db);
$elapsed = round((microtime(true) - $before) * 1000);

$ts = date('Y-m-d H:i:s');
$logLine = "[$ts] Escalation check completed in {$elapsed}ms" . PHP_EOL;


if (php_sapi_name() === 'cli' || (defined('IMAP_ENABLED'))) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/escalation.log', $logLine, FILE_APPEND);
}

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    echo $logLine;
}
