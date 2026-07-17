<?php














require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/imap_config.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    requireRole('admin');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfToken();
    }
}

header('Content-Type: application/json');

function jsonExit($data) {
    echo json_encode($data);
    exit;
}

if (!defined('IMAP_ENABLED') || !IMAP_ENABLED) {
    jsonExit(array('ok' => false, 'error' => 'Email intake is disabled (IMAP_ENABLED is false in imap_config.php).'));
}
if (!function_exists('imap_open')) {
    jsonExit(array('ok' => false, 'error' => 'The PHP IMAP extension is not enabled on this server.'));
}

$db = getDB();

$mailbox = '{' . IMAP_HOST . ':' . IMAP_PORT . IMAP_ENCRYPTION . '}' . IMAP_FOLDER_INBOX;
$conn = @imap_open($mailbox, IMAP_USERNAME, IMAP_PASSWORD);

if (!$conn) {
    jsonExit(array('ok' => false, 'error' => 'IMAP connection failed: ' . imap_last_error()));
}

$allowed_ext   = array('jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx', 'txt');
$allowed_mimes = array(
    'image/jpeg',
    'image/png',
    'application/pdf',
    'text/plain',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
);

$createdCount = 0;
$skippedCount = 0;
$errors       = array();

$ids = imap_search($conn, 'UNSEEN');
if ($ids === false) {
    $ids = array();
}

foreach ($ids as $msgno) {
    $headerInfo = imap_headerinfo($conn, $msgno);
    $messageId  = isset($headerInfo->message_id) ? trim($headerInfo->message_id) : ('no-msgid-' . md5(imap_fetchheader($conn, $msgno)));

    
    $chk = $db->prepare('SELECT id FROM email_ticket_log WHERE message_id = ?');
    $chk->bind_param('s', $messageId);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $skippedCount++;
        imap_setflag_full($conn, $msgno, '\\Seen');
        continue;
    }

    
    $fromEmail = 'unknown@unknown.invalid';
    $fromName  = 'Unknown Sender';
    if (!empty($headerInfo->from) && isset($headerInfo->from[0])) {
        $fromObj   = $headerInfo->from[0];
        $fromEmail = strtolower(trim($fromObj->mailbox . '@' . $fromObj->host));
        $fromName  = isset($fromObj->personal) ? imap_utf8($fromObj->personal) : $fromEmail;
    }

    
    
    
    
    
    $selfAddresses = array(strtolower(trim(IMAP_USERNAME)));
    if (defined('SMTP_FROM_EMAIL')) $selfAddresses[] = strtolower(trim(SMTP_FROM_EMAIL));
    if (in_array($fromEmail, $selfAddresses, true)) {
        $skippedCount++;
        imap_setflag_full($conn, $msgno, '\\Seen');
        continue;
    }

    
    $subject = isset($headerInfo->subject) ? imap_utf8($headerInfo->subject) : '(No Subject)';
    $subject = trim($subject);
    if ($subject === '') $subject = '(No Subject)';
    if (strlen($subject) > 200) $subject = substr($subject, 0, 200);

    
    $structure = imap_fetchstructure($conn, $msgno);
    $body      = extractPlainBody($conn, $msgno, $structure);
    if (trim($body) === '') $body = '(Email had no readable message body.)';

    
    $scan     = strtolower($subject . ' ' . $body);
    $priority = 'Medium';
    if (preg_match('/\b(urgent|critical|asap|down|outage)\b/', $scan)) {
        $priority = 'High';
    } elseif (preg_match('/\b(low priority|whenever|no rush)\b/', $scan)) {
        $priority = 'Low';
    }

    
    $category = 'Other';
    $stmt = $db->prepare("INSERT INTO tickets (ticket_no,subject,description,category,priority,submitted_by,client_name,client_email,source) VALUES ('PENDING',?,?,?,?,NULL,?,?,'email')");
    $stmt->bind_param('ssssss', $subject, $body, $category, $priority, $fromName, $fromEmail);
    if (!$stmt->execute()) {
        $errors[] = "Failed to create ticket for message from $fromEmail: " . $db->error;
        continue;
    }

    $newId = $db->insert_id;
    $tno   = sprintf('TKT-%s-%04d', date('Y'), $newId);
    $upd   = $db->prepare('UPDATE tickets SET ticket_no=? WHERE id=?');
    $upd->bind_param('si', $tno, $newId);
    $upd->execute();

    list($rd, $sd) = calculateSlaDeadlines($db, $priority, date('Y-m-d H:i:s'));
    $sla = $db->prepare('UPDATE tickets SET sla_response_due=?,sla_resolution_due=? WHERE id=?');
    $sla->bind_param('ssi', $rd, $sd, $newId);
    $sla->execute();

    
    saveAttachments($conn, $msgno, $structure, $newId, $allowed_ext, $allowed_mimes, $db);

    
    $log = $db->prepare('INSERT INTO email_ticket_log (message_id, ticket_id) VALUES (?, ?)');
    $log->bind_param('si', $messageId, $newId);
    $log->execute();

    notifyTicketCreated($db, $newId);

    imap_setflag_full($conn, $msgno, '\\Seen');
    if (defined('IMAP_FOLDER_PROCESSED') && IMAP_FOLDER_PROCESSED !== '') {
        @imap_mail_move($conn, $msgno, IMAP_FOLDER_PROCESSED);
    }

    $createdCount++;
}

