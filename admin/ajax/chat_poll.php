<?php
require_once __DIR__ . '/../includes/session_admin.php';
requireAdminLogin();

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$peer   = trim($_GET['peer'] ?? '');
$lastId = (int)($_GET['last_id'] ?? 0);

if ($peer === '' || !filter_var($peer, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid peer email']);
    exit;
}

try {
    // ✅ auto-mark read: user -> Admin
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read = 1, read_at = NOW()
        WHERE sender = :peer
          AND receiver = :admin
          AND is_read = 0
    ");
    $mk->execute([
        ':peer'  => $peer,
        ':admin' => 'Admin'
    ]);

    // ✅ fetch only new messages (by id)
    $st = $dbh->prepare("
        SELECT id, sender, receiver, feedbackdata, attachment, created_at
        FROM feedback
        WHERE (
              (sender = :peer1 AND receiver = :admin1)
           OR (sender = :admin2 AND receiver = :peer2)
        )
          AND id > :lastId
        ORDER BY id ASC
        LIMIT 200
    ");
    $st->execute([
        ':peer1'  => $peer,
        ':admin1' => 'Admin',
        ':admin2' => 'Admin',
        ':peer2'  => $peer,
        ':lastId' => $lastId,
    ]);
    $messages = $st->fetchAll(PDO::FETCH_ASSOC);

    // optional: overall unread count (Admin inbox badge)
    $cnt = $dbh->prepare("
        SELECT COUNT(*) 
        FROM feedback
        WHERE receiver = :admin AND is_read = 0
    ");
    $cnt->execute([':admin' => 'Admin']);
    $unreadTotal = (int)$cnt->fetchColumn();

    echo json_encode([
        'ok' => true,
        'unread_total' => $unreadTotal,
        'messages' => $messages
    ]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
