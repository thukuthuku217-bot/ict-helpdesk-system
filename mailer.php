<?php
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/imap_config.php';








function replyToAddress() {
    if (defined('IMAP_ENABLED') && IMAP_ENABLED && defined('IMAP_USERNAME') && IMAP_USERNAME) {
        return IMAP_USERNAME;
    }
    return defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '';
}

$__pm_ok = false;
$__pm_locations = array(
    array(
        __DIR__ . '/PHPMailer/src/PHPMailer.php',
        __DIR__ . '/PHPMailer/src/SMTP.php',
        __DIR__ . '/PHPMailer/src/Exception.php',
    ),
    array(
        __DIR__ . '/PHPMailer/PHPMailer.php',
        __DIR__ . '/PHPMailer/SMTP.php',
        __DIR__ . '/PHPMailer/Exception.php',
    ),
    array(
        __DIR__ . '/PHPMailer.php',
        __DIR__ . '/SMTP.php',
        __DIR__ . '/Exception.php',
    ),
);
foreach ($__pm_locations as $__set) {
    if (file_exists($__set[0]) && file_exists($__set[1]) && file_exists($__set[2])) {
        require_once $__set[0];
        require_once $__set[1];
        require_once $__set[2];
        $__pm_ok = true;
        break;
    }
}

$GLOBALS['_mail_last_error']  = '';
$GLOBALS['_mail_method_used'] = '';

function _buildMailer($enc, $port) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = $enc;
    $mail->Port       = $port;
    $mail->Timeout    = 5;
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addReplyTo(replyToAddress(), SMTP_FROM_NAME);
    return $mail;
}

function _brevoSend($toEmail, $toName, $subject, $bodyHtml) {
    if (!defined('BREVO_API_KEY') || !BREVO_API_KEY) return false;
    if (!function_exists('curl_init')) {
        error_log('ICT HelpDesk: cURL not available for Brevo API');
        return false;
    }
    $payload = json_encode(array(
        'sender'      => array('name' => SMTP_FROM_NAME, 'email' => SMTP_FROM_EMAIL),
        'replyTo'     => array('name' => SMTP_FROM_NAME, 'email' => replyToAddress()),
        'to'          => array(array('email' => $toEmail, 'name' => $toName ?: $toEmail)),
        'subject'     => $subject,
        'htmlContent' => $bodyHtml,
    ));
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'Accept: application/json',
            'api-key: ' . BREVO_API_KEY,
        ),
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr) {
        error_log('ICT HelpDesk: Brevo cURL error: ' . $curlErr);
        return false;
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        $GLOBALS['_mail_method_used'] = 'brevo_api';
        return true;
    }
    error_log('ICT HelpDesk: Brevo API failed (' . $httpCode . '): ' . $response);
    $GLOBALS['_mail_last_error'] = 'Brevo API error (' . $httpCode . '). Check your API key in mail_config.php.';
    return false;
}

function _phpMailFallback($toEmail, $toName, $subject, $bodyHtml) {
    if (!function_exists('mail')) return false;
    $from    = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '';
    $name    = defined('SMTP_FROM_NAME')  ? SMTP_FROM_NAME  : 'ICT Help Desk';
    $encName = '=?UTF-8?B?' . base64_encode($name) . '?=';
    $encSubj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$encName} <{$from}>\r\n";
    $headers .= "Reply-To: " . replyToAddress() . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $to     = $toName ? ('=?UTF-8?B?' . base64_encode($toName) . "?= <{$toEmail}>") : $toEmail;
    $result = @mail($to, $encSubj, $bodyHtml, $headers);
    if ($result) {
        $GLOBALS['_mail_method_used'] = 'php_mail';
    } else {
        error_log("ICT HelpDesk: PHP mail() failed for {$toEmail}");
    }
    return (bool)$result;
}

function sendMail($toEmail, $toName, $subject, $bodyHtml) {
    global $__pm_ok;
    $GLOBALS['_mail_last_error']  = '';
    $GLOBALS['_mail_method_used'] = '';

    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        $GLOBALS['_mail_last_error'] = 'Email notifications are disabled.';
        return false;
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['_mail_last_error'] = "Invalid recipient address: {$toEmail}";
        return false;
    }

    if ($__pm_ok) {
        foreach (array(array('tls', 587), array('ssl', 465)) as $a) {
            try {
                $mail = _buildMailer($a[0], $a[1]);
                $mail->addAddress($toEmail, $toName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $bodyHtml;
                $mail->AltBody = strip_tags($bodyHtml);
                $mail->send();
                $GLOBALS['_mail_method_used'] = 'smtp_' . $a[0] . '_' . $a[1];
                return true;
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                $GLOBALS['_mail_last_error'] = '[' . $a[0] . ':' . $a[1] . '] ' . $e->getMessage();
                error_log('ICT HelpDesk SMTP [' . $a[0] . ':' . $a[1] . ']: ' . $e->getMessage());
            }
        }
    }

    if (_brevoSend($toEmail, $toName, $subject, $bodyHtml)) return true;

    if (_phpMailFallback($toEmail, $toName, $subject, $bodyHtml)) return true;

    if (!defined('BREVO_API_KEY') || !BREVO_API_KEY) {
        $GLOBALS['_mail_last_error'] = 'SMTP is blocked by your host. Add your Brevo API key to mail_config.php to enable email delivery.';
    }
    return false;
}

