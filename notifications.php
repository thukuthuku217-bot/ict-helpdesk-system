<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$db  = getDB();
$u   = currentUser();
$uid = (int)$u['id'];

$notifs = $db->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 50");
include 'header.php';
?>
<div class="page-header">
  <div class="page-title">Notifications</div>
  <div class="page-sub">Your notification history.</div>
</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Type</th><th>Title</th><th>Message</th><th>Time</th><th></th></tr></thead>
      <tbody>
        <?php $any = false; while ($n = $notifs->fetch_assoc()): $any = true; ?>
        <tr style="<?php echo $n['is_read'] ? '' : 'background-color: var(--col-bg-alt, rgba(0,123,255,0.03)); font-weight: 500;'; ?>">
          <td><span class="tag-pill"><?php echo clean($n['type']); ?></span></td>
          <td style="font-weight:500"><?php echo clean($n['title']); ?></td>
          <td><?php echo clean($n['message']); ?></td>
          <td style="font-size:11.5px;color:var(--col-muted)"><?php echo date('d M Y H:i', strtotime($n['created_at'])); ?></td>
          <td>
            <?php if ($n['link']): ?>
              <a href="<?php echo clean($n['link']); ?>" 
                 class="btn btn-outline btn-sm" 
                 onclick="dismissNotif(event, <?php echo $n['id']; ?>, '<?php echo clean($n['link']); ?>')">
                 View
              </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; if (!$any): ?>
        <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--col-muted)">No notifications yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function dismissNotif(e, id, targetUrl) {
    e.preventDefault(); // Stop normal navigation temporarily
    
    const formData = new URLSearchParams();
    formData.append('id', id);

    fetch('notifications_mark_read.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .finally(() => {
        // Always redirect the user even if the network request lags
        window.location.href = targetUrl;
    });
}
</script>

<?php include 'footer.php';