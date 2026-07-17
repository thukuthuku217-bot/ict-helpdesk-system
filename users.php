<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');
$db  = getDB();
$msg = '';
$err = '';
$tab = (isset($_GET['tab']) && $_GET['tab'] === 'departments') ? 'departments' : 'users';

if (isset($_GET['delete_user']) || isset($_GET['delete_dept'])) {
    redirect('users.php?tab=' . $tab);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if (isset($_POST['do_delete_user'])) {
        $did = (int)$_POST['do_delete_user'];
        if ($did !== (int)currentUser()['id']) {
            $db->query("DELETE FROM users WHERE id=$did");
            $msg = 'User deleted.';
        } else {
            $err = 'Cannot delete your own account.';
        }
        $tab = 'users';
    } elseif (isset($_POST['do_delete_dept'])) {
        $did = (int)$_POST['do_delete_dept'];
        $db->query("DELETE FROM departments WHERE id=$did");
        $msg = 'Department deleted.';
        $tab = 'departments';
    } elseif (isset($_POST['save_user'])) {
        $fname = trim(isset($_POST['full_name'])      ? $_POST['full_name']      : '');
        $email = trim(isset($_POST['email'])          ? $_POST['email']          : '');
        $role  = trim(isset($_POST['role'])           ? $_POST['role']           : '');
        $dept  = (int)(isset($_POST['department_id']) ? $_POST['department_id']  : 0); $dept = $dept ?: null;
        $phone = trim(isset($_POST['phone'])          ? $_POST['phone']          : '');
        $pw    = trim(isset($_POST['password'])       ? $_POST['password']       : '');
        $uid   = (int)(isset($_POST['user_id'])       ? $_POST['user_id']        : 0);
        $tab   = 'users';
        $allowed_roles = array('admin','technician','staff');
        if ($fname && $email && in_array($role, $allowed_roles, true)) {
            if ($uid) {
                try {
                    if ($pw) {
                        $hash = password_hash($pw, PASSWORD_DEFAULT);
                        $s = $db->prepare("UPDATE users SET full_name=?,email=?,role=?,department_id=?,phone=?,password=? WHERE id=?");
                        $s->bind_param('ssssssi', $fname, $email, $role, $dept, $phone, $hash, $uid);
                    } else {
                        $s = $db->prepare("UPDATE users SET full_name=?,email=?,role=?,department_id=?,phone=? WHERE id=?");
                        $s->bind_param('sssssi', $fname, $email, $role, $dept, $phone, $uid);
                    }
                    $s->execute();
                    $msg = 'User updated.';
                } catch (mysqli_sql_exception $e) {
                    $err = 'That email address is already in use by another account.';
                }
            } else {
                if (!$pw) $err = 'Password required for new users.';
                else {
                    try {
                        $hash = password_hash($pw, PASSWORD_DEFAULT);
                        $s    = $db->prepare("INSERT INTO users (full_name,email,password,role,department_id,phone) VALUES (?,?,?,?,?,?)");
                        $s->bind_param('ssssis', $fname, $email, $hash, $role, $dept, $phone);
                        $s->execute();
                        $msg = 'User created.';
                    } catch (mysqli_sql_exception $e) {
                        $err = 'A user with that email address already exists.';
                    }
                }
            }
        } else $err = 'Fill in all required fields.';
    } elseif (isset($_POST['save_dept'])) {
        $name = trim(isset($_POST['dept_name']) ? $_POST['dept_name'] : '');
        $did  = (int)(isset($_POST['dept_id'])  ? $_POST['dept_id']  : 0);
        $tab  = 'departments';
        if ($name) {
            if ($did) {
                try {
                    $s = $db->prepare("UPDATE departments SET name=? WHERE id=?");
                    $s->bind_param('si', $name, $did);
                    $s->execute();
                    $msg = 'Department updated.';
                } catch (mysqli_sql_exception $e) {
                    $err = 'A department with that name already exists.';
                }
            } else {
                try {
                    $s = $db->prepare("INSERT INTO departments (name) VALUES (?)");
                    $s->bind_param('s', $name);
                    $s->execute();
                    $msg = 'Department created.';
                } catch (mysqli_sql_exception $e) {
                    $err = 'A department with that name already exists.';
                }
            }
        } else $err = 'Department name is required.';
    }
}

