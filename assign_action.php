<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
requireRole('admin');
verifyCsrfToken();

$db        = getDB();
$ticket_id = (int)(isset($_POST['ticket_id']) ? $_POST['ticket_id'] : 0);
$tech_id   = (int)(isset($_POST['tech_id'])   ? $_POST['tech_id']   : 0);

$allowed_redirects = array('assign.php', 'admin_tickets.php', 'ticket_view.php');
$redir = isset($_POST['redirect']) ? $_POST['redirect'] : 'assign.php';
$base  = strtok($redir, '?');
if (!in_array($base, $allowed_redirects, true)) $redir = 'assign.php';

if ($ticket_id && $tech_id) {
    $chk = $db->prepare("SELECT assigned_to FROM tickets WHERE id=?");
    $chk->bind_param('i', $ticket_id);
    $chk->execute();
    $ex = $chk->get_result()->fetch_assoc();
    if ($ex && $ex['assigned_to']) {
        $sep = strpos($redir, '?') !== false ? '&' : '?';
        redirect($redir . $sep . 'msg=' . urlencode('Already assigned.'));
    }
    $upd = $db->prepare("UPDATE tickets SET assigned_to=?,status='In Progress',first_response_at=NOW() WHERE id=?");
    $upd->bind_param('ii', $tech_id, $ticket_id);
    $upd->execute();

    $uid  = (int)currentUser()['id'];
    $note = 'Ticket assigned to technician.';
    $st   = 'In Progress';
    $ins  = $db->prepare("INSERT INTO ticket_updates (ticket_id,user_id,note,status_to) VALUES (?,?,?,?)");
    $ins->bind_param('iiss', $ticket_id, $uid, $note, $st);
    $ins->execute();

    notifyTicketAssigned($db, $ticket_id, $tech_id);
    $trow = $db->query("SELECT ticket_no,subject FROM tickets WHERE id=$ticket_id")->fetch_assoc();
    if ($trow) notify($db, $tech_id, 'Ticket Assigned to You', $trow['ticket_no'] . ': ' . $trow['subject'], "ticket_view.php?id=$ticket_id");
}
$sep = strpos($redir, '?') !== false ? '&' : '?';
redirect($redir . $sep . 'msg=' . urlencode('Assignment saved.'));
