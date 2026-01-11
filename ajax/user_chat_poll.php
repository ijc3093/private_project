<?php
// /Business_only3/ajax/user_chat_poll.php
declare(strict_types=1);

error_reporting(0);

require_once __DIR__ . '/../controller.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function myUserEmail(): string {
    $keys = ['user_email', 'email', 'userlogin', 'login_email'];
    foreach ($keys as $k) if (!empty($_SESSION[$k])) return trim((string)$_SESSION[$k]);
    return '';
}

$meEmail = myUserEmail();
if ($meEmail === '') {
    echo json_encode(['ok'=>false,'error'=>'Not logged in']);
    exit;
}

$reply = trim((string)($_GET['reply'] ?? ''));
$after = (int)($_GET['after'] ?? 0);

if ($reply === '') {
    echo json_encode(['ok'=>false,'error'=>'Missing reply']);
    exit;
}

$controller = new Controller();
$dbh = $controller->pdo();

$st = $dbh->prepare("
    SELECT email, status
    FROM users
    WHERE UPPER(friend_code) = :c
    LIMIT 1
");
$st->execute([':c' => strtoupper($reply)]);
$peer = $st->fetch(PDO::FETCH_ASSOC);

if (!$peer || (int)($peer['status'] ?? 1) !== 1) {
    echo json_encode(['ok'=>false,'error'=>'Friend not found']);
    exit;
}

$peerEmail = (string)$peer['email'];

try {
    $q = $dbh->prepare("
        SELECT id, sender, receiver, feedbackdata, created_at
        FROM feedback
        WHERE channel='user_user'
          AND id > :after
          AND (
                (sender=:me AND receiver=:peer)
             OR (sender=:peer2 AND receiver=:me2)
          )
        ORDER BY id ASC
        LIMIT 100
    ");
    $q->execute([
        ':after' => $after,
        ':me'    => $meEmail,
        ':peer'  => $peerEmail,
        ':peer2' => $peerEmail,
        ':me2'   => $meEmail,
    ]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    // Mark peer->me messages as read
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read=1, read_at=NOW()
        WHERE channel='user_user'
          AND receiver=:me
          AND sender=:peer
          AND is_read=0
    ");
    $mk->execute([':me'=>$meEmail, ':peer'=>$peerEmail]);

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id' => (int)$r['id'],
            'is_me' => ((string)$r['sender'] === $meEmail),
            'feedbackdata' => (string)($r['feedbackdata'] ?? ''),
            'time' => $r['created_at'] ? date('M d, Y h:i A', strtotime((string)$r['created_at'])) : '',
        ];
    }

    echo json_encode(['ok'=>true,'items'=>$items]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Server error']);
    exit;
}