$editUser = null;
if (isset($_GET['edit_user'])) {
    $eid      = (int)$_GET['edit_user'];
    $editUser = $db->query("SELECT * FROM users WHERE id=$eid")->fetch_assoc();
    $tab      = 'users';
}
$editDept = null;
if (isset($_GET['edit_dept'])) {
    $eid      = (int)$_GET['edit_dept'];
    $editDept = $db->query("SELECT * FROM departments WHERE id=$eid")->fetch_assoc();
    $tab      = 'departments';
}

if (isset($_GET['msg'])) $msg = clean($_GET['msg']);

$users          = $db->query("SELECT u.*,d.name AS dept_name FROM users u LEFT JOIN departments d ON d.id=u.department_id ORDER BY u.role,u.full_name");
$depts          = $db->query("SELECT d.*,(SELECT COUNT(*) FROM users u WHERE u.department_id=d.id) AS uc,(SELECT COUNT(*) FROM tickets t WHERE t.department_id=d.id) AS tc FROM departments d ORDER BY d.name");
$deptsForSelect = $db->query("SELECT * FROM departments ORDER BY name");

include 'header.php';
?>
<div class="page-header">
  <div class="page-title">Users &amp; Departments</div>
  <div class="page-sub">Manage user accounts, roles, and departments.</div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo clean($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?php echo clean($err); ?></div><?php endif; ?>

<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--col-border)">
  <a href="?tab=users" style="padding:10px 20px;font-weight:600;font-size:13.5px;text-decoration:none;border-radius:6px 6px 0 0;<?php echo $tab==='users'?'background:var(--col-primary);color:#fff':'color:var(--col-muted)';?>">&#128101; Users</a>
  <a href="?tab=departments" style="padding:10px 20px;font-weight:600;font-size:13.5px;text-decoration:none;border-radius:6px 6px 0 0;<?php echo $tab==='departments'?'background:var(--col-primary);color:#fff':'color:var(--col-muted)';?>">&#127970; Departments</a>
</div>

