<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
requireRole('admin');

$msg = '';
$err = '';

$currentUser     = defined('SMTP_USERNAME')   ? SMTP_USERNAME   : '';
$currentFromName = defined('SMTP_FROM_NAME')  ? SMTP_FROM_NAME  : 'ICT Help Desk';
$currentHost     = defined('SMTP_HOST')       ? SMTP_HOST       : 'smtp.gmail.com';
$currentPort     = defined('SMTP_PORT')       ? SMTP_PORT       : 587;
$currentEnabled  = defined('MAIL_ENABLED')    ? MAIL_ENABLED    : true;

global $__pm_ok;
$phpMailerFound = $__pm_ok;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'test') {
        $to = trim(isset($_POST['test_email']) ? $_POST['test_email'] : $currentUser);
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $sent = sendMail(
                $to, 'Test Recipient',
                'ICT Help Desk — Email Test',
                emailTemplate('Email Test', '<p>This is a test email from your ICT Help Desk system.</p><p>If you received this, email delivery is working correctly.</p>')
            );
            if ($sent) {
                $method = $GLOBALS['_mail_method_used'];
                $label  = strpos($method, 'smtp') !== false ? 'SMTP (' . $method . ')' : 'PHP mail()';
                $msg    = 'Test email sent to ' . htmlspecialchars($to) . ' via ' . $label . '.';
            } else {
                $err = 'Failed to send. ' . ($GLOBALS['_mail_last_error'] ?: 'Check your SMTP settings and ensure your host allows outgoing email.');
            }
        } else {
            $err = 'Enter a valid email address.';
        }
    }

    if ($action === 'save') {
        $host     = trim(isset($_POST['smtp_host'])  ? $_POST['smtp_host']  : 'smtp.gmail.com');
        $port     = (int)(isset($_POST['smtp_port']) ? $_POST['smtp_port']  : 587);
        $user     = trim(isset($_POST['smtp_user'])  ? $_POST['smtp_user']  : '');
        $pass     = trim(isset($_POST['smtp_pass'])  ? $_POST['smtp_pass']  : '');
        $fromName = trim(isset($_POST['from_name'])  ? $_POST['from_name']  : 'ICT Help Desk');
        $enabled  = isset($_POST['mail_enabled']) ? 'true' : 'false';

        if ($user) {
            $finalPass = $pass ? $pass : (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');

            $updates = array(
                'MAIL_ENABLED'    => $enabled,
                'SMTP_HOST'       => $host,
                'SMTP_PORT'       => $port,
                'SMTP_USERNAME'   => $user,
                'SMTP_PASSWORD'   => $finalPass,
                'SMTP_FROM_EMAIL' => $user,
                'SMTP_FROM_NAME'  => $fromName,
                'MAIL_DEBUG'      => 'false',
            );

            $written = writeEnvValues(__DIR__ . '/.env', $updates);
            if ($written) {
                $msg             = 'Email settings saved to .env. Reload the page to confirm updated values.';
                $currentUser     = $user;
                $currentHost     = $host;
                $currentPort     = $port;
                $currentFromName = $fromName;
                $currentEnabled  = $enabled === 'true';
            } else {
                $err = 'Could not write to .env (check file permissions). You can also edit .env manually.';
            }
        } else {
            $err = 'SMTP username / email address is required.';
        }
    }
}

