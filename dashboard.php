<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/escalation_engine.php';
requireLogin();
$db  = getDB();
$u   = currentUser();
$uid = (int)$u['id'];
runEscalationCheck($db);

if ($u['role'] === 'staff') {
    $stats  = $db->query("SELECT COUNT(*) AS total,SUM(status='Open') AS open,SUM(status='In Progress') AS inp,SUM(status='Resolved') AS res FROM tickets WHERE submitted_by=$uid OR assigned_to=$uid")->fetch_assoc();
    $assignedToMe = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE assigned_to=$uid")->fetch_assoc();
    $recent = $db->query("SELECT t.*,tech.full_name AS tech_name,s.full_name AS staff_name FROM tickets t LEFT JOIN users tech ON tech.id=t.assigned_to LEFT JOIN users s ON s.id=t.submitted_by WHERE t.submitted_by=$uid OR t.assigned_to=$uid ORDER BY t.created_at DESC LIMIT 8");
} elseif ($u['role'] === 'technician') {
    $stats  = $db->query("SELECT COUNT(*) AS total,SUM(status='Open') AS open,SUM(status='In Progress') AS inp,SUM(status='Escalated') AS esc,SUM(status='Resolved') AS res FROM tickets WHERE assigned_to=$uid")->fetch_assoc();
    $recent = $db->query("SELECT t.*,s.full_name AS staff_name FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by WHERE t.assigned_to=$uid ORDER BY t.created_at DESC LIMIT 8");
} else {
    $stats  = $db->query("SELECT COUNT(*) AS total,SUM(status='Open') AS open,SUM(status='In Progress') AS inp,SUM(status='Escalated') AS esc,SUM(status='Resolved') AS res FROM tickets")->fetch_assoc();
    $tusers = $db->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc();
    $tdepts = $db->query("SELECT COUNT(*) AS c FROM departments")->fetch_assoc();
    $recent = $db->query("SELECT t.*,s.full_name AS staff_name,tech.full_name AS tech_name FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by LEFT JOIN users tech ON tech.id=t.assigned_to ORDER BY t.created_at DESC LIMIT 8");
}
include 'header.php';
?>
<div class="page-header">
  <div class="page-title">Dashboard</div>
  <div class="page-sub">Welcome back, <?php echo clean($u['name']); ?> &mdash; <?php echo date('l, d F Y'); ?></div>
</div>

<?php if ($u['role'] === 'admin'): ?>
<div class="stats-grid">
  <div class="stat-card total"><div class="stat-label">Total Tickets</div><div class="stat-value"><?php echo (int)$stats['total']; ?></div></div>
  <div class="stat-card open"><div class="stat-label">Open</div><div class="stat-value"><?php echo (int)$stats['open']; ?></div></div>
  <div class="stat-card progress"><div class="stat-label">In Progress</div><div class="stat-value"><?php echo (int)$stats['inp']; ?></div></div>
  <div class="stat-card open"><div class="stat-label">Escalated</div><div class="stat-value"><?php echo (int)$stats['esc']; ?></div></div>
  <div class="stat-card resolved"><div class="stat-label">Resolved</div><div class="stat-value"><?php echo (int)$stats['res']; ?></div></div>
  <div class="stat-card total"><div class="stat-label">Total Users</div><div class="stat-value"><?php echo (int)$tusers['c']; ?></div></div>
  <div class="stat-card total"><div class="stat-label">Departments</div><div class="stat-value"><?php echo (int)$tdepts['c']; ?></div></div>
</div>
<?php elseif ($u['role'] === 'technician'): ?>
<div class="stats-grid">
  <div class="stat-card total"><div class="stat-label">Assigned</div><div class="stat-value"><?php echo (int)$stats['total']; ?></div></div>
  <div class="stat-card open"><div class="stat-label">Open</div><div class="stat-value"><?php echo (int)$stats['open']; ?></div></div>
  <div class="stat-card progress"><div class="stat-label">In Progress</div><div class="stat-value"><?php echo (int)$stats['inp']; ?></div></div>
  <div class="stat-card open"><div class="stat-label">Escalated</div><div class="stat-value"><?php echo (int)$stats['esc']; ?></div></div>
  <div class="stat-card resolved"><div class="stat-label">Resolved</div><div class="stat-value"><?php echo (int)$stats['res']; ?></div></div>
</div>
<?php else: ?>
<div class="stats-grid">
  <div class="stat-card total"><div class="stat-label">My Tickets</div><div class="stat-value"><?php echo (int)$stats['total']; ?></div></div>
  <div class="stat-card open"><div class="stat-label">Open</div><div class="stat-value"><?php echo (int)$stats['open']; ?></div></div>
  <div class="stat-card progress"><div class="stat-label">In Progress</div><div class="stat-value"><?php echo (int)$stats['inp']; ?></div></div>
  <div class="stat-card resolved"><div class="stat-label">Resolved</div><div class="stat-value"><?php echo (int)$stats['res']; ?></div></div>
  <div class="stat-card total"><div class="stat-label">Assigned to Me</div><div class="stat-value"><?php echo (int)$assignedToMe['c']; ?></div></div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Recent Tickets</span>
    <?php if ($u['role'] === 'staff'): ?>
      <a href="new_ticket.php" class="btn btn-accent btn-sm">+ New Ticket</a>
    <?php elseif ($u['role'] === 'admin'): ?>
      <a href="admin_tickets.php" class="btn btn-outline btn-sm">View All</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ticket #</th><th>Subject</th><th>Priority</th><th>Status</th>
          <th>Submitted By</th>
          <th>Date</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php $any = false; while ($row = $recent->fetch_assoc()): $any = true; ?>
        <tr>
          <td><strong><?php echo clean($row['ticket_no']); ?></strong></td>
          <td><?php echo clean($row['subject']); ?></td>
          <td><span class="badge <?php echo priorityClass($row['priority']); ?>"><?php echo $row['priority']; ?></span></td>
          <td><span class="badge <?php echo statusClass($row['status']); ?>"><?php echo $row['status']; ?></span></td>
          <td><?php echo clean($row['staff_name'] ?? '—'); ?></td>
          <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
          <td><a href="ticket_view.php?id=<?php echo $row['id']; ?>" class="btn btn-outline btn-sm">View</a></td>
        </tr>
        <?php endwhile; if (!$any): ?>
        <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--col-muted)">No tickets yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'footer.php';