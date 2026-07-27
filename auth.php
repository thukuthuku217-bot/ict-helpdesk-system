<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function redirect($url) { header("Location: $url"); exit; }
function isLoggedIn()   { return isset($_SESSION['user_id']); }

function sendSecurityHeaders() {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; font-src https://fonts.gstatic.com; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'");
}

function enforceHttps() {
    if (!defined('FORCE_HTTPS') || !FORCE_HTTPS) return;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL']   === 'on')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    if (!$isHttps) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken() {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!$token || !hash_equals(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '', $token)) {
        http_response_code(403);
        die('Invalid or missing security token. Please go back and try again.');
    }
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

function requireLogin() {
    enforceHttps();
    sendSecurityHeaders();
    if (!isLoggedIn()) redirect('login.php');
    enforceSessionTimeout();
}

function requireRole() {
    $roles = func_get_args();
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles, true)) redirect('dashboard.php');
}

function currentUser() {
    return array(
        'id'      => isset($_SESSION['user_id'])    ? $_SESSION['user_id']    : 0,
        'name'    => isset($_SESSION['user_name'])  ? $_SESSION['user_name']  : '',
        'role'    => isset($_SESSION['user_role'])  ? $_SESSION['user_role']  : '',
        'email'   => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '',
        'dept_id' => isset($_SESSION['user_dept'])  ? $_SESSION['user_dept']  : null,
    );
}

function clean($val) {
    return htmlspecialchars(trim((string)$val), ENT_QUOTES, 'UTF-8');
}

function statusClass($s) {
    switch ($s) {
        case 'Open':        return 'badge-open';
        case 'In Progress': return 'badge-progress';
        case 'Escalated':   return 'badge-escalated';
        case 'Resolved':    return 'badge-resolved';
        default:            return 'badge-open';
    }
}

function priorityClass($p) {
    switch ($p) {
        case 'High':   return 'pri-high';
        case 'Medium': return 'pri-medium';
        case 'Low':    return 'pri-low';
        default:       return 'pri-low';
    }
}

