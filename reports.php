<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
$db = getDB();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $db->query("SELECT t.ticket_no,t.subject,t.category,t.priority,t.status,s.full_name AS staff,tech.full_name AS tech,d.name AS dept,t.created_at,t.resolved_at FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by LEFT JOIN users tech ON tech.id=t.assigned_to LEFT JOIN departments d ON d.id=t.department_id ORDER BY t.created_at DESC");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tickets_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF"); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Ticket #', 'Subject', 'Category', 'Priority', 'Status', 'Submitted By', 'Assigned To', 'Department', 'Created', 'Resolved']);
    while ($r = $rows->fetch_assoc())
        fputcsv($out, [$r['ticket_no'], $r['subject'], $r['category'], $r['priority'], $r['status'], $r['staff'] ?? '', $r['tech'] ?? '', $r['dept'] ?? '', $r['created_at'], $r['resolved_at'] ?? '']);
    fclose($out);
    exit;
}

$summary = $db->query("SELECT COUNT(*) AS total,SUM(status='Open') AS open,SUM(status='In Progress') AS inp,SUM(status='Escalated') AS esc,SUM(status='Resolved') AS res,SUM(priority='High') AS high FROM tickets")->fetch_assoc();

$sla = $db->query("SELECT COUNT(*) AS total,SUM(resolved_at<=sla_resolution_due) AS met,SUM(resolved_at>sla_resolution_due) AS breached FROM tickets WHERE status='Resolved' AND resolved_at IS NOT NULL AND sla_resolution_due IS NOT NULL")->fetch_assoc();

$techPerf = $db->query("SELECT u.full_name,u.role,COUNT(t.id) AS total,SUM(t.status='Resolved') AS resolved,SUM(t.status='In Progress') AS inp,SUM(t.status='Escalated') AS esc,AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,t.created_at,t.resolved_at) END) AS avg_h FROM users u LEFT JOIN tickets t ON t.assigned_to=u.id WHERE u.role IN ('technician','staff') GROUP BY u.id,u.full_name,u.role HAVING u.role='technician' OR total > 0 ORDER BY resolved DESC");

$deptReport = $db->query("SELECT d.name,COUNT(t.id) AS total,SUM(t.status='Open') AS open,SUM(t.status='Resolved') AS resolved FROM departments d LEFT JOIN tickets t ON t.department_id=d.id GROUP BY d.id,d.name ORDER BY total DESC");

$catReport = $db->query("SELECT category,COUNT(*) AS total,SUM(status='Resolved') AS resolved FROM tickets GROUP BY category ORDER BY total DESC");

$monthly = $db->query("SELECT DATE_FORMAT(created_at,'%b %Y') AS month,COUNT(*) AS opened,SUM(status='Resolved') AS resolved FROM tickets WHERE created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY MIN(created_at) ASC");

$escReport = $db->query("SELECT t.ticket_no,t.subject,t.priority,t.escalated_at,s.full_name AS staff,tech.full_name AS tech FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by LEFT JOIN users tech ON tech.id=t.assigned_to WHERE t.status='Escalated' OR t.escalated_at IS NOT NULL ORDER BY t.escalated_at DESC LIMIT 20");

include 'header.php';
?>
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between">
  <div>
    <div class="page-title">Reports &amp; Analytics</div>
    <div class="page-sub">System-wide performance overview — <?php echo date('d M Y, H:i'); ?></div>
  </div>
  <div style="display:flex;gap:8px">
    <a href="?export=csv" class="btn btn-outline btn-sm">⬇ Export CSV</a>
    <button onclick="window.print()" class="btn btn-outline btn-sm">🖨 Print / PDF</button>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr))">
  <div class="stat-card total"><div class="stat-label">Total</div><div class="stat-value"><?php echo (int)$summary['total']; ?></div></div>
  <div class="stat-card open"><div class="stat-label">Open</div><div class="stat-value"><?php echo (int)$summary['open']; ?></div></div>
  <div class="stat-card progress"><div class="stat-label">In Progress</div><div class="stat-value"><?php echo (int)$summary['inp']; ?></div></div>
  <div class="stat-card open"><div class="stat-label">Escalated</div><div class="stat-value"><?php echo (int)$summary['esc']; ?></div></div>
  <div class="stat-card resolved"><div class="stat-label">Resolved</div><div class="stat-value"><?php echo (int)$summary['res']; ?></div></div>
  <div class="stat-card open"><div class="stat-label">High Priority</div><div class="stat-value"><?php echo (int)$summary['high']; ?></div></div>
</div>