<?php if ($tab === 'users'): ?>
<div style="display:grid;grid-template-columns:1fr 400px;gap:20px;align-items:start">
  <div class="card">
    <div class="card-header"><span class="card-title">All Users</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Last Login</th><th></th></tr></thead>
        <tbody>
          <?php while ($row = $users->fetch_assoc()): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="user-avatar" style="width:28px;height:28px;font-size:11px;background:var(--col-primary);color:#fff"><?php echo strtoupper(substr($row['full_name'],0,1)); ?></div>
                <?php echo clean($row['full_name']); ?>
              </div>
            </td>
            <td><?php echo clean($row['email']); ?></td>
            <td><span class="badge <?php echo $row['role']==='admin'?'badge-progress':($row['role']==='technician'?'badge-open':'badge-closed'); ?>"><?php echo ucfirst($row['role']); ?></span></td>
            <td><?php echo clean($row['dept_name'] ?? '—'); ?></td>
            <td style="font-size:11.5px;color:var(--col-muted)"><?php echo $row['last_login_at'] ? date('d M Y H:i',strtotime($row['last_login_at'])) : 'Never'; ?></td>
            <td style="display:flex;gap:5px">
              <a href="?tab=users&edit_user=<?php echo $row['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
              <?php if ($row['id'] !== (int)currentUser()['id']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?php echo clean($row['full_name']); ?>?')">
                <?php echo csrfField(); ?>
                <input type="hidden" name="do_delete_user" value="<?php echo $row['id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title"><?php echo $editUser ? 'Edit User' : 'Add New User'; ?></span>
      <?php if ($editUser): ?><a href="?tab=users" class="btn btn-outline btn-sm">Cancel</a><?php endif; ?>
    </div>
    <div class="card-body">
      <form method="POST">
        <?php echo csrfField(); ?>
        <?php if ($editUser): ?><input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>"><?php endif; ?>
        <input type="hidden" name="save_user" value="1">
        <div class="form-group" style="margin-bottom:12px"><label>Full Name *</label><input type="text" name="full_name" required maxlength="100" value="<?php echo clean($editUser ? $editUser['full_name'] : ''); ?>"></div>
        <div class="form-group" style="margin-bottom:12px"><label>Email *</label><input type="email" name="email" required maxlength="150" value="<?php echo clean($editUser ? $editUser['email'] : ''); ?>"></div>
        <div class="form-group" style="margin-bottom:12px">
          <label>Role *</label>
          <select name="role" required>
            <option value="">— select —</option>
            <?php foreach (array('admin','technician','staff') as $r): ?>
              <option value="<?php echo $r; ?>" <?php echo ($editUser && $editUser['role']===$r)?'selected':''; ?>><?php echo ucfirst($r); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label>Department</label>
          <select name="department_id">
            <option value="">— none —</option>
            <?php $deptsForSelect->data_seek(0); while ($d=$deptsForSelect->fetch_assoc()): ?>
              <option value="<?php echo $d['id']; ?>" <?php echo ($editUser && (int)$editUser['department_id']===(int)$d['id'])?'selected':''; ?>><?php echo clean($d['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:12px"><label>Phone</label><input type="text" name="phone" maxlength="30" value="<?php echo clean($editUser ? $editUser['phone'] : ''); ?>"></div>
        <div class="form-group" style="margin-bottom:18px">
          <label>Password <?php echo $editUser ? '(leave blank to keep)' : '*'; ?></label>
          <div class="pw-wrap">
            <input type="password" id="upw" name="password" <?php echo $editUser?'':'required'; ?> maxlength="128" placeholder="<?php echo $editUser?'Leave blank to keep':'Set password'; ?>">
            <button type="button" class="pw-toggle" onclick="togglePw('upw',this)">&#128065;</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><?php echo $editUser ? 'Save Changes' : 'Create User'; ?></button>
      </form>
    </div>
  </div>
</div>

<?php else: ?>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">
  <div class="card">
    <div class="card-header"><span class="card-title">All Departments</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Users</th><th>Tickets</th><th></th></tr></thead>
        <tbody>
          <?php $any=false; while ($d=$depts->fetch_assoc()): $any=true; ?>
          <tr>
            <td><strong><?php echo clean($d['name']); ?></strong></td>
            <td><?php echo (int)$d['uc']; ?></td>
            <td><?php echo (int)$d['tc']; ?></td>
            <td style="display:flex;gap:5px">
              <a href="?tab=departments&edit_dept=<?php echo $d['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?php echo clean($d['name']); ?>? Historical data is preserved.')">
                <?php echo csrfField(); ?>
                <input type="hidden" name="do_delete_dept" value="<?php echo $d['id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
          <?php endwhile; if (!$any): ?><tr><td colspan="4" style="text-align:center;padding:24px;color:var(--col-muted)">No departments yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title"><?php echo $editDept ? 'Edit Department' : 'Add Department'; ?></span>
      <?php if ($editDept): ?><a href="?tab=departments" class="btn btn-outline btn-sm">Cancel</a><?php endif; ?>
    </div>
    <div class="card-body">
      <form method="POST">
        <?php echo csrfField(); ?>
        <?php if ($editDept): ?><input type="hidden" name="dept_id" value="<?php echo $editDept['id']; ?>"><?php endif; ?>
        <input type="hidden" name="save_dept" value="1">
        <div class="form-group" style="margin-bottom:16px">
          <label>Department Name *</label>
          <input type="text" name="dept_name" required maxlength="100" value="<?php echo clean($editDept ? $editDept['name'] : ''); ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><?php echo $editDept ? 'Save Changes' : 'Add Department'; ?></button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php include 'footer.php';
