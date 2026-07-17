<?php
function runEscalationCheck($db) {
    $cfg = $db->query("SELECT setting_value FROM system_settings WHERE setting_key='escalation_enabled'")->fetch_assoc();
    if (!$cfg || $cfg['setting_value'] !== '1') return;

    $breached = $db->query("SELECT id,ticket_no,subject,priority,assigned_to FROM tickets WHERE status NOT IN ('Resolved','Escalated') AND sla_resolution_due IS NOT NULL AND sla_resolution_due<NOW()");
    while ($t = $breached->fetch_assoc()) {
        $tid = (int)$t['id'];
        $db->query("UPDATE tickets SET status='Escalated',escalated_at=NOW(),sla_breached=1 WHERE id=$tid");
        $note     = 'Automatically escalated: SLA resolution deadline breached.';
        $st       = 'Escalated';
        $adminRow = $db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
        $adminId  = $adminRow ? (int)$adminRow['id'] : 0;
        if ($adminId) {
            $ins = $db->prepare("INSERT INTO ticket_updates (ticket_id,user_id,note,status_to) VALUES (?,?,?,?)");
            $ins->bind_param('iiss', $tid, $adminId, $note, $st);
            $ins->execute();
        }
        notifyRole($db, 'admin', 'Ticket Escalated', $t['ticket_no'] . ': ' . $t['subject'] . ' (SLA breached)', "ticket_view.php?id=$tid", 'escalation');
        if (function_exists('notifyEscalation')) notifyEscalation($db, $tid);
    }

    $warnings = $db->query("SELECT t.id,t.ticket_no,t.subject,t.priority,t.created_at,t.sla_resolution_due,t.assigned_to,r.warning_threshold_pct FROM tickets t JOIN sla_rules r ON r.priority=t.priority WHERE t.status NOT IN ('Resolved','Escalated') AND t.sla_resolution_due IS NOT NULL AND t.sla_resolution_due>NOW() AND t.sla_warning_sent=0");
    while ($t = $warnings->fetch_assoc()) {
        $cr  = strtotime($t['created_at']);
        $due = strtotime($t['sla_resolution_due']);
        $now = time();
        $pct = ($due - $cr) > 0 ? (($now - $cr) / ($due - $cr) * 100) : 0;
        if ($pct >= (float)$t['warning_threshold_pct']) {
            $tid = (int)$t['id'];
            $db->query("UPDATE tickets SET sla_warning_sent=1 WHERE id=$tid");
            if ($t['assigned_to']) {
                notify($db, (int)$t['assigned_to'], 'SLA Warning', $t['ticket_no'] . ' is approaching its SLA deadline', "ticket_view.php?id=$tid", 'sla');
                if (function_exists('notifySlaWarning')) notifySlaWarning($db, $tid);
            }
        }
    }
}
