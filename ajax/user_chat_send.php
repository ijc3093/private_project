<?php
// /Business_only3/ajax/user_chat_send.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function j(array $arr): void {
    echo json_encode($arr);
    exit;
}

$meCode = function_exists('userFriendCode') ? userFriendCode() : trim((string)($_SESSION['user_friend_code'] ?? ''));
$meCode = trim((string)$meCode);

$to  = trim((string)($_POST['to'] ?? ''));
$msg = trim((string)($_POST['message'] ?? ''));

// Optional: document link/url (no upload)
$attachmentUrl = trim((string)($_POST['attachment_url'] ?? ''));

// Optional: upload file (image/video/doc/gif)
$uploaded = $_FILES['attachment'] ?? null;

$hasUpload = is_array($uploaded) && !empty($uploaded['name']) && (int)($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$hasUrl    = $attachmentUrl !== '';

if ($meCode === '' || $to === '') {
    j(['ok'=>false,'error'=>'Missing fields']);
}

// Allow empty message if user is sending attachment or url
if ($msg === '' && !$hasUpload && !$hasUrl) {
    j(['ok'=>false,'error'=>'Message or attachment required']);
}

// friend code validation
if (!preg_match('/^[A-Z]{3}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $to)) {
    j(['ok'=>false,'error'=>'Invalid peer']);
}

if ($hasUrl) {
    if (!filter_var($attachmentUrl, FILTER_VALIDATE_URL)) {
        j(['ok'=>false,'error'=>'Invalid URL']);
    }
}

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    // ensure peer exists
    $st = $dbh->prepare("SELECT id, status FROM users WHERE UPPER(friend_code)=UPPER(:c) LIMIT 1");
    $st->execute([':c'=>$to]);
    $peer = $st->fetch(PDO::FETCH_ASSOC);

    if (!$peer || (int)($peer['status'] ?? 1) !== 1) {
        j(['ok'=>false,'error'=>'Peer not found']);
    }

    $attachmentValue = null;

    // Handle upload (save under /Business_only3/attachment/)
    if ($hasUpload) {
        if ((int)$uploaded['error'] !== UPLOAD_ERR_OK) {
            j(['ok'=>false,'error'=>'Upload failed']);
        }

        $origName = (string)$uploaded['name'];
        $tmpName  = (string)$uploaded['tmp_name'];

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // Allowed extensions (image/video/doc/gif)
        $allowed = [
            'jpg','jpeg','png','gif',
            'mp4','webm','ogg','mov',
            'pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip'
        ];

        if ($ext === '' || !in_array($ext, $allowed, true)) {
            j(['ok'=>false,'error'=>'File type not allowed']);
        }

        // Limit size (25MB)
        $maxBytes = 25 * 1024 * 1024;
        if ((int)($uploaded['size'] ?? 0) > $maxBytes) {
            j(['ok'=>false,'error'=>'File too large']);
        }

        $saveDir = realpath(__DIR__ . '/../attachment');
        if (!$saveDir) {
            // create if missing
            @mkdir(__DIR__ . '/../attachment', 0755, true);
            $saveDir = realpath(__DIR__ . '/../attachment');
        }
        if (!$saveDir) {
            j(['ok'=>false,'error'=>'Attachment folder missing']);
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $newName  = $safeBase . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $saveDir . DIRECTORY_SEPARATOR . $newName;

        if (!move_uploaded_file($tmpName, $destPath)) {
            j(['ok'=>false,'error'=>'Could not save file']);
        }

        $attachmentValue = $newName;
    } elseif ($hasUrl) {
        // Store URL as a value in attachment (so we don't require schema changes)
        $attachmentValue = $attachmentUrl;
    }

    $ins = $dbh->prepare("
        INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read, created_at)
        VALUES (:s, :r, 'user_user', '', :m, :a, 0, NOW())
    ");
    $ins->execute([
        ':s'=>$meCode,
        ':r'=>$to,
        ':m'=>$msg,
        ':a'=>$attachmentValue
    ]);

    $id = (int)$dbh->lastInsertId();

    $nowTs    = time();
    $created  = date('Y-m-d H:i:s', $nowTs);
    $dayKey   = date('Y-m-d', $nowTs);
    $dayLabel = date('M d, Y', $nowTs);
    $timeLabel= date('M d, Y h:i A', $nowTs);

    j([
        'ok'  => true,
        'item'=> [
            'id'         => $id,
            'is_me'      => true,
            'text'       => $msg,
            'attachment' => $attachmentValue,
            'created_at' => $created,
            'day_key'    => $dayKey,
            'day_label'  => $dayLabel,
            'time_label' => $timeLabel,
            'is_read'    => 0,
        ]
    ]);

} catch (Throwable $e) {
    j(['ok'=>false,'error'=>'Server error']);
}
