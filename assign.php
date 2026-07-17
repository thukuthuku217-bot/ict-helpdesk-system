<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
$db     = getDB();
$id     = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$single = null;
if ($id) {
    $s = $db->prepare("SELECT t.*,s.full_name AS staff_name,tech.full_name AS tech_name,d.name AS dept_name FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by LEFT JOIN users tech ON tech.id=t.assigned_to LEFT JOIN departments d ON d.id=t.department_id WHERE t.id=? LIMIT 1");
    $s->bind_param('i', $id);
    $s->execute();
    $single = $s->get_result()->fetch_assoc();
}
$unassigned = $db->query("SELECT t.*,s.full_name AS staff_name,d.name AS dept_name FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by LEFT JOIN departments d ON d.id=t.department_id WHERE t.assigned_to IS NULL ORDER BY FIELD(t.priority,'High','Medium','Low'),t.created_at ASC");

$departments = array();
$dres = $db->query("SELECT id,name FROM departments ORDER BY name");
while ($d = $dres->fetch_assoc()) $departments[] = $d;




$usersByDept = array();
$ures = $db->query("SELECT id,full_name,role,department_id FROM users WHERE role IN ('technician','staff') AND department_id IS NOT NULL ORDER BY role,full_name");
while ($u = $ures->fetch_assoc()) $usersByDept[$u['department_id']][] = $u;

function assigneeOptions($users, $selected = null) {
    $out = '';
    foreach ($users as $usr) {
        $label = clean($usr['full_name']) . ' (' . ucfirst($usr['role']) . ')';
        $sel   = ((int)$selected === (int)$usr['id']) ? ' selected' : '';
        $out  .= '<option value="' . (int)$usr['id'] . '"' . $sel . '>' . $label . '</option>';
    }
    return $out;
}

include 'header.php';
?>
<div class="page-header">
  <div class="page-title">Ticket Assignment</div>
  <div class="page-sub">Set a department, then assign to a technician or department staff member.</div>
</div>
<?php if (isset($_GET['msg'])): ?><div class="alert alert-success"><?php echo clean($_GET['msg']); ?></div><?php endif; ?>

<?php if (!empty($single) && !$single['assigned_to']): ?>
<div class="card" style="max-width:540px;margin-bottom:24px">
  <div class="card-header">
    <span class="card-title">Assign: <?php echo clean($single['ticket_no']); ?></span>
    <a href="assign.php" class="btn btn-outline btn-sm">Clear</a>
  </div>
  <div class="card-body">
    <p style="margin-bottom:14px;font-size:13.5px"><strong>Subject:</strong> <?php echo clean($single['subject']); ?></p>

    <form method="POST" action="set_department.php" style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #eee">
      <?php echo csrfField(); ?>
      <input type="hidden" name="ticket_id" value="<?php echo $single['id']; ?>">
      <input type="hidden" name="redirect" value="assign.php?id=<?php echo $single['id']; ?>">
      <div class="form-group" style="margin-bottom:10px">
        <label>Department</label>
        <select name="department_id" required>
          <option value="">— select department —</option>
          <?php foreach ($departments as $d): ?>
          <option value="<?php echo $d['id']; ?>" <?php echo ((int)$single['department_id']===(int)$d['id'])?'selected':''; ?>><?php echo clean($d['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-outline btn-sm">Save Department</button>
    </form>

    <?php if ($single['department_id']): ?>
    <form method="POST" action="assign_action.php">
      <?php echo csrfField(); ?>
      <input type="hidden" name="ticket_id" value="<?php echo $single['id']; ?>">
      <input type="hidden" name="redirect" value="assign.php">
      <div class="form-group" style="margin-bottom:14px">
        <label>Assign To — <?php echo clean($single['dept_name']); ?></label>
        <select name="tech_id" required>
          <option value="">— select —</option>
          <?php echo assigneeOptions($usersByDept[$single['department_id']] ?? array()); ?>
        </select>
      </div>
      <?php if (empty($usersByDept[$single['department_id']])): ?>
      <p style="font-size:12px;color:var(--col-muted);margin-bottom:10px">No technician or staff is set up in this department yet.</p>
      <?php endif; ?>
      <button class="btn btn-primary">Save Assignment</button>
    </form>
    <?php else: ?>
    <p style="font-size:13px;color:var(--col-muted)">Set a department above to see who's eligible for assignment.</p>
    <?php endif; ?>
  </div>
</div>
<?php elseif (!empty($single) && $single['assigned_to']): ?>
<div class="alert alert-info">Ticket <?php echo clean($single['ticket_no']); ?> is already assigned to <?php echo clean($single['tech_name']); ?>.</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Unassigned Tickets</span>
    <span style="font-size:12px;color:var(--col-muted)"><?php echo $unassigned->num_rows; ?> pending</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Ticket #</th><th>Subject</th><th>Priority</th><th>Submitted By</th><th>Department</th><th>Date</th><th>Assign To</th><th></th></tr></thead>
      <tbody>
        <?php $any = false; while ($row = $unassigned->fetch_assoc()): $any = true;
          $deptUsers = !empty($row['department_id']) ? ($usersByDept[$row['department_id']] ?? array()) : array();
        ?>
        <tr>
          <td><strong><?php echo clean($row['ticket_no']); ?></strong></td>
          <td><?php echo clean($row['subject']); ?></td>
          <td><span class="badge <?php echo priorityClass($row['priority']); ?>"><?php echo $row['priority']; ?></span></td>
          <td><?php echo clean($row['staff_name'] ?? '—'); ?></td>
          <td>
            <form method="POST" action="set_department.php" onchange="this.submit()">
              <?php echo csrfField(); ?>
              <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
              <input type="hidden" name="redirect" value="assign.php">
              <select name="department_id" style="min-width:130px;padding:5px 8px;font-size:12.5px">
                <option value="">— none —</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?php echo $d['id']; ?>" <?php echo ((int)$row['department_id']===(int)$d['id'])?'selected':''; ?>><?php echo clean($d['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
          <td>
            <form method="POST" action="assign_action.php" style="display:flex;gap:6px">
              <?php echo csrfField(); ?>
              <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
              <input type="hidden" name="redirect" value="assign.php">
              <select name="tech_id" required style="min-width:160px;padding:5px 8px;font-size:12.5px" <?php echo empty($deptUsers) ? 'disabled' : ''; ?>>
                <option value="">— select —</option>
                <?php echo assigneeOptions($deptUsers); ?>
              </select>
              <button type="submit" class="btn btn-accent btn-sm" <?php echo empty($deptUsers) ? 'disabled title="Set a department with eligible staff first"' : ''; ?>>Assign</button>
            </form>
          </td>
          <td><a href="ticket_view.php?id=<?php echo $row['id']; ?>" class="btn btn-outline btn-sm">View</a></td>
        </tr>
        <?php endwhile; if (!$any): ?>
        <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--col-muted)">All tickets are assigned.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'footer.php';