<?php
// /Business_only3/admin/ajax/chat_poll.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/../controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$controller = new Controller();
$dbh = $controller->pdo();

$meEmail = trim((string)($_SESSION['admin_email'] ?? ''));
if ($meEmail === '' || !filter_var($meEmail, FILTER_VALIDATE_EMAIL)) {
    $aid = (int)($_SESSION['admin_id'] ?? 0);
    if ($aid > 0) {
        $q = $dbh->prepare("SELECT email FROM admin WHERE idadmin = :id LIMIT 1");
        $q->execute([':id' => $aid]);
        $meEmail = trim((string)$q->fetchColumn());
    }
}
if ($meEmail === '' || !filter_var($meEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Missing/invalid session identity']);
    exit;
}

$peer   = trim((string)($_GET['peer'] ?? ''));
$lastId = (int)($_GET['last_id'] ?? 0);

if ($peer === '' || !filter_var($peer, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid peer email']);
    exit;
}

try {
    // Mark unread messages from peer -> me as read
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read = 1, read_at = NOW()
        WHERE channel = 'internal_admin'
          AND sender = :peer
          AND receiver = :me
          AND is_read = 0
    ");
    $mk->execute([':peer' => $peer, ':me' => $meEmail]);

    // Fetch only NEW messages since lastId
    $st = $dbh->prepare("
        SELECT id, sender, receiver, feedbackdata, attachment, created_at
        FROM feedback
        WHERE channel = 'internal_admin'
          AND (
                (sender = :me AND receiver = :peer)
             OR (sender = :peer2 AND receiver = :me2)
          )
          AND id > :lastId
        ORDER BY id ASC
        LIMIT 200
    ");
    $st->execute([
        ':me' => $meEmail,
        ':peer' => $peer,
        ':peer2' => $peer,
        ':me2' => $meEmail,
        ':lastId' => $lastId
    ]);

    echo json_encode([
        'ok' => true,
        'messages' => $st->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    exit;
}