imap_expunge($conn);
imap_close($conn);

jsonExit(array(
    'ok'      => true,
    'created' => $createdCount,
    'skipped' => $skippedCount,
    'errors'  => $errors,
));





function extractPlainBody($conn, $msgno, $structure) {
    if (!isset($structure->parts) || !$structure->parts) {
        $raw = imap_body($conn, $msgno);
        return decodePart($raw, isset($structure->encoding) ? $structure->encoding : 0);
    }

    $plain = findPart($conn, $msgno, $structure, 'TEXT/PLAIN');
    if ($plain !== null) return $plain;

    $html = findPart($conn, $msgno, $structure, 'TEXT/HTML');
    if ($html !== null) {
        return trim(html_entity_decode(strip_tags($html)));
    }

    return '';
}

function findPart($conn, $msgno, $structure, $wantMime, $partNum = '', $depth = 0) {
    if ($depth > 6) return null; 

    if (!isset($structure->parts) || !$structure->parts) {
        $mime = subtypeLabel($structure);
        if ($mime === $wantMime) {
            $raw = $partNum === '' ? imap_body($conn, $msgno) : imap_fetchbody($conn, $msgno, $partNum);
            return decodePart($raw, isset($structure->encoding) ? $structure->encoding : 0);
        }
        return null;
    }

    foreach ($structure->parts as $i => $part) {
        $num = $partNum === '' ? (string)($i + 1) : $partNum . '.' . ($i + 1);
        if (isset($part->parts) && $part->parts) {
            $found = findPart($conn, $msgno, $part, $wantMime, $num, $depth + 1);
            if ($found !== null) return $found;
        } else {
            $mime = subtypeLabel($part);
            if ($mime === $wantMime && (!isset($part->disposition) || strtolower($part->disposition) !== 'attachment')) {
                $raw = imap_fetchbody($conn, $msgno, $num);
                return decodePart($raw, isset($part->encoding) ? $part->encoding : 0);
            }
        }
    }
    return null;
}

function subtypeLabel($part) {
    $primary = array('TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER');
    $type    = isset($part->type) && isset($primary[$part->type]) ? $primary[$part->type] : 'OTHER';
    $sub     = isset($part->subtype) ? strtoupper($part->subtype) : '';
    return $type . '/' . $sub;
}

function decodePart($data, $encoding) {
    
    if ($encoding == 3) return base64_decode($data);
    if ($encoding == 4) return quoted_printable_decode($data);
    return $data;
}





function saveAttachments($conn, $msgno, $structure, $ticketId, $allowed_ext, $allowed_mimes, $db) {
    if (!isset($structure->parts) || !$structure->parts) return;
    walkAttachmentParts($conn, $msgno, $structure->parts, '', $ticketId, $allowed_ext, $allowed_mimes, $db);
}

function walkAttachmentParts($conn, $msgno, $parts, $prefix, $ticketId, $allowed_ext, $allowed_mimes, $db) {
    foreach ($parts as $i => $part) {
        $num = $prefix === '' ? (string)($i + 1) : $prefix . '.' . ($i + 1);

        if (isset($part->parts) && $part->parts) {
            walkAttachmentParts($conn, $msgno, $part->parts, $num, $ticketId, $allowed_ext, $allowed_mimes, $db);
            continue;
        }

        $filename = null;
        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $p) {
                if (strtolower($p->attribute) === 'filename') $filename = $p->value;
            }
        }
        if (!$filename && isset($part->parameters)) {
            foreach ($part->parameters as $p) {
                if (strtolower($p->attribute) === 'name') $filename = $p->value;
            }
        }
        if (!$filename) continue; 

        $filename = imap_utf8($filename);
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) continue; 

        $raw = imap_fetchbody($conn, $msgno, $num);
        $raw = decodePart($raw, isset($part->encoding) ? $part->encoding : 0);
        if (strlen($raw) > 5 * 1024 * 1024) continue; 

        if (function_exists('finfo_open')) {
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($fi, $raw);
            finfo_close($fi);
            if (!in_array($mime, $allowed_mimes, true)) continue;
        }

        $dir = __DIR__ . '/uploads/tickets/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $safe = 'tkt_' . $ticketId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $dest = $dir . $safe;
        if (file_put_contents($dest, $raw) === false) continue;

        $rel  = 'uploads/tickets/' . $safe;
        $size = strlen($raw);
        $ins  = $db->prepare('INSERT INTO ticket_attachments (ticket_id,uploaded_by,file_name,file_path,file_size) VALUES (?,NULL,?,?,?)');
        $ins->bind_param('issi', $ticketId, $filename, $rel, $size);
        $ins->execute();
    }
}
