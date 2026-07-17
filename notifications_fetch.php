<?php
require_once __DIR__ . '/auth.php';
requireLogin();
header('Content-Type: application/json');
$db  = getDB();
$uid = (int)currentUser()['id'];

$s = $db->prepare("SELECT id,type,title,message,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
$s->bind_param('i', $uid);
$s->execute();
$res   = $s->get_result();
$items = [];

while ($r = $res->fetch_assoc()) {
    $diff = time() - strtotime($r['created_at']);
    if      ($diff < 60)    $ago = $diff . 's ago';
    elseif  ($diff < 3600)  $ago = floor($diff / 60) . 'm ago';
    elseif  ($diff < 86400) $ago = floor($diff / 3600) . 'h ago';
    else                    $ago = floor($diff / 86400) . 'd ago';
    $items[] = [
        'id'       => (int)$r['id'],
        'type'     => $r['type'],
        'title'    => $r['title'],
        'message'  => $r['message'],
        'link'     => $r['link'],
        'is_read'  => (bool)$r['is_read'],
        'time_ago' => $ago,
    ];
}

echo json_encode(['unread' => unreadCount($db, $uid), 'items' => $items]);
