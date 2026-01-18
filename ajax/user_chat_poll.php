<?php
// /Business_only3/ajax/user_chat_poll.php
declare(strict_types=1);

error_reporting(0);

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function j(array $arr): void { echo json_encode($arr); exit; }

$controller = new Controller();
$dbh = $controller->pdo();

$meCode  = strtoupper(trim((string)userFriendCode()));
$meEmail = trim((string)userEmail());
$meName  = trim((string)myUserName());

if ($meCode === '' && $meEmail === '') j(['ok'=>false,'error'=>'Not logged in']);

$peerCode = strtoupper(trim((string)($_GET['peer'] ?? '')));
if ($peerCode === '') $peerCode = strtoupper(trim((string)($_GET['reply'] ?? ''))); // legacy
$after = (int)($_GET['after'] ?? 0);
$wait  = (int)($_GET['wait'] ?? 0); // seconds (long-poll)

if ($peerCode === '') j(['ok'=>false,'error'=>'Missing peer']);

if (!preg_match('/^[A-Z]{3}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $peerCode)) {
    j(['ok'=>false,'error'=>'Friend code required']);
}

try {
    // Resolve peer
    $st = $dbh->prepare("
        SELECT id, email, friend_code,
               COALESCE(NULLIF(name,''), NULLIF(username,''), friend_code) AS display
        FROM users
        WHERE UPPER(friend_code) = :c
          AND status = 1
        LIMIT 1
    ");
    $st->execute([':c' => $peerCode]);
    $peer = $st->fetch(PDO::FETCH_ASSOC);

    if (!$peer) j(['ok'=>false,'error'=>'Friend not found']);

    $peerEmail   = (string)$peer['email'];
    $peerDisplay = (string)$peer['display'];

    // Long-poll loop
    $deadline = time() + max(0, min($wait, 25)); // cap to 25s
    $rows = [];

    while (true) {
        // Pull new rows since last id
        $q = $dbh->prepare("
            SELECT id, sender, receiver, feedbackdata, created_at, is_read
            FROM feedback
            WHERE channel='user_user'
              AND id > :after
              AND (
                    (sender IN (:meCode, :meEmail) AND receiver IN (:peerCode, :peerEmail))
                 OR (sender IN (:peerCode2, :peerEmail2) AND receiver IN (:meCode2, :meEmail2))
              )
            ORDER BY id ASC
            LIMIT 200
        ");
        $q->execute([
            ':after'      => $after,
            ':meCode'     => $meCode,
            ':meEmail'    => $meEmail,
            ':peerCode'   => $peerCode,
            ':peerEmail'  => $peerEmail,
            ':peerCode2'  => $peerCode,
            ':peerEmail2' => $peerEmail,
            ':meCode2'    => $meCode,
            ':meEmail2'   => $meEmail,
        ]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!empty($rows) || $wait <= 0 || time() >= $deadline) {
            break;
        }

        // Sleep briefly then try again (keeps connection open)
        usleep(300000); // 300ms
        clearstatcache();
    }

    // Mark peer->me as read (only if there were messages OR always on poll)
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read=1, read_at=NOW()
        WHERE channel='user_user'
          AND receiver IN (:meCode, :meEmail)
          AND sender   IN (:peerCode, :peerEmail)
          AND is_read=0
    ");
    $mk->execute([
        ':meCode'    => $meCode,
        ':meEmail'   => $meEmail,
        ':peerCode'  => $peerCode,
        ':peerEmail' => $peerEmail,
    ]);

    $items = [];
    $lastId = $after;

    foreach ($rows as $r) {
        $id = (int)$r['id'];
        if ($id > $lastId) $lastId = $id;

        $sender = (string)$r['sender'];
        $isMe = (strcasecmp($sender, $meCode) === 0) || ($meEmail !== '' && strcasecmp($sender, $meEmail) === 0);

        $created = (string)($r['created_at'] ?? '');
        $ts = $created ? (strtotime($created) ?: 0) : 0;

        $items[] = [
            'id' => $id,
            'is_me' => $isMe,
            'sender_name' => $isMe ? (($meName !== '') ? $meName : 'You') : $peerDisplay,
            'text' => (string)($r['feedbackdata'] ?? ''),
            'created_at' => $created,
            'time_label' => $ts ? date('M d, Y h:i A', $ts) : '',
            'day_key'    => $ts ? date('Y-m-d', $ts) : '',
            'day_label'  => $ts ? (date('Y-m-d', $ts) === date('Y-m-d') ? 'Today' : date('M j, Y', $ts)) : '',
            // status: for my messages, show read tick if is_read=1
            'is_read' => (int)($r['is_read'] ?? 0),
        ];
    }

    j(['ok'=>true,'items'=>$items,'last_id'=>$lastId,'peer_name'=>$peerDisplay]);
} catch (Throwable $e) {
    j(['ok'=>false,'error'=>'Server error']);
}