function notify($db, $userId, $title, $message, $link = '', $type = 'ticket') {
    if (empty($userId) || (int)$userId <= 0) {
        error_log("notify() skipped: invalid user_id (" . var_export($userId, true) . ")");
        return false;
    }
    $stmt = $db->prepare("INSERT INTO notifications (user_id,type,title,message,link) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
    return $stmt->execute();
}

function notifyRole($db, $role, $title, $message, $link = '', $type = 'ticket') {
    $r   = $db->real_escape_string($role);
    $res = $db->query("SELECT id FROM users WHERE role='$r'");
    while ($row = $res->fetch_assoc()) notify($db, (int)$row['id'], $title, $message, $link, $type);
}

function unreadCount($db, $userId) {
    $s = $db->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0");
    $s->bind_param('i', $userId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? (int)$r['c'] : 0;
}

function getSlaRule($db, $priority) {
    $s = $db->prepare("SELECT response_minutes,resolution_minutes,warning_threshold_pct FROM sla_rules WHERE priority=?");
    $s->bind_param('s', $priority);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? $r : array('response_minutes' => 480, 'resolution_minutes' => 4320, 'warning_threshold_pct' => 80);
}

function isBusinessDay($ts) {
    $day  = (int)date('N', $ts);
    $days = array_map('intval', explode(',', BIZ_DAYS));
    return in_array($day, $days, true);
}

function businessWindowStart($ts) {
    return strtotime(date('Y-m-d', $ts) . ' ' . sprintf('%02d:00:00', BIZ_START_HOUR));
}

function businessWindowEnd($ts) {
    return strtotime(date('Y-m-d', $ts) . ' ' . sprintf('%02d:00:00', BIZ_END_HOUR));
}

function nextBusinessStart($ts) {
    $cursor = $ts;
    $guard  = 0;
    while ($guard++ < 30) {
        if (isBusinessDay($cursor)) {
            $wStart = businessWindowStart($cursor);
            $wEnd   = businessWindowEnd($cursor);
            if ($cursor < $wStart) return $wStart;
            if ($cursor < $wEnd)   return $cursor;
        }
        $cursor = strtotime('+1 day', strtotime(date('Y-m-d 00:00:00', $cursor)));
    }
    return $cursor;
}

function addBusinessMinutes($startTs, $minutes) {
    $remaining = (float)$minutes;
    $cursor    = nextBusinessStart($startTs);
    $guard     = 0;
    while ($remaining > 0 && $guard++ < 3650) {
        $wEnd  = businessWindowEnd($cursor);
        $avail = ($wEnd - $cursor) / 60;
        if ($remaining <= $avail) {
            $cursor += (int)round($remaining * 60);
            $remaining = 0;
        } else {
            $remaining -= $avail;
            $cursor = nextBusinessStart(strtotime('+1 day', strtotime(date('Y-m-d 00:00:00', $cursor))));
        }
    }
    return $cursor;
}

function businessMinutesBetween($startTs, $endTs) {
    if ($endTs <= $startTs) return 0;
    $cursor = nextBusinessStart($startTs);
    $total  = 0;
    $guard  = 0;
    while ($cursor < $endTs && $guard++ < 3650) {
        $wEnd = min(businessWindowEnd($cursor), $endTs);
        if ($wEnd > $cursor) $total += ($wEnd - $cursor) / 60;
        $cursor = nextBusinessStart(strtotime('+1 day', strtotime(date('Y-m-d 00:00:00', $cursor))));
    }
    return $total;
}

function calculateSlaDeadlines($db, $priority, $createdAt) {
    $rule = getSlaRule($db, $priority);
    $c    = strtotime($createdAt);
    return array(
        date('Y-m-d H:i:s', addBusinessMinutes($c, $rule['response_minutes'])),
        date('Y-m-d H:i:s', addBusinessMinutes($c, $rule['resolution_minutes'])),
    );
}

function slaState($due, $createdAt, $met, $warnPct = 80) {
    if ($met)  return array('label' => 'Met',        'class' => 'badge-resolved');
    if (!$due) return array('label' => 'Within SLA', 'class' => 'badge-resolved');
    $now = time();
    $d   = strtotime($due);
    $cr  = strtotime($createdAt);
    if ($now > $d) return array('label' => 'Overdue', 'class' => 'badge-open');
    $totalBizMin   = businessMinutesBetween($cr, $d);
    $elapsedBizMin = businessMinutesBetween($cr, $now);
    $pct = $totalBizMin > 0 ? ($elapsedBizMin / $totalBizMin * 100) : 100;
    $h   = round(($d - $now) / 3600, 1);
    if ($pct >= $warnPct) return array('label' => "Approaching Breach ({$h}h)", 'class' => 'badge-progress');
    return array('label' => "Within SLA ({$h}h left)", 'class' => 'badge-resolved');
}

function enforceSessionTimeout() {
    if (!isLoggedIn()) return;
    $t = isset($_SESSION['session_timeout_minutes']) ? $_SESSION['session_timeout_minutes'] : 480;
    $l = isset($_SESSION['last_activity'])           ? $_SESSION['last_activity']           : time();
    if (time() - $l > $t * 60) {
        session_destroy();
        redirect('login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}

function paginationLinks($page, $totalPages, $qs = '') {
    if ($totalPages <= 1) return '';
    $sep = $qs ? '&amp;' : '';
    $out = '<div class="pagination">';
    if ($page > 1)
        $out .= '<a href="?page=' . ($page - 1) . $sep . $qs . '" class="btn btn-outline btn-sm">&larr; Prev</a>';
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    if ($start > 1) $out .= '<span class="page-gap">&hellip;</span>';
    for ($i = $start; $i <= $end; $i++) {
        $cls  = $i === $page ? 'btn-primary' : 'btn-outline';
        $out .= '<a href="?page=' . $i . $sep . $qs . '" class="btn btn-sm ' . $cls . '">' . $i . '</a>';
    }
    if ($end < $totalPages) $out .= '<span class="page-gap">&hellip;</span>';
    if ($page < $totalPages)
        $out .= '<a href="?page=' . ($page + 1) . $sep . $qs . '" class="btn btn-outline btn-sm">Next &rarr;</a>';
    $out .= '</div>';
    return $out;
}

function publicTicketToken($ticketId, $ticketNo) {
    return substr(hash_hmac('sha256', $ticketId . '|' . $ticketNo, PUBLIC_TOKEN_SECRET), 0, 40);
}

function verifyPublicTicketToken($ticketId, $ticketNo, $token) {
    $expected = publicTicketToken($ticketId, $ticketNo);
    return hash_equals($expected, (string)$token);
}
