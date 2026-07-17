<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
requireLogin();
$db = getDB();
$u  = currentUser();
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$stmt = $db->prepare("SELECT t.*,COALESCE(s.full_name,t.client_name) AS staff_name,COALESCE(s.email,t.client_email) AS staff_email,tech.full_name AS tech_name,d.name AS dept_name FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by LEFT JOIN users tech ON tech.id=t.assigned_to LEFT JOIN departments d ON d.id=t.department_id WHERE t.id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) redirect('dashboard.php');


if ($u['role'] === 'staff' && (int)$ticket['submitted_by'] !== (int)$u['id'] && (int)$ticket['assigned_to'] !== (int)$u['id']) redirect('dashboard.php');
if ($u['role'] === 'technician' && (int)$ticket['assigned_to']  !== (int)$u['id']) redirect('assigned.php');



$isAssignee = in_array($u['role'], array('technician', 'staff'), true) && (int)$ticket['assigned_to'] === (int)$u['id'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note']) && $isAssignee) {
    verifyCsrfToken();
    $note      = trim(isset($_POST['note'])   ? $_POST['note']   : '');
    $newStatus = trim(isset($_POST['status']) ? $_POST['status'] : '');
    $allowed   = array('Open','In Progress','Escalated','Resolved');
    $isRes     = ($newStatus === 'Resolved') ? 1 : 0;
    if ($note && in_array($newStatus, $allowed, true)) {
        $ins = $db->prepare("INSERT INTO ticket_updates (ticket_id,user_id,note,status_to,is_resolution_comment) VALUES (?,?,?,?,?)");
        $ins->bind_param('iissi', $id, $u['id'], $note, $newStatus, $isRes);
        $ins->execute();
        if ($isRes) {
            $upd = $db->prepare("UPDATE tickets SET status=?,resolved_at=NOW(),resolution_comment=? WHERE id=?");
            $upd->bind_param('ssi', $newStatus, $note, $id);
        } else {
            $upd = $db->prepare("UPDATE tickets SET status=? WHERE id=?");
            $upd->bind_param('si', $newStatus, $id);
        }
        $upd->execute();
        notifyStatusUpdated($db, $id, $newStatus, $note);
        notify($db, (int)$ticket['submitted_by'], 'Ticket Updated', $ticket['ticket_no'] . ': status set to ' . $newStatus, "ticket_view.php?id=$id");
        redirect("ticket_view.php?id=$id&updated=1");
    }
}

$updates     = $db->query("SELECT tu.*,u.full_name FROM ticket_updates tu JOIN users u ON u.id=tu.user_id WHERE tu.ticket_id=$id ORDER BY tu.created_at ASC");
$attachments = $db->query("SELECT ta.*,u.full_name FROM ticket_attachments ta JOIN users u ON u.id=ta.uploaded_by WHERE ta.ticket_id=$id ORDER BY ta.uploaded_at ASC");

if (isset($_GET['updated'])) $msg = 'Ticket updated successfully.';

$rule = getSlaRule($db, $ticket['priority']);
$sla  = slaState($ticket['sla_resolution_due'], $ticket['created_at'], $ticket['status'] === 'Resolved', $rule['warning_threshold_pct']);

include 'header.php';
?>
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between">
  <div>
    <div class="page-title"><?php echo clean($ticket['ticket_no']); ?></div>
    <div class="page-sub"><?php echo clean($ticket['subject']); ?></div>
  </div>
  <a href="javascript:history.back()" class="btn btn-outline btn-sm">&larr; Back</a>
</div>
<?php if ($msg): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px">
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><span class="card-title">Ticket Details</span></div>
      <div class="card-body">
        <div class="ticket-meta">
          <div class="meta-item"><div class="meta-label">Status</div><div class="meta-val"><span class="badge <?php echo statusClass($ticket['status']); ?>"><?php echo $ticket['status']; ?></span></div></div>
          <div class="meta-item"><div class="meta-label">Priority</div><div class="meta-val"><span class="badge <?php echo priorityClass($ticket['priority']); ?>"><?php echo $ticket['priority']; ?></span></div></div>
          <div class="meta-item"><div class="meta-label">Category</div><div class="meta-val"><?php echo clean($ticket['category']); ?></div></div>
          <div class="meta-item"><div class="meta-label">Department</div><div class="meta-val"><?php echo clean($ticket['dept_name'] ?? '—'); ?></div></div>
          <div class="meta-item"><div class="meta-label">Submitted By</div><div class="meta-val"><?php echo clean($ticket['staff_name']); ?><?php if ($ticket['source'] === 'email'): ?><br><span style="font-size:11px;color:var(--col-muted)"><?php echo clean($ticket['staff_email']); ?> · via email</span><?php endif; ?></div></div>
          <div class="meta-item"><div class="meta-label">Assigned To</div><div class="meta-val"><?php echo clean($ticket['tech_name'] ?? 'Unassigned'); ?></div></div>
          <div class="meta-item"><div class="meta-label">Opened</div><div class="meta-val"><?php echo date('d M Y H:i', strtotime($ticket['created_at'])); ?></div></div>
          <div class="meta-item"><div class="meta-label">SLA Status</div><div class="meta-val"><span class="badge <?php echo $sla['class']; ?>"><?php echo clean($sla['label']); ?></span></div></div>
          <?php if ($ticket['resolved_at']): ?>
          <div class="meta-item"><div class="meta-label">Resolved</div><div class="meta-val"><?php echo date('d M Y H:i', strtotime($ticket['resolved_at'])); ?></div></div>
          <?php endif; ?>
        </div>
        <div style="margin-top:14px">
          <div class="meta-label" style="margin-bottom:6px">Description</div>
          <p style="font-size:14px;line-height:1.65"><?php echo nl2br(clean($ticket['description'])); ?></p>
        </div>
        <?php if ($ticket['resolution_comment']): ?>
        <div style="margin-top:16px;background:#D6F5E8;border-radius:8px;padding:14px">
          <div class="meta-label" style="margin-bottom:6px;color:#1A7A4A">Resolution Comment</div>
          <p style="font-size:14px;line-height:1.6;color:#145A32"><?php echo nl2br(clean($ticket['resolution_comment'])); ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($attachments->num_rows > 0): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><span class="card-title">Attachments</span></div>
      <div class="card-body">
        <?php while ($att = $attachments->fetch_assoc()):
          $att_ext  = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
          $is_image = in_array($att_ext, array('jpg','jpeg','png'));
          $icon     = $is_image ? '&#128247;' : '&#128206;';
          $size_kb  = round($att['file_size'] / 1024);
        ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--col-border)">
          <?php if ($is_image): ?>
          <div style="margin-bottom:10px">
            <img src="<?php echo clean($att['file_path']); ?>"
                 alt="<?php echo clean($att['file_name']); ?>"
                 style="max-width:100%;max-height:420px;border-radius:6px;border:1px solid var(--col-border);cursor:pointer;display:block"
                 onclick="window.open(this.src,'_blank')"
                 title="Click to open full size">
          </div>
          <?php endif; ?>
          <div style="display:flex;align-items:center;gap:10px">
            <a href="<?php echo clean($att['file_path']); ?>" target="_blank"
               style="font-size:13.5px;color:var(--col-primary);text-decoration:none;font-weight:500">
              <?php echo $icon; ?> <?php echo clean($att['file_name']); ?>
            </a>
            <span style="font-size:11px;color:var(--col-muted)"><?php echo $size_kb; ?> KB &middot; <?php echo clean($att['full_name']); ?></span>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header"><span class="card-title">Activity / Updates</span></div>
      <div class="card-body">
        <?php $any = false; while ($upd = $updates->fetch_assoc()): $any = true; ?>
        <div class="timeline-item" style="margin-bottom:16px">
          <div class="tl-dot"></div>
          <div>
            <div class="tl-meta">
              <strong><?php echo clean($upd['full_name']); ?></strong> &mdash;
              <?php echo date('d M Y H:i', strtotime($upd['created_at'])); ?> &mdash;
              <span class="badge <?php echo statusClass($upd['status_to']); ?>"><?php echo $upd['status_to']; ?></span>
              <?php if ($upd['is_resolution_comment']): ?><span class="tag-pill">Resolution</span><?php endif; ?>
            </div>
            <div class="tl-note"><?php echo nl2br(clean($upd['note'])); ?></div>
          </div>
        </div>
        <?php endwhile; if (!$any): ?><p style="color:var(--col-muted);font-size:13px">No updates yet.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($u['role'] === 'admin' || $isAssignee): ?>
  <div>
    <?php if ($isAssignee): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Add Update</span></div>
      <div class="card-body">
        <form method="POST">
          <?php echo csrfField(); ?>
          <div class="form-group" style="margin-bottom:14px">
            <label>Change Status</label>
            <select name="status" required>
              <option value="">— select —</option>
              <?php foreach (array('Open','In Progress','Escalated','Resolved') as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $ticket['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:16px">
            <label>Progress / Resolution Note</label>
            <textarea name="note" rows="4" required maxlength="2000" placeholder="Describe progress. If marking Resolved, this becomes the resolution comment shown to staff…"></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Save Update</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($u['role'] === 'admin' && !$ticket['assigned_to']): ?>
    <div class="card" style="margin-top:16px">
      <div class="card-header"><span class="card-title">Assignment</span></div>
      <div class="card-body">
        <form method="POST" action="set_department.php" style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #eee">
          <?php echo csrfField(); ?>
          <input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
          <input type="hidden" name="redirect" value="ticket_view.php?id=<?php echo $id; ?>">
          <div class="form-group" style="margin-bottom:10px">
            <label>Department</label>
            <select name="department_id" required>
              <option value="">— select —</option>
              <?php $depts = $db->query("SELECT id,name FROM departments ORDER BY name"); while ($d = $depts->fetch_assoc()): ?>
              <option value="<?php echo $d['id']; ?>" <?php echo ((int)$ticket['department_id']===(int)$d['id'])?'selected':''; ?>><?php echo clean($d['name']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <button class="btn btn-outline btn-sm">Save Department</button>
        </form>

        <?php if ($ticket['department_id']): ?>
        <form method="POST" action="assign_action.php">
          <?php echo csrfField(); ?>
          <input type="hidden" name="ticket_id" value="<?php echo $id; ?>">
          <input type="hidden" name="redirect" value="ticket_view.php?id=<?php echo $id; ?>">
          <div class="form-group" style="margin-bottom:14px">
            <label>Assign To — <?php echo clean($ticket['dept_name']); ?></label>
            <select name="tech_id" required>
              <option value="">— select —</option>
              <?php
              $eligible = $db->prepare("SELECT id,full_name,role FROM users WHERE department_id=? AND role IN ('technician','staff') ORDER BY role,full_name");
              $eligible->bind_param('i', $ticket['department_id']);
              $eligible->execute();
              $eres = $eligible->get_result();
              while ($t = $eres->fetch_assoc()):
              ?>
              <option value="<?php echo $t['id']; ?>"><?php echo clean($t['full_name']); ?> (<?php echo ucfirst($t['role']); ?>)</option>
              <?php endwhile; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-outline" style="width:100%;justify-content:center">Assign</button>
        </form>
        <?php else: ?>
        <p style="font-size:12.5px;color:var(--col-muted)">Set a department to see eligible assignees.</p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include 'footer.php';