<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
verifyCsrfToken();

$db          = getDB();
$ticket_id   = (int)(isset($_POST['ticket_id']) ? $_POST['ticket_id'] : 0);
$dept_raw    = isset($_POST['department_id']) ? $_POST['department_id'] : '';
$department_id = ($dept_raw === '') ? null : (int)$dept_raw;

$allowed_redirects = array('assign.php', 'admin_tickets.php', 'ticket_view.php');
$redir = isset($_POST['redirect']) ? $_POST['redirect'] : 'assign.php';
$base  = strtok($redir, '?');
if (!in_array($base, $allowed_redirects, true)) $redir = 'assign.php';

if ($ticket_id) {
    $upd = $db->prepare("UPDATE tickets SET department_id=? WHERE id=?");
    $upd->bind_param('ii', $department_id, $ticket_id);
    $upd->execute();
}

$sep = strpos($redir, '?') !== false ? '&' : '?';
redirect($redir . $sep . 'msg=' . urlencode('Department updated.'));