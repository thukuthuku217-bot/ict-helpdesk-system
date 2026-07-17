<?php
$u      = currentUser();
$role   = $u['role'];
$self   = basename($_SERVER['PHP_SELF']);
$db2    = getDB();
$unread = unreadCount($db2, (int)$u['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
<title>ICT Help Desk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">ICT</div>
      <div>
        <div class="brand-title">ICT Help Desk</div>
        <div class="brand-sub">Support Ticket System</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="nav-item <?php echo $self==='dashboard.php'?'active':''; ?>"><span class="nav-icon">&#8862;</span> Dashboard</a>

      <?php if ($role==='staff'): ?>
        <a href="new_ticket.php" class="nav-item <?php echo $self==='new_ticket.php'?'active':''; ?>"><span class="nav-icon">&#65291;</span> New Ticket</a>
        <a href="my_tickets.php" class="nav-item <?php echo $self==='my_tickets.php'?'active':''; ?>"><span class="nav-icon">&#9776;</span> My Tickets</a>
      <?php endif; ?>

      <?php if ($role==='technician' || $role==='staff'): ?>
        <a href="assigned.php" class="nav-item <?php echo $self==='assigned.php'?'active':''; ?>"><span class="nav-icon">&#9776;</span> Assigned Tickets</a>
      <?php endif; ?>

      <?php if ($role==='admin'): ?>
        <div class="nav-section">Tickets</div>
        <a href="admin_tickets.php" class="nav-item <?php echo $self==='admin_tickets.php'?'active':''; ?>"><span class="nav-icon">&#9776;</span> All Tickets</a>
        <a href="assign.php"        class="nav-item <?php echo $self==='assign.php'?'active':''; ?>"><span class="nav-icon">&#8644;</span> Assignments</a>
        <div class="nav-section">Management</div>
        <a href="users.php"          class="nav-item <?php echo $self==='users.php'?'active':''; ?>"><span class="nav-icon">&#9823;</span> Users &amp; Departments</a>
        <a href="reports.php"        class="nav-item <?php echo $self==='reports.php'?'active':''; ?>"><span class="nav-icon">&#9638;</span> Reports</a>
        <a href="email_settings.php" class="nav-item <?php echo $self==='email_settings.php'?'active':''; ?>"><span class="nav-icon">&#9993;</span> Email Settings</a>
        <a href="settings.php"       class="nav-item <?php echo $self==='settings.php'?'active':''; ?>"><span class="nav-icon">&#9881;</span> Settings</a>
      <?php endif; ?>

      <div class="nav-section">Account</div>
      <a href="notifications.php" class="nav-item <?php echo $self==='notifications.php'?'active':''; ?>">
        <span class="nav-icon">&#128276;</span> Notifications
        <?php if ($unread > 0): ?><span class="notif-badge" style="position:relative;top:0;right:0;margin-left:6px"><?php echo $unread>9?'9+':$unread; ?></span><?php endif; ?>
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?php echo strtoupper(substr($u['name'],0,1)); ?></div>
        <div>
          <div class="user-name"><?php echo clean($u['name']); ?></div>
          <div class="user-role"><?php echo ucfirst($role); ?></div>
        </div>
      </div>
      <a href="logout.php" class="logout-btn">Sign out</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <button id="sidebar-toggle" class="mobile-menu-btn" type="button">&#9776;</button>
      <div style="display:flex;align-items:center;gap:12px">
        <a href="notifications.php" class="notif-bell" style="text-decoration:none;position:relative;display:flex;align-items:center;justify-content:center">
          &#128276;<?php if ($unread>0): ?><span class="notif-badge"><?php echo $unread>9?'9+':$unread; ?></span><?php endif; ?>
        </a>
      </div>
    </div>