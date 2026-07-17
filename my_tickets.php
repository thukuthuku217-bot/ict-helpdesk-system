<?php
require_once __DIR__ . '/auth.php';
requireRole('staff');
$db     = getDB();
$u      = currentUser();
$uid    = (int)$u['id'];
$filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = trim(isset($_GET['q']) ? $_GET['q'] : '');

$allowed_statuses = array('Open','In Progress','Escalated','Resolved');

$conditions = array('t.submitted_by = ?');
$params     = array($uid);
$types      = 'i';

if ($filter && in_array($filter, $allowed_statuses, true)) {
    $conditions[] = 't.status = ?';
    $params[]     = $filter;
    $types       .= 's';
}
if ($search) {
    $like         = '%' . $search . '%';
    $conditions[] = '(t.ticket_no LIKE ? OR t.subject LIKE ?)';
    $params[]     = $like;
    $params[]     = $like;
    $types       .= 'ss';
}

$where = implode(' AND ', $conditions);
$stmt  = $db->prepare("SELECT t.*,tech.full_name AS tech_name FROM tickets t LEFT JOIN users tech ON tech.id=t.assigned_to WHERE $where ORDER BY t.created_at DESC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tickets = $stmt->get_result();

include 'header.php';
?>
<div class="page-header">
  <div class="page-title">My Tickets</div>
  <div class="page-sub">All ICT issues you have submitted.</div>
</div>
<?php if (isset($_GET['created'])): ?>
  <div class="alert alert-success">Ticket submitted successfully. We will be in touch shortly.</div>
<?php endif; ?>
<?php if (isset($_GET['att_warn'])): ?>
  <div class="alert alert-info">&#9888; Attachment note: <?php echo clean($_GET['att_warn']); ?></div>
<?php endif; ?>
<div class="toolbar">
  <div class="toolbar-left">
    <?php foreach (array(''=>'All','Open'=>'Open','In Progress'=>'In Progress','Escalated'=>'Escalated','Resolved'=>'Resolved') as $v => $l): ?>
      <a href="?status=<?php echo urlencode($v); ?>&q=<?php echo urlencode($search); ?>" class="btn btn-sm <?php echo $filter === $v ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $l; ?></a>
    <?php endforeach; ?>
  </div>
  <form method="GET" style="display:flex;gap:8px">
    <input type="hidden" name="status" value="<?php echo clean($filter); ?>">
    <input type="text" name="q" class="search-input" placeholder="Search…" value="<?php echo clean($search); ?>">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
  </form>
</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Ticket #</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Assigned To</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php $any = false; while ($row = $tickets->fetch_assoc()): $any = true; ?>
        <tr>
          <td><strong><?php echo clean($row['ticket_no']); ?></strong></td>
          <td><?php echo clean($row['subject']); ?></td>
          <td><?php echo clean($row['category']); ?></td>
          <td><span class="badge <?php echo priorityClass($row['priority']); ?>"><?php echo $row['priority']; ?></span></td>
          <td><span class="badge <?php echo statusClass($row['status']); ?>"><?php echo $row['status']; ?></span></td>
          <td><?php echo clean($row['tech_name'] ?? 'Unassigned'); ?></td>
          <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
          <td><a href="ticket_view.php?id=<?php echo $row['id']; ?>" class="btn btn-outline btn-sm">View</a></td>
        </tr>
        <?php endwhile; if (!$any): ?>
        <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--col-muted)">No tickets found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'footer.php';
