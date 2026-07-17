<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
$db     = getDB();
$filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = trim(isset($_GET['q']) ? $_GET['q'] : '');
$page   = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));

$allowed_statuses = array('Open','In Progress','Escalated','Resolved');

$conditions = array();
$params     = array();
$types      = '';

if ($filter && in_array($filter, $allowed_statuses, true)) {
    $conditions[] = 't.status = ?';
    $params[]     = $filter;
    $types       .= 's';
}
if ($search) {
    $like         = '%' . $search . '%';
    $conditions[] = '(t.ticket_no LIKE ? OR t.subject LIKE ? OR s.full_name LIKE ? OR t.client_name LIKE ? OR t.client_email LIKE ?)';
    $params[]     = $like;
    $params[]     = $like;
    $params[]     = $like;
    $params[]     = $like;
    $params[]     = $like;
    $types       .= 'sssss';
}

$where    = $conditions ? implode(' AND ', $conditions) : '1=1';
$per_page = 20;

$count_sql  = "SELECT COUNT(*) AS cnt FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by WHERE $where";
$count_stmt = $db->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total      = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];
$total_pages = max(1, (int)ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$data_params = array_merge($params, array($per_page, $offset));
$data_types  = $types . 'ii';
$stmt = $db->prepare("SELECT t.*,COALESCE(s.full_name,t.client_name) AS staff_name,tech.full_name AS tech_name,d.name AS dept_name FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by LEFT JOIN users tech ON tech.id=t.assigned_to LEFT JOIN departments d ON d.id=t.department_id WHERE $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$tickets = $stmt->get_result();

$qs = 'status=' . urlencode($filter) . '&amp;q=' . urlencode($search);
include 'header.php';
?>
<div class="page-header">
  <div class="page-title">All Tickets</div>
  <div class="page-sub">Manage and track every ticket in the system.</div>
</div>
<?php if (isset($_GET['msg'])): ?><div class="alert alert-success"><?php echo clean($_GET['msg']); ?></div><?php endif; ?>
<div class="toolbar">
  <div class="toolbar-left">
    <?php foreach (array(''=>'All','Open'=>'Open','In Progress'=>'In Progress','Escalated'=>'Escalated','Resolved'=>'Resolved') as $v => $l): ?>
      <a href="?status=<?php echo urlencode($v); ?>&q=<?php echo urlencode($search); ?>" class="btn btn-sm <?php echo $filter===$v?'btn-primary':'btn-outline'; ?>"><?php echo $l; ?></a>
    <?php endforeach; ?>
  </div>
  <form method="GET" style="display:flex;gap:8px">
    <input type="hidden" name="status" value="<?php echo clean($filter); ?>">
    <input type="text" name="q" class="search-input" maxlength="100" placeholder="Search ticket #, subject, name…" value="<?php echo clean($search); ?>">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
  </form>
  <form method="POST" action="fetch_emails.php" id="checkEmailForm" style="display:inline">
    <?php echo csrfField(); ?>
    <button type="submit" id="checkEmailBtn" class="btn btn-accent btn-sm">🎫 Check New Tickets</button>
  </form>
</div>
<div id="emailCheckResult"></div>
<script>
document.getElementById('checkEmailForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var btn  = document.getElementById('checkEmailBtn');
  var out  = document.getElementById('emailCheckResult');
  var data = new FormData(this);
  btn.disabled = true;
  btn.textContent = 'Checking…';
  fetch('fetch_emails.php', { method: 'POST', body: data })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      btn.disabled = false;
      btn.textContent = '🎫 Check New Tickets';
      if (!res.ok) {
        out.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">' + res.error + '</div>';
        return;
      }
      out.innerHTML = '<div class="alert alert-success" style="margin-top:10px">'
        + res.created + ' new ticket(s) created from email'
        + (res.skipped ? ', ' + res.skipped + ' already processed' : '') + '.</div>';
      if (res.created > 0) setTimeout(function () { window.location.reload(); }, 1200);
    })
    .catch(function () {
      btn.disabled = false;
      btn.textContent = '🎫 Check New Tickets';
      out.innerHTML = '<div class="alert alert-danger" style="margin-top:10px">Could not reach fetch_emails.php.</div>';
    });
});
</script>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Ticket #</th><th>Subject</th><th>Priority</th><th>Status</th><th>SLA</th><th>Submitted By</th><th>Assigned To</th><th>Dept</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php $any = false; while ($row = $tickets->fetch_assoc()): $any = true;
          $rule = getSlaRule($db, $row['priority']);
          $sla  = slaState($row['sla_resolution_due'], $row['created_at'], $row['status']==='Resolved', $rule['warning_threshold_pct']);
        ?>
        <tr>
          <td><strong><?php echo clean($row['ticket_no']); ?></strong></td>
          <td><?php echo clean($row['subject']); ?></td>
          <td><span class="badge <?php echo priorityClass($row['priority']); ?>"><?php echo $row['priority']; ?></span></td>
          <td><span class="badge <?php echo statusClass($row['status']); ?>"><?php echo $row['status']; ?></span></td>
          <td><span class="badge <?php echo $sla['class']; ?>" style="font-size:10px"><?php echo clean($sla['label']); ?></span></td>
          <td><?php echo clean($row['staff_name'] ?? '—'); ?><?php if ($row['source'] === 'email'): ?> <span class="badge" style="font-size:9px;background:#eef;color:#335">via email</span><?php endif; ?></td>
          <td><?php echo $row['tech_name'] ? clean($row['tech_name']) : '<em style="color:var(--col-open)">Unassigned</em>'; ?></td>
          <td><?php echo clean($row['dept_name'] ?? '—'); ?></td>
          <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
          <td style="display:flex;gap:6px">
            <a href="ticket_view.php?id=<?php echo $row['id']; ?>" class="btn btn-outline btn-sm">View</a>
            <?php if (!$row['assigned_to']): ?><a href="assign.php?id=<?php echo $row['id']; ?>" class="btn btn-accent btn-sm">Assign</a><?php endif; ?>
          </td>
        </tr>
        <?php endwhile; if (!$any): ?>
        <tr><td colspan="10" style="text-align:center;padding:28px;color:var(--col-muted)">No tickets found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php echo paginationLinks($page, $total_pages, $qs); ?>
</div>
<?php include 'footer.php';

