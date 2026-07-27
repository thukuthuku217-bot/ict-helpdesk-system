<?php
// Bulk ticket action handler v1
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
requireRole('admin');
verifyCsrfToken();

$db = getDB();
$ticketIds = isset($_POST['ticket_ids']) && is_array($_POST['ticket_ids']) ? array_map('intval', $_POST['ticket_ids']) : array();
$ticketIds = array_filter($ticketIds, function ($v) { return $v > 0; });

$qsStatus = isset($_POST['qs_status']) ? $_POST['qs_status'] : '';
$qsQ      = isset($_POST['qs_q'])      ? $_POST['qs_q']      : '';
$qsPage   = isset($_POST['qs_page'])   ? (int)$_POST['qs_page'] : 1;
$backTo   = 'admin_tickets.php?status=' . urlencode($qsStatus) . '&q=' . urlencode($qsQ) . '&page=' . $qsPage;

if (empty($ticketIds)) {
    redirect($backTo . '&msg=' . urlencode('No tickets were selected.'));
}

$action = isset($_POST['bulk_action']) ? $_POST['bulk_action'] : '';
$uid    = (int)currentUser()['id'];
$count  = 0;

if ($action === 'status') {
    $newStatus = isset($_POST['bulk_status']) ? $_POST['bulk_status'] : '';
    $allowed   = array('Open','In Progress','Escalated','Resolved');
    if (in_array($newStatus, $allowed, true)) {
        foreach ($ticketIds as $tid) {
            $isRes = ($newStatus === 'Resolved') ? 1 : 0;
            if ($isRes) {
                $upd = $db->prepare("UPDATE tickets SET status=?,resolved_at=NOW() WHERE id=?");
            } else {
                $upd = $db->prepare("UPDATE tickets SET status=? WHERE id=?");
            }
            $upd->bind_param('si', $newStatus, $tid);
            $upd->execute();

            $note = 'Bulk status update by admin.';
            $ins = $db->prepare("INSERT INTO ticket_updates (ticket_id,user_id,note,status_to,is_resolution_comment) VALUES (?,?,?,?,?)");
            $ins->bind_param('iissi', $tid, $uid, $note, $newStatus, $isRes);
            $ins->execute();

            $trow = $db->query("SELECT ticket_no,subject,submitted_by FROM tickets WHERE id=$tid")->fetch_assoc();
            if ($trow && $trow['submitted_by']) {
                notify($db, (int)$trow['submitted_by'], 'Ticket Updated', $trow['ticket_no'] . ': status set to ' . $newStatus, "ticket_view.php?id=$tid");
            }
            if ($isRes && $trow) {
                notifyStatusUpdated($db, $tid, $newStatus, $note);
            }
            $count++;
        }
        redirect($backTo . '&msg=' . urlencode("$count ticket(s) updated to $newStatus."));
    } else {
        redirect($backTo . '&msg=' . urlencode('Select a valid status first.'));
    }
} elseif ($action === 'assign') {
    $techId = (int)(isset($_POST['bulk_assignee']) ? $_POST['bulk_assignee'] : 0);
    if ($techId > 0) {
        foreach ($ticketIds as $tid) {
            $chk = $db->prepare("SELECT assigned_to FROM tickets WHERE id=?");
            $chk->bind_param('i', $tid);
            $chk->execute();
            $ex = $chk->get_result()->fetch_assoc();
            $wasAssigned = $ex && $ex['assigned_to'];

            if ($wasAssigned) {
                $upd = $db->prepare("UPDATE tickets SET assigned_to=?,status='In Progress' WHERE id=?");
            } else {
                $upd = $db->prepare("UPDATE tickets SET assigned_to=?,status='In Progress',first_response_at=NOW() WHERE id=?");
            }
            $upd->bind_param('ii', $techId, $tid);
            $upd->execute();

            $note = 'Bulk assignment by admin.';
            $st   = 'In Progress';
            $ins  = $db->prepare("INSERT INTO ticket_updates (ticket_id,user_id,note,status_to) VALUES (?,?,?,?)");
            $ins->bind_param('iiss', $tid, $uid, $note, $st);
            $ins->execute();

            notifyTicketAssigned($db, $tid, $techId);
            $trow = $db->query("SELECT ticket_no,subject FROM tickets WHERE id=$tid")->fetch_assoc();
            if ($trow) notify($db, $techId, 'Ticket Assigned to You', $trow['ticket_no'] . ': ' . $trow['subject'], "ticket_view.php?id=$tid");
            $count++;
        }
        redirect($backTo . '&msg=' . urlencode("$count ticket(s) assigned."));
    } else {
        redirect($backTo . '&msg=' . urlencode('Select an assignee first.'));
    }
} else {
    redirect($backTo . '&msg=' . urlencode('No bulk action selected.'));
}