<?php
$tot = (int)$sla['total'];
$met = (int)$sla['met'];
$br  = (int)$sla['breached'];
$pct = $tot ? round($met / $tot * 100) : 100;
$breachedOpen = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status!='Resolved' AND sla_resolution_due IS NOT NULL AND sla_resolution_due<NOW()")->fetch_assoc();
?>
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><span class="card-title">SLA Compliance</span></div>
  <div class="card-body">
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:0">
      <div class="stat-card resolved"><div class="stat-label">Compliance Rate</div><div class="stat-value"><?php echo $pct; ?>%</div></div>
      <div class="stat-card resolved"><div class="stat-label">SLA Met</div><div class="stat-value"><?php echo $met; ?></div></div>
      <div class="stat-card open"><div class="stat-label">SLA Breached</div><div class="stat-value"><?php echo $br; ?></div></div>
      <div class="stat-card open"><div class="stat-label">Currently Overdue</div><div class="stat-value"><?php echo (int)$breachedOpen['c']; ?></div></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">Assignee Performance</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Role</th><th>Assigned</th><th>Resolved</th><th>Escalated</th><th>Avg Hours</th></tr></thead>
        <tbody>
          <?php while ($r = $techPerf->fetch_assoc()): ?>
          <tr>
            <td><?php echo clean($r['full_name']); ?></td>
            <td><?php echo ucfirst($r['role']); ?></td>
            <td><?php echo (int)$r['total']; ?></td>
            <td><strong style="color:var(--col-resolved)"><?php echo (int)$r['resolved']; ?></strong></td>
            <td><?php echo (int)$r['esc']; ?></td>
            <td><?php echo $r['avg_h'] ? round($r['avg_h'], 1) . ' h' : '—'; ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Tickets by Department</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Department</th><th>Total</th><th>Open</th><th>Resolved</th><th>Resolution %</th></tr></thead>
        <tbody>
          <?php while ($r = $deptReport->fetch_assoc()):
            $p2 = $r['total'] ? round($r['resolved'] / $r['total'] * 100) : 0;
          ?>
          <tr>
            <td><?php echo clean($r['name']); ?></td>
            <td><?php echo (int)$r['total']; ?></td>
            <td><?php echo (int)$r['open']; ?></td>
            <td><?php echo (int)$r['resolved']; ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <div style="flex:1;height:6px;background:var(--col-border);border-radius:3px;overflow:hidden">
                  <div style="width:<?php echo $p2; ?>%;height:100%;background:var(--col-resolved);border-radius:3px"></div>
                </div>
                <?php echo $p2; ?>%
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">Tickets by Category</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Category</th><th>Total</th><th>Resolved</th></tr></thead>
        <tbody>
          <?php while ($r = $catReport->fetch_assoc()): ?>
          <tr>
            <td><?php echo clean($r['category']); ?></td>
            <td><?php echo (int)$r['total']; ?></td>
            <td><?php echo (int)$r['resolved']; ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Monthly Trend (6 Months)</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Month</th><th>Opened</th><th>Resolved</th></tr></thead>
        <tbody>
          <?php while ($r = $monthly->fetch_assoc()): ?>
          <tr>
            <td><?php echo clean($r['month']); ?></td>
            <td><?php echo (int)$r['opened']; ?></td>
            <td><strong style="color:var(--col-resolved)"><?php echo (int)$r['resolved']; ?></strong></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-header"><span class="card-title">Escalation Summary</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Ticket #</th><th>Subject</th><th>Priority</th><th>Submitted By</th><th>Assigned To</th><th>Escalated At</th></tr></thead>
      <tbody>
        <?php $any = false; while ($r = $escReport->fetch_assoc()): $any = true; ?>
        <tr>
          <td><strong><?php echo clean($r['ticket_no']); ?></strong></td>
          <td><?php echo clean($r['subject']); ?></td>
          <td><span class="badge <?php echo priorityClass($r['priority']); ?>"><?php echo $r['priority']; ?></span></td>
          <td><?php echo clean($r['staff'] ?? '—'); ?></td>
          <td><?php echo clean($r['tech'] ?? 'Unassigned'); ?></td>
          <td><?php echo $r['escalated_at'] ? date('d M Y H:i', strtotime($r['escalated_at'])) : '—'; ?></td>
        </tr>
        <?php endwhile; if (!$any): ?>
        <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--col-muted)">No escalations recorded.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>@media print{.sidebar,.btn,.topbar{display:none!important}.main-content{margin:0;padding:12px}}</style>
<?php include 'footer.php';
