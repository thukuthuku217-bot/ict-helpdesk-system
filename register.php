<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
enforceHttps();
sendSecurityHeaders();
if (isLoggedIn()) redirect('dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $fullName = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
    $email    = trim(isset($_POST['email'])     ? $_POST['email']     : '');
    $password = trim(isset($_POST['password'])  ? $_POST['password']  : '');
    $confirm  = trim(isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '');
    $deptId   = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : null;
    $phone    = trim(isset($_POST['phone'])     ? $_POST['phone']     : '');

    if (!$fullName || !$email || !$password || !$confirm) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db  = getDB();
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $error = 'An account with that email already exists. Please sign in instead.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'staff';
                $stmt = $db->prepare("INSERT INTO users (full_name, email, password, role, department_id, phone, session_timeout_minutes) VALUES (?,?,?,?,?,?,480)");
                $stmt->bind_param('sssssi', $fullName, $email, $hash, $role, $deptId, $phone);
                $stmt->execute();
                $newUserId = $db->insert_id;

                notifyRole($db, 'admin', 'New Account Created', "{$fullName} ({$email}) just created a staff account.", "users.php");
                notifyAccountCreated($db, $newUserId);

                $success = 'Your account has been created successfully. You can now sign in.';
            } catch (mysqli_sql_exception $e) {
                $error = 'An account with that email already exists. Please sign in instead.';
            }
        }
    }
}

$depts = null;
try {
    $db    = isset($db) ? $db : getDB();
    $depts = $db->query("SELECT id, name FROM departments ORDER BY name");
} catch (Exception $e) {
    $depts = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Create Account — ICT Help Desk</title>
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
    <div class="login-title">Create your account</div>
    <div class="login-sub">Sign up to submit and track your ICT support tickets.</div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo clean($success); ?></div>
      <a href="login.php" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">Go to Sign In</a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?php echo clean($error); ?></div><?php endif; ?>
      <form method="POST" action="">
        <?php echo csrfField(); ?>
        <div class="form-group" style="margin-bottom:14px">
          <label for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name" required placeholder="Jane Doe" maxlength="100"
                 value="<?php echo clean(isset($_POST['full_name']) ? $_POST['full_name'] : ''); ?>">
        </div>
        <div class="form-group" style="margin-bottom:14px">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" required placeholder="you@organization.com" maxlength="150"
                 value="<?php echo clean(isset($_POST['email']) ? $_POST['email'] : ''); ?>">
        </div>
        <?php if ($depts && $depts->num_rows > 0): ?>
        <div class="form-group" style="margin-bottom:14px">
          <label for="department_id">Department <span style="font-weight:400">(optional)</span></label>
          <select id="department_id" name="department_id">
            <option value="">— select department —</option>
            <?php while ($d = $depts->fetch_assoc()): ?>
              <option value="<?php echo (int)$d['id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : ''; ?>>
                <?php echo clean($d['name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group" style="margin-bottom:14px">
          <label for="phone">Phone Number <span style="font-weight:400">(optional)</span></label>
          <input type="text" id="phone" name="phone" placeholder="07XX XXX XXX" maxlength="30"
                 value="<?php echo clean(isset($_POST['phone']) ? $_POST['phone'] : ''); ?>">
        </div>
        <div class="form-group" style="margin-bottom:14px">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password" required placeholder="At least 8 characters" minlength="8">
            <button type="button" class="pw-toggle" onclick="togglePw('password',this)" aria-label="Show password">👁</button>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:22px">
          <label for="confirm_password">Confirm Password</label>
          <div class="pw-wrap">
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password" minlength="8">
            <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)" aria-label="Show password">👁</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">Create Account</button>
      </form>
      <p style="margin-top:20px;font-size:12px;color:#6B7A99;text-align:center">
        Already have an account? <a href="login.php" style="color:var(--col-primary);font-weight:600">Sign In</a>
      </p>
    <?php endif; ?>
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
