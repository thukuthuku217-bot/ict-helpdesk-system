<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if (isset($_POST['save_settings'])) {
        $settings = array(
            'session_timeout_minutes' => max(5,  (int)(isset($_POST['session_timeout_minutes']) ? $_POST['session_timeout_minutes'] : 480)),
            'escalation_enabled'      => isset($_POST['escalation_enabled']) ? '1' : '0',
            'max_login_attempts'      => max(3,  (int)(isset($_POST['max_login_attempts'])      ? $_POST['max_login_attempts']      : 5)),
        );
        foreach ($settings as $k => $v) {
            $s = $db->prepare("INSERT INTO system_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
            $s->bind_param('sss', $k, $v, $v);
            $s->execute();
        }
        $msg = 'System settings saved.';
    }

    if (isset($_POST['save_sla'])) {
        foreach (array('High','Medium','Low') as $p) {
            $resp = max(1, (int)(isset($_POST["response_$p"])   ? $_POST["response_$p"]   : 0));
            $reso = max(1, (int)(isset($_POST["resolution_$p"]) ? $_POST["resolution_$p"] : 0));
            $warn = min(99, max(1, (int)(isset($_POST["warning_$p"]) ? $_POST["warning_$p"] : 80)));
            $s = $db->prepare("UPDATE sla_rules SET response_minutes=?,resolution_minutes=?,warning_threshold_pct=? WHERE priority=?");
            $s->bind_param('iiis', $resp, $reso, $warn, $p);
            $s->execute();
        }
        $msg = 'SLA rules updated.';
    }
}

$rows = $db->query("SELECT * FROM system_settings");
$cfg  = array();
while ($r = $rows->fetch_assoc()) $cfg[$r['setting_key']] = $r['setting_value'];

$slaRules = array();
$res = $db->query("SELECT * FROM sla_rules");
while ($r = $res->fetch_assoc()) $slaRules[$r['priority']] = $r;

$fails = $db->query("SELECT email,ip_address,created_at FROM login_attempts WHERE success=0 ORDER BY created_at DESC LIMIT 10");

function fmtMin($m) {
    $m = (int)$m;
    if ($m < 60)              return "$m min";
    if ($m % 60 === 0 && $m < 1440) return ($m / 60) . ' hr';
    if ($m % 1440 === 0)      return ($m / 1440) . ' days';
    return round($m / 60, 1) . ' hrs';
}

include 'header.php';
?>
<div class="page-header">
  <div class="page-title">Settings</div>
  <div class="page-sub">System configuration, SLA rules, and security policies.</div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">System Settings</span></div>
    <div class="card-body">
      <form method="POST">
        <?php echo csrfField(); ?>
        <input type="hidden" name="save_settings" value="1">
        <div class="settings-section">
          <div class="settings-row">
            <div>
              <div style="font-weight:600;font-size:13.5px">Automatic Escalation</div>
              <div style="font-size:12px;color:var(--col-muted)">Auto-escalate tickets that breach their SLA resolution deadline</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="escalation_enabled" <?php echo (isset($cfg['escalation_enabled']) && $cfg['escalation_enabled']==='1') ? 'checked' : ''; ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
        <div class="settings-section">
          <label style="margin-bottom:6px">Session Timeout (minutes)</label>
          <input type="number" name="session_timeout_minutes" min="5" max="1440" value="<?php echo clean(isset($cfg['session_timeout_minutes']) ? $cfg['session_timeout_minutes'] : '480'); ?>">
        </div>
        <div class="settings-section">
          <label style="margin-bottom:6px">Max Login Attempts (before 15-min lockout)</label>
          <input type="number" name="max_login_attempts" min="3" max="20" value="<?php echo clean(isset($cfg['max_login_attempts']) ? $cfg['max_login_attempts'] : '5'); ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:16px">Save Settings</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Recent Failed Logins</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Email</th><th>IP Address</th><th>Time</th></tr></thead>
        <tbody>
          <?php $any = false; while ($f = $fails->fetch_assoc()): $any = true; ?>
          <tr>
            <td><?php echo clean($f['email']); ?></td>
            <td style="font-size:11.5px;color:var(--col-muted)"><?php echo clean($f['ip_address']); ?></td>
            <td><?php echo date('d M Y H:i', strtotime($f['created_at'])); ?></td>
          </tr>
          <?php endwhile; if (!$any): ?>
          <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--col-muted)">No failed login attempts.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">SLA Rules</span>
    <span style="font-size:12px;color:var(--col-muted)">Response and resolution targets per priority level</span>
  </div>
  <div class="card-body">
    <form method="POST">
      <?php echo csrfField(); ?>
      <input type="hidden" name="save_sla" value="1">
      <table style="width:100%;margin-bottom:20px">
        <thead>
          <tr><th>Priority</th><th>Response Time (min)</th><th>Resolution Time (min)</th><th>Warning Threshold (%)</th><th>Human Readable</th></tr>
        </thead>
        <tbody>
          <?php foreach (array('High','Medium','Low') as $p):
            $r = isset($slaRules[$p]) ? $slaRules[$p] : array('response_minutes'=>60,'resolution_minutes'=>480,'warning_threshold_pct'=>80);
          ?>
          <tr>
            <td><span class="badge <?php echo priorityClass($p); ?>"><?php echo $p; ?></span></td>
            <td><input type="number" name="response_<?php echo $p; ?>" value="<?php echo (int)$r['response_minutes']; ?>" min="1" style="max-width:110px"></td>
            <td><input type="number" name="resolution_<?php echo $p; ?>" value="<?php echo (int)$r['resolution_minutes']; ?>" min="1" style="max-width:110px"></td>
            <td><input type="number" name="warning_<?php echo $p; ?>" value="<?php echo (int)$r['warning_threshold_pct']; ?>" min="1" max="99" style="max-width:80px"></td>
            <td style="font-size:12px;color:var(--col-muted)">Response: <?php echo fmtMin($r['response_minutes']); ?> &nbsp;/&nbsp; Resolve: <?php echo fmtMin($r['resolution_minutes']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button type="submit" class="btn btn-primary">Save SLA Rules</button>
    </form>
  </div>
</div>
<?php include 'footer.php';