function emailTemplate($title, $body) {
    return "<div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto'>
      <div style='background:#1B3A6B;padding:20px 24px;border-radius:8px 8px 0 0'>
        <span style='color:#E8A020;font-weight:700;font-size:14px'>ICT HELP DESK</span>
      </div>
      <div style='background:#fff;padding:24px;border:1px solid #DDE3ED;border-radius:0 0 8px 8px'>
        <h2 style='color:#1B3A6B;font-size:18px;margin-top:0'>$title</h2>
        $body
        <p style='font-size:12px;color:#6B7A99;margin-top:24px;border-top:1px solid #DDE3ED;padding-top:12px'>
          Automated message from ICT Help Desk. Replying to this email opens a new support ticket.
        </p>
      </div>
    </div>";
}

function notifyTicketCreated($db, $ticketId) {
    $s = $db->prepare("SELECT t.ticket_no,t.subject,t.priority,t.source,COALESCE(u.full_name,t.client_name) AS sname FROM tickets t LEFT JOIN users u ON u.id=t.submitted_by WHERE t.id=?");
    $s->bind_param('i', $ticketId);
    $s->execute();
    $t = $s->get_result()->fetch_assoc();
    if (!$t) return;
    $admins = $db->query("SELECT full_name,email FROM users WHERE role='admin' AND email != ''");
    $link   = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . "/ticket_view.php?id={$ticketId}";
    $body   = "<p>A new ticket has been submitted.</p>
               <p><strong>Ticket:</strong> " . htmlspecialchars($t['ticket_no']) . "<br>
               <strong>Subject:</strong> " . htmlspecialchars($t['subject']) . "<br>
               <strong>Priority:</strong> " . htmlspecialchars($t['priority']) . "<br>
               <strong>Submitted by:</strong> " . htmlspecialchars($t['sname']) . ($t['source'] === 'email' ? ' (via email)' : '') . "</p>
               <p><a href='" . htmlspecialchars($link) . "' style='display:inline-block;padding:10px 20px;background:#1B3A6B;color:#fff;border-radius:6px;text-decoration:none;font-size:13px'>View Ticket</a></p>";
    while ($a = $admins->fetch_assoc()) {
        if (!empty($a['email'])) {
            sendMail($a['email'], $a['full_name'], 'New Ticket: ' . $t['ticket_no'], emailTemplate('New Ticket Submitted', $body));
        }
    }
}

function notifyTicketAssigned($db, $ticketId, $techId) {
    $s = $db->prepare("SELECT ticket_no,subject,priority FROM tickets WHERE id=?");
    $s->bind_param('i', $ticketId);
    $s->execute();
    $t = $s->get_result()->fetch_assoc();
    $s2 = $db->prepare("SELECT full_name,email FROM users WHERE id=?");
    $s2->bind_param('i', $techId);
    $s2->execute();
    $tech = $s2->get_result()->fetch_assoc();
    if (!$t || !$tech || empty($tech['email'])) return;
    $link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . "/ticket_view.php?id={$ticketId}";
    $body = "<p>A ticket has been assigned to you.</p>
             <p><strong>Ticket:</strong> " . htmlspecialchars($t['ticket_no']) . "<br>
             <strong>Subject:</strong> " . htmlspecialchars($t['subject']) . "<br>
             <strong>Priority:</strong> " . htmlspecialchars($t['priority']) . "</p>
             <p><a href='" . htmlspecialchars($link) . "' style='display:inline-block;padding:10px 20px;background:#1B3A6B;color:#fff;border-radius:6px;text-decoration:none;font-size:13px'>View Ticket</a></p>";
    sendMail($tech['email'], $tech['full_name'], 'Ticket Assigned: ' . $t['ticket_no'], emailTemplate('Ticket Assigned to You', $body));
}

