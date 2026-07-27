<?php
// Canned responses admin page v1
require_once __DIR__ . '/auth.php';
requireRole('admin');
$db  = getDB();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    if (isset($_POST['do_delete'])) {
        $did = (int)$_POST['do_delete'];
        $db->query("DELETE FROM canned_responses WHERE id=$did");
        $msg = 'Canned response deleted.';
    } elseif (isset($_POST['save_response'])) {
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        $body  = trim(isset($_POST['body'])  ? $_POST['body']  : '');
        $rid   = (int)(isset($_POST['response_id']) ? $_POST['response_id'] : 0);
        $uid   = (int)currentUser()['id'];
        if ($title && $body) {
            if ($rid) {
                $s = $db->prepare("UPDATE canned_responses SET title=?,body=? WHERE id=?");
                $s->bind_param('ssi', $title, $body, $rid);
                $s->execute();
                $msg = 'Canned response updated.';
            } else {
                $s = $db->prepare("INSERT INTO canned_responses (title,body,created_by) VALUES (?,?,?)");
                $s->bind_param('ssi', $title, $body, $uid);
                $s->execute();
                $msg = 'Canned response created.';
            }
        } else {
            $err = 'Title and body are both required.';
        }
    }
}

$editResponse = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $editResponse = $db->query("SELECT * FROM canned_responses WHERE id=$eid")->fetch_assoc();
}

$responses = $db->query("SELECT * FROM canned_responses ORDER BY title");

include 'header.php';
?>
<div class="page-header">
  <div class="page-title">Canned Responses</div>
  <div class="page-sub">Reusable reply templates technicians can drop into ticket updates.</div>
</div>
<?php if ($msg): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:1fr 420px;gap:20px;align-items:start">
  <div class="card">
    <div class="card-header"><span class="card-title">All Templates</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Title</th><th>Preview</th><th></th></tr></thead>
        <tbody>
          <?php $any = false; while ($r = $responses->fetch_assoc()): $any = true; ?>
          <tr>
            <td><strong><?php echo clean($r['title']); ?></strong></td>
            <td style="font-size:12.5px;color:var(--col-muted)"><?php echo clean(mb_strimwidth($r['body'], 0, 80, '...')); ?></td>
            <td style="display:flex;gap:5px">
              <a href="?edit=<?php echo $r['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this template?')">
                <?php echo csrfField(); ?>
                <input type="hidden" name="do_delete" value="<?php echo $r['id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
          <?php endwhile; if (!$any): ?>
          <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--col-muted)">No canned responses yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <span class="card-title"><?php echo $editResponse ? 'Edit Template' : 'Add Template'; ?></span>
      <?php if ($editResponse): ?><a href="canned_responses.php" class="btn btn-outline btn-sm">Cancel</a><?php endif; ?>
    </div>
    <div class="card-body">
      <form method="POST">
        <?php echo csrfField(); ?>
        <?php if ($editResponse): ?><input type="hidden" name="response_id" value="<?php echo $editResponse['id']; ?>"><?php endif; ?>
        <input type="hidden" name="save_response" value="1">
        <div class="form-group" style="margin-bottom:12px">
          <label>Title *</label>
          <input type="text" name="title" required maxlength="150" value="<?php echo clean($editResponse ? $editResponse['title'] : ''); ?>">
        </div>
        <div class="form-group" style="margin-bottom:18px">
          <label>Body *</label>
          <textarea name="body" rows="8" required maxlength="2000"><?php echo clean($editResponse ? $editResponse['body'] : ''); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><?php echo $editResponse ? 'Save Changes' : 'Add Template'; ?></button>
      </form>
    </div>
  </div>
</div>
<?php include 'footer.php';