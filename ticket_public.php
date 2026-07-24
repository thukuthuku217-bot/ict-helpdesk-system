<?php
require_once __DIR__ . "/auth.php";

$db      = getDB();
$id      = (int)(isset($_GET["id"]) ? $_GET["id"] : 0);
$token   = isset($_GET["token"]) ? $_GET["token"] : "";
$ticket  = null;
$invalid = false;

if ($id && $token) {
    $stmt = $db->prepare("SELECT id,ticket_no,subject,description,category,priority,status,client_name,submitted_by,created_at,resolved_at,resolution_comment FROM tickets WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();

    if (!$ticket || !empty($ticket["submitted_by"]) || !verifyPublicTicketToken($id, $ticket["ticket_no"], $token)) {
        $ticket  = null;
        $invalid = true;
    }
} else {
    $invalid = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ticket Status — ICT Help Desk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-box" style="max-width:560px">
    <div class="login-logo">
      <div class="login-logo-icon">ICT</div>
      <div>
        <div class="login-logo-text">ICT Help Desk</div>
        <div class="login-logo-sub">Ticket Status</div>
      </div>
    </div>

    <?php if ($invalid || !$ticket): ?>
      <div class="alert alert-error">This link is invalid or has expired. Please contact the ICT Help Desk directly if you need assistance.</div>
    <?php else: ?>
      <div class="page-header" style="margin-bottom:16px">
        <div class="page-title"><?php echo clean($ticket["ticket_no"]); ?></div>
        <div class="page-sub"><?php echo clean($ticket["subject"]); ?></div>
      </div>

      <div class="ticket-meta" style="margin-bottom:16px">
        <div class="meta-item"><div class="meta-label">Status</div><div class="meta-val"><span class="badge <?php echo statusClass($ticket["status"]); ?>"><?php echo $ticket["status"]; ?></span></div></div>
        <div class="meta-item"><div class="meta-label">Priority</div><div class="meta-val"><span class="badge <?php echo priorityClass($ticket["priority"]); ?>"><?php echo $ticket["priority"]; ?></span></div></div>
        <div class="meta-item"><div class="meta-label">Category</div><div class="meta-val"><?php echo clean($ticket["category"]); ?></div></div>
        <div class="meta-item"><div class="meta-label">Submitted</div><div class="meta-val"><?php echo date("d M Y H:i", strtotime($ticket["created_at"])); ?></div></div>
        <?php if ($ticket["resolved_at"]): ?>
        <div class="meta-item"><div class="meta-label">Resolved</div><div class="meta-val"><?php echo date("d M Y H:i", strtotime($ticket["resolved_at"])); ?></div></div>
        <?php endif; ?>
      </div>

      <div style="margin-bottom:16px">
        <div class="meta-label" style="margin-bottom:6px">Description</div>
        <p style="font-size:14px;line-height:1.65"><?php echo nl2br(clean($ticket["description"])); ?></p>
      </div>

      <?php if ($ticket["resolution_comment"]): ?>
      <div style="background:#D6F5E8;border-radius:8px;padding:14px">
        <div class="meta-label" style="margin-bottom:6px;color:#1A7A4A">Resolution Comment</div>
        <p style="font-size:14px;line-height:1.6;color:#145A32"><?php echo nl2br(clean($ticket["resolution_comment"])); ?></p>
      </div>
      <?php endif; ?>

      <p style="margin-top:20px;font-size:12px;color:#6B7A99;text-align:center">
        Still experiencing issues? Reply to the original email or contact the ICT Help Desk to open a new ticket.
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
