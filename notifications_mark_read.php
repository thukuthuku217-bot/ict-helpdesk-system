<?php
require_once __DIR__ . '/auth.php';
requireLogin();
header('Content-Type: application/json');

if (strtolower($_SERVER['REQUEST_METHOD'] ?? '') !== 'post') {
    http_response_code(405);
    echo json_encode(array('error' => 'Method not allowed'));
    exit;
}

$xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$token_ok = isset($_POST['csrf_token']) && hash_equals(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '', $_POST['csrf_token']);

if (!$xhr && !$token_ok) {
    http_response_code(403);
    echo json_encode(array('error' => 'Forbidden'));
    exit;
}

$db  = getDB();
$uid = (int)currentUser()['id'];
$id  = (int)(isset($_POST['id'])  ? $_POST['id']  : 0);
$all = isset($_POST['all']);

if ($all) {
    $s = $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $s->bind_param('i', $uid);
    $s->execute();
} elseif ($id) {
    $s = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $s->bind_param('ii', $id, $uid);
    $s->execute();
}

echo json_encode(array('ok' => true, 'unread' => unreadCount($db, $uid)));
