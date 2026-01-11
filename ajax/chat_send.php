<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$controller = new Controller();
$dbh = $controller->pdo();

$meEmail = myUserEmail();
if ($meEmail === '' || !filter_var($meEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid session identity']);
    exit;
}

$peer = trim((string)($_POST['peer'] ?? ''));
$text = trim((string)($_POST['message'] ?? ''));

if ($peer === '' || !filter_var($peer, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid peer']);
    exit;
}

$attachment = null;

// Attachment upload (optional)
try {
    $folder = dirname(__DIR__) . "/attachment/"; // /Business_only3/attachment/
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    if (!empty($_FILES['attachment']['name'])) {
        $file     = (string)$_FILES['attachment']['name'];
        $file_loc = (string)$_FILES['attachment']['tmp_name'];

        $ext      = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','pdf','doc','docx'];

        if (!in_array($ext, $allowed, true)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid attachment type']);
            exit;
        }

        $base = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($file, PATHINFO_FILENAME));
        $final_file = strtolower($base . '-' . time() . '.' . $ext);

        if (!move_uploaded_file($file_loc, $folder . $final_file)) {
            echo json_encode(['ok' => false, 'error' => 'Attachment upload failed']);
            exit;
        }

        $attachment = $final_file;
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Attachment handling error']);
    exit;
}

if ($text === '' && !$attachment) {
    echo json_encode(['ok' => false, 'error' => 'Message cannot be empty']);
    exit;
}

try {
    $stmt = $dbh->prepare("
        INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read)
        VALUES (:s, :r, 'user_user', 'Chat', :d, :a, 0)
    ");
    $stmt->execute([
        ':s' => $meEmail,
        ':r' => $peer,
        ':d' => $text,
        ':a' => $attachment
    ]);

    $newId = (int)$dbh->lastInsertId();

    echo json_encode([
        'ok' => true,
        'message' => [
            'id' => $newId,
            'sender' => $meEmail,
            'receiver' => $peer,
            'feedbackdata' => $text,
            'attachment' => $attachment,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    exit;
}
