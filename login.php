<?php
require_once __DIR__ . '/auth.php';
enforceHttps();
sendSecurityHeaders();
if (isLoggedIn()) redirect('dashboard.php');

$error   = '';
$timeout = isset($_GET['timeout']) ? 'You were logged out due to inactivity.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $email    = trim(isset($_POST['email'])    ? $_POST['email']    : '');
    $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
    $ip       = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    if ($email && $password) {
        $db  = getDB();
        $chk = $db->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE email=? AND success=0 AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
        $chk->bind_param('s', $email);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        if ($row && (int)$row['c'] >= 5) {
            $error = 'Too many failed attempts. Try again in 15 minutes.';
        } else {
            $stmt = $db->prepare("SELECT id,full_name,password,role,department_id,email,session_timeout_minutes FROM users WHERE email=? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $log  = $db->prepare("INSERT INTO login_attempts (email,ip_address,success) VALUES (?,?,?)");
            if ($user && password_verify($password, $user['password'])) {
                $s = 1; $log->bind_param('ssi', $email, $ip, $s); $log->execute();
                session_regenerate_id(true);
                $_SESSION['user_id']                 = $user['id'];
                $_SESSION['user_name']               = $user['full_name'];
                $_SESSION['user_role']               = $user['role'];
                $_SESSION['user_email']              = $user['email'];
                $_SESSION['user_dept']               = $user['department_id'];
                $_SESSION['session_timeout_minutes'] = $user['session_timeout_minutes'] ?: 480;
                $_SESSION['last_activity']           = time();
                $db->query("UPDATE users SET last_login_at=NOW() WHERE id=" . (int)$user['id']);
                redirect('dashboard.php');
            } else {
                $s = 0; $log->bind_param('ssi', $email, $ip, $s); $log->execute();
                $error = 'Invalid email or password.';
            }
        }
    } else {
        $error = 'Please fill in both fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sign In — ICT Help Desk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-logo-icon">ICT</div>
      <div>
        <div class="login-logo-text">ICT Help Desk</div>
        <div class="login-logo-sub">Support Ticket System</div>
      </div>
    </div>
    <div class="login-title">Sign in to your account</div>
    <div class="login-sub">Enter your credentials to continue.</div>
    <?php if ($timeout): ?><div class="alert alert-info"><?php echo clean($timeout); ?></div><?php endif; ?>
    <?php if ($error):  ?><div class="alert alert-error"><?php echo clean($error); ?></div><?php endif; ?>
    <form method="POST" action="">
      <?php echo csrfField(); ?>
      <div class="form-group" style="margin-bottom:14px">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required placeholder="you@organization.com" maxlength="150"
               value="<?php echo clean(isset($_POST['email']) ? $_POST['email'] : ''); ?>">
      </div>
      <div class="form-group" style="margin-bottom:22px">
        <label for="password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" required placeholder="••••••••" maxlength="128">
          <button type="button" class="pw-toggle" onclick="togglePw('password',this)" aria-label="Show password">👁</button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">Sign In</button>
    </form>
    <p style="margin-top:20px;font-size:12px;color:#6B7A99;text-align:center">
      Don't have an account? <a href="register.php" style="color:var(--col-primary);font-weight:600">Create one</a>
    </p>
    <p style="margin-top:8px;font-size:12px;color:#6B7A99;text-align:center">Forgot your password? Contact your administrator.</p>
  </div>
</div>
<script>
function togglePw(id, btn) {
    var f = document.getElementById(id);
    if (f.type === 'password') { f.type = 'text'; btn.textContent = '🙈'; }
    else { f.type = 'password'; btn.textContent = '👁'; }
}
</script>
</body>
</html>
