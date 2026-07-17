<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
requireRole('staff');
$db  = getDB();
$u   = currentUser();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $subject  = trim(isset($_POST['subject'])     ? $_POST['subject']     : '');
    $desc     = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $category = trim(isset($_POST['category'])    ? $_POST['category']    : '');
    $priority = trim(isset($_POST['priority'])    ? $_POST['priority']    : '');

    $allowed_categories = array('Hardware','Software','Network','Email','Printer','Other');
    $allowed_priorities = array('Low','Medium','High');

    if ($subject && $desc && in_array($category, $allowed_categories, true) && in_array($priority, $allowed_priorities, true)) {
        $uid  = (int)$u['id'];
        $dept = $u['dept_id'];

        $stmt = $db->prepare("INSERT INTO tickets (ticket_no,subject,description,category,priority,submitted_by,department_id) VALUES ('PENDING',?,?,?,?,?,?)");
        $stmt->bind_param('ssssii', $subject, $desc, $category, $priority, $uid, $dept);

        if ($stmt->execute()) {
            $newId = $db->insert_id;
            $tno   = sprintf('TKT-%s-%04d', date('Y'), $newId);

            $upd = $db->prepare("UPDATE tickets SET ticket_no=? WHERE id=?");
            $upd->bind_param('si', $tno, $newId);
            $upd->execute();

            list($rd, $sd) = calculateSlaDeadlines($db, $priority, date('Y-m-d H:i:s'));
            $sla = $db->prepare("UPDATE tickets SET sla_response_due=?,sla_resolution_due=? WHERE id=?");
            $sla->bind_param('ssi', $rd, $sd, $newId);
            $sla->execute();

            $att_warning = '';
            $f = isset($_FILES['attachment']) ? $_FILES['attachment'] : null;

            if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $att_warning = 'File upload failed (server error ' . (int)$f['error'] . ').';
                } elseif ($f['size'] > 5 * 1024 * 1024) {
                    $att_warning = 'File exceeds the 5 MB limit and was not attached.';
                } else {
                    $ext     = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    $allowed = array('jpg','jpeg','png','pdf','docx','xlsx','txt');
                    $allowed_mimes = array(
                        'image/jpeg',
                        'image/png',
                        'application/pdf',
                        'text/plain',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    );

                    $mime_ok = true;
                    if (function_exists('finfo_open')) {
                        $fi      = finfo_open(FILEINFO_MIME_TYPE);
                        $mime    = finfo_file($fi, $f['tmp_name']);
                        finfo_close($fi);
                        $mime_ok = in_array($mime, $allowed_mimes, true);
                    }

                    if (!in_array($ext, $allowed, true) || !$mime_ok) {
                        $att_warning = 'File type not permitted. Allowed: JPG, PNG, PDF, DOCX, XLSX, TXT.';
                    } else {
                        $dir = __DIR__ . '/uploads/tickets/';
                        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                            $att_warning = 'Upload folder could not be created. Create uploads/tickets/ on the server and make it writable.';
                        } else {
                            $ts   = time();
                            $safe = 'tkt_' . $newId . '_' . $ts . '.' . $ext;
                            $dest = $dir . $safe;
                            if (move_uploaded_file($f['tmp_name'], $dest)) {
                                $rel  = 'uploads/tickets/' . $safe;
                                $ins2 = $db->prepare("INSERT INTO ticket_attachments (ticket_id,uploaded_by,file_name,file_path,file_size) VALUES (?,?,?,?,?)");
                                $ins2->bind_param('iissi', $newId, $uid, $f['name'], $rel, $f['size']);
                                $ins2->execute();
                            } else {
                                $att_warning = 'File could not be saved. Ensure uploads/tickets/ exists and is writable on the server.';
                            }
                        }
                    }
                }
            }

            notifyTicketCreated($db, $newId);
            notifyRole($db, 'admin', 'New Ticket Submitted', "$tno: $subject", "ticket_view.php?id=$newId");

            $redir = 'my_tickets.php?created=1';
            if ($att_warning) $redir .= '&att_warn=' . urlencode($att_warning);
            redirect($redir);
        } else {
            $err = 'Could not create ticket. Please try again.';
        }
    } else {
        $err = 'Please fill in all required fields.';
    }
}
include 'header.php';
?>
<div class="page-header">
  <div class="page-title">New Ticket</div>
  <div class="page-sub">Describe your ICT issue and we will assign a technician.</div>
</div>
<?php if ($err): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>
<div class="card" style="max-width:680px">
  <div class="card-header"><span class="card-title">Issue Details</span></div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?php echo csrfField(); ?>
      <div class="form-grid">
        <div class="form-group span2">
          <label>Subject *</label>
          <input type="text" name="subject" required maxlength="200" placeholder="Short description"
                 value="<?php echo clean(isset($_POST['subject']) ? $_POST['subject'] : ''); ?>">
        </div>
        <div class="form-group">
          <label>Category *</label>
          <select name="category" required>
            <option value="">— select —</option>
            <?php foreach (array('Hardware','Software','Network','Email','Printer','Other') as $c): ?>
            <option value="<?php echo $c; ?>" <?php echo (isset($_POST['category']) && $_POST['category'] === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Priority *</label>
          <select name="priority" required>
            <option value="">— select —</option>
            <?php foreach (array('Low','Medium','High') as $p): ?>
            <option value="<?php echo $p; ?>" <?php echo (isset($_POST['priority']) && $_POST['priority'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group span2">
          <label>Full Description *</label>
          <textarea name="description" rows="6" required maxlength="5000"
                    placeholder="Describe the problem, when it started, any error messages, and troubleshooting steps already tried…"><?php echo clean(isset($_POST['description']) ? $_POST['description'] : ''); ?></textarea>
        </div>
        <div class="form-group span2">
          <label>Attachment <span style="font-size:11px;color:var(--col-muted)">(optional — JPG, PNG, PDF, DOCX, XLSX, TXT — max 5 MB)</span></label>
          <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.docx,.xlsx,.txt">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Submit Ticket</button>
        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php include 'footer.php';
