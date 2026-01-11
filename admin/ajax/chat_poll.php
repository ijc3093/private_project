<?php
// /Business_only3/admin/ajax/chat_poll.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/../includes/identity.php';
require_once __DIR__ . '/../controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$controller = new Controller();
$dbh = $controller->pdo();

$meUser = myUsername();
$meRole = myRoleId();

if ($meUser === '' || $meRole <= 0) {
    echo json_encode(['ok'=>false,'error'=>'Invalid session']);
    exit;
}

$peer   = trim((string)($_GET['peer'] ?? ''));   // peer USERNAME
$lastId = (int)($_GET['last_id'] ?? 0);

if ($peer === '') {
    echo json_encode(['ok'=>false,'error'=>'Invalid peer']);
    exit;
}

// peer role => channel
$st = $dbh->prepare("SELECT username, role, status FROM admin WHERE username = :u LIMIT 1");
$st->execute([':u' => $peer]);
$peerRow = $st->fetch(PDO::FETCH_ASSOC);

if (!$peerRow || (int)$peerRow['status'] !== 1) {
    echo json_encode(['ok'=>false,'error'=>'Peer not found/inactive']);
    exit;
}

$peerRole = (int)$peerRow['role'];
$channel  = channelForAdminRoles($meRole, $peerRole);

if ($channel === '') {
    echo json_encode(['ok'=>false,'error'=>'Channel not allowed']);
    exit;
}

try {
    // mark unread peer->me as read
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read = 1, read_at = NOW()
        WHERE channel = :ch
          AND sender = :peer
          AND receiver = :me
          AND is_read = 0
    ");
    $mk->execute([':ch'=>$channel, ':peer'=>$peer, ':me'=>$meUser]);

    // fetch new messages
    $q = $dbh->prepare("
        SELECT id, sender, receiver, channel, feedbackdata, attachment, created_at
        FROM feedback
        WHERE channel = :ch
          AND (
                (sender = :me AND receiver = :peer)
             OR (sender = :peer2 AND receiver = :me2)
          )
          AND id > :lastId
        ORDER BY id ASC
        LIMIT 200
    ");
    $q->execute([
        ':ch' => $channel,
        ':me' => $meUser,
        ':peer' => $peer,
        ':peer2' => $peer,
        ':me2' => $meUser,
        ':lastId' => $lastId
    ]);

    echo json_encode(['ok'=>true, 'messages'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Server error']);
    exit;
}