include 'header.php';
?>
<div class="page-header">
  <div class="page-title">Email Settings</div>
  <div class="page-sub">Configure SMTP for outgoing ticket notifications.</div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <div class="card">
    <div class="card-header"><span class="card-title">SMTP Configuration</span></div>
    <div class="card-body">
      <form method="POST">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="save">
        <div class="settings-section">
          <div class="settings-row">
            <div>
              <div style="font-weight:600;font-size:13.5px">Enable Email Notifications</div>
              <div style="font-size:12px;color:var(--col-muted)">Send emails for ticket events, SLA warnings, and escalations</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="mail_enabled" <?php echo $currentEnabled ? 'checked' : ''; ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label>SMTP Host</label>
          <input type="text" name="smtp_host" value="<?php echo clean($currentHost); ?>" maxlength="150">
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label>SMTP Port</label>
          <input type="number" name="smtp_port" value="<?php echo (int)$currentPort; ?>" min="1" max="65535">
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label>Gmail Address *</label>
          <input type="email" name="smtp_user" required value="<?php echo clean($currentUser); ?>" maxlength="150">
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label>App Password <span style="font-size:11px;color:var(--col-muted)">(leave blank to keep current)</span></label>
          <div class="pw-wrap">
            <input type="password" id="smtp_pass" name="smtp_pass" placeholder="16-character App Password" maxlength="64">
            <button type="button" class="pw-toggle" onclick="togglePw('smtp_pass',this)">&#128065;</button>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:18px">
          <label>Sender Name</label>
          <input type="text" name="from_name" value="<?php echo clean($currentFromName); ?>" maxlength="80">
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </form>
    </div>
  </div>

  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">Send Test Email</span></div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--col-muted);margin-bottom:16px">
          Send a test message to confirm email delivery is working. The system will try SMTP first, then fall back to PHP mail() automatically.
        </p>
        <form method="POST">
          <?php echo csrfField(); ?>
          <input type="hidden" name="action" value="test">
          <div class="form-group" style="margin-bottom:14px">
            <label>Send Test To</label>
            <input type="email" name="test_email" value="<?php echo clean($currentUser); ?>" required placeholder="recipient@example.com">
          </div>
          <button type="submit" class="btn btn-outline" style="width:100%;justify-content:center">Send Test Email</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">System Status</span></div>
      <div class="card-body" style="font-size:13px">
        <table style="width:100%;margin-bottom:16px">
          <tr>
            <td style="color:var(--col-muted);padding:5px 0">Status</td>
            <td><span class="badge <?php echo $currentEnabled ? 'badge-resolved' : 'badge-closed'; ?>"><?php echo $currentEnabled ? 'Enabled' : 'Disabled'; ?></span></td>
          </tr>
          <tr>
            <td style="color:var(--col-muted);padding:5px 0">Host</td>
            <td><strong><?php echo clean($currentHost); ?>:<?php echo (int)$currentPort; ?></strong></td>
          </tr>
          <tr>
            <td style="color:var(--col-muted);padding:5px 0">Username</td>
            <td><strong><?php echo clean($currentUser); ?></strong></td>
          </tr>
          <tr>
            <td style="color:var(--col-muted);padding:5px 0">Sender Name</td>
            <td><strong><?php echo clean($currentFromName); ?></strong></td>
          </tr>
          <tr>
            <td style="color:var(--col-muted);padding:5px 0">PHPMailer</td>
            <td>
              <?php if ($phpMailerFound): ?>
                <span class="badge badge-resolved">Found</span>
              <?php else: ?>
                <span class="badge badge-open">Not Found</span>
                <div style="font-size:11px;color:var(--col-muted);margin-top:4px">Upload PHPMailer.php, SMTP.php and Exception.php to your web root. System will use PHP mail() as fallback.</div>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td style="color:var(--col-muted);padding:5px 0">PHP mail()</td>
            <td><span class="badge <?php echo function_exists('mail') ? 'badge-resolved' : 'badge-open'; ?>"><?php echo function_exists('mail') ? 'Available' : 'Unavailable'; ?></span></td>
          </tr>
        </table>
        <div style="padding:12px;background:var(--col-surface);border-radius:6px;font-size:12px;color:var(--col-muted);line-height:1.8">
          <strong style="color:var(--col-text)">Gmail App Password required for SMTP.</strong><br>
          Enable 2-Step Verification, then generate an App Password at<br>
          <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--col-primary)">myaccount.google.com/apppasswords</a><br><br>
          <strong style="color:var(--col-text)">If your host blocks SMTP,</strong> emails will automatically fall back to PHP mail() — no extra configuration needed.
        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'footer.php';