function notifyStatusUpdated($db, $ticketId, $newStatus, $note) {
    if ($newStatus !== 'Resolved') return;
    $s = $db->prepare("SELECT t.ticket_no,t.subject,COALESCE(s.full_name,t.client_name) AS full_name,COALESCE(s.email,t.client_email) AS email FROM tickets t LEFT JOIN users s ON s.id=t.submitted_by WHERE t.id=?");
    $s->bind_param('i', $ticketId);
    $s->execute();
    $t = $s->get_result()->fetch_assoc();
    if (!$t || empty($t['email'])) return;
    $link    = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . "/ticket_view.php?id={$ticketId}";
    $subject = 'Your Ticket Has Been Resolved: ' . $t['ticket_no'];
    $body    = "<p>Good news! Your ICT issue has been resolved.</p>
                <p><strong>Ticket:</strong> " . htmlspecialchars($t['ticket_no']) . "<br>
                <strong>Subject:</strong> " . htmlspecialchars($t['subject']) . "</p>
                <p><strong>Resolution Comment:</strong><br>" . nl2br(htmlspecialchars($note)) . "</p>
                <p>If you are still experiencing issues, please submit a new ticket.</p>
                <p><a href='" . htmlspecialchars($link) . "' style='display:inline-block;padding:10px 20px;background:#2BAA6F;color:#fff;border-radius:6px;text-decoration:none;font-size:13px'>View Resolved Ticket</a></p>";
    sendMail($t['email'], $t['full_name'], $subject, emailTemplate('Ticket Resolved', $body));
}

function notifySlaWarning($db, $ticketId) {
    $s = $db->prepare("SELECT t.ticket_no,t.subject,u.full_name,u.email FROM tickets t LEFT JOIN users u ON u.id=t.assigned_to WHERE t.id=?");
    $s->bind_param('i', $ticketId);
    $s->execute();
    $t = $s->get_result()->fetch_assoc();
    if (!$t || empty($t['email'])) return;
    $link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . "/ticket_view.php?id={$ticketId}";
    $body = "<p>This ticket is approaching its SLA deadline.</p>
             <p><strong>Ticket:</strong> " . htmlspecialchars($t['ticket_no']) . "<br>
             <strong>Subject:</strong> " . htmlspecialchars($t['subject']) . "</p>
             <p>Please take action immediately.</p>
             <p><a href='" . htmlspecialchars($link) . "' style='display:inline-block;padding:10px 20px;background:#1B3A6B;color:#fff;border-radius:6px;text-decoration:none;font-size:13px'>View Ticket</a></p>";
    sendMail($t['email'], $t['full_name'], 'SLA Warning: ' . $t['ticket_no'], emailTemplate('SLA Approaching Breach', $body));
}

function notifyAccountCreated($db, $newUserId) {
    $s = $db->prepare("SELECT full_name,email,role FROM users WHERE id=?");
    $s->bind_param('i', $newUserId);
    $s->execute();
    $u = $s->get_result()->fetch_assoc();
    if (!$u || empty($u['email'])) return;
    $loginLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . "/login.php";
    $body = "<p>Hi " . htmlspecialchars($u['full_name']) . ",</p>
             <p>Welcome to the <strong>ICT Help Desk</strong>! Your account has been created successfully.</p>
             <p><strong>Email:</strong> " . htmlspecialchars($u['email']) . "<br>
             <strong>Role:</strong> " . htmlspecialchars(ucfirst($u['role'])) . "</p>
             <p>You can now sign in and start submitting support tickets.</p>
             <p><a href='" . htmlspecialchars($loginLink) . "' style='display:inline-block;padding:10px 20px;background:#1B3A6B;color:#fff;border-radius:6px;text-decoration:none;font-size:13px'>Sign In Now</a></p>
             <p style='font-size:12px;color:#6B7A99;'>If you did not create this account, please contact your ICT administrator immediately.</p>";
    sendMail($u['email'], $u['full_name'], 'Welcome to ICT Help Desk — Account Created', emailTemplate('Account Created Successfully', $body));
}

function notifyEscalation($db, $ticketId) {
    $s = $db->prepare("SELECT ticket_no,subject FROM tickets WHERE id=?");
    $s->bind_param('i', $ticketId);
    $s->execute();
    $t = $s->get_result()->fetch_assoc();
    if (!$t) return;
    $admins = $db->query("SELECT full_name,email FROM users WHERE role='admin' AND email != ''");
    $link   = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . "/ticket_view.php?id={$ticketId}";
    $body   = "<p>A ticket has been automatically escalated due to SLA breach.</p>
               <p><strong>Ticket:</strong> " . htmlspecialchars($t['ticket_no']) . "<br>
               <strong>Subject:</strong> " . htmlspecialchars($t['subject']) . "</p>
               <p><strong>This requires immediate attention.</strong></p>
               <p><a href='" . htmlspecialchars($link) . "' style='display:inline-block;padding:10px 20px;background:#D94F3D;color:#fff;border-radius:6px;text-decoration:none;font-size:13px'>View Escalated Ticket</a></p>";
    while ($a = $admins->fetch_assoc()) {
        if (!empty($a['email'])) {
            sendMail($a['email'], $a['full_name'], 'ESCALATED: ' . $t['ticket_no'], emailTemplate('Ticket Escalated', $body));
        }
    }
}

