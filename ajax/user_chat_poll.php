<?php
// /Business_only3/ajax/user_chat_poll.php
declare(strict_types=1);

error_reporting(0);

require_once __DIR__ . '/../admin/controller.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function myFriendCode(): string {
    if (function_exists('userFriendCode')) return trim((string)userFriendCode());
    return trim((string)($_SESSION['user_friend_code'] ?? $_SESSION['friend_code'] ?? ''));
}

function fmt_time_short(string $dt): string {
    if ($dt === '') return '';
    $ts = strtotime($dt);
    if (!$ts) return '';
    return date('h:i A', $ts);
}

$meCode = myFriendCode();
if ($meCode === '') {
    echo json_encode(['ok'=>false,'error'=>'Not logged in']);
    exit;
}

$controller = new Controller();
$dbh = $controller->pdo();

$mode  = trim((string)($_GET['mode'] ?? ''));
$after = (int)($_GET['after'] ?? 0);

/**
 * MODE 1: dropdown unread threads (friend_code based)
 * GET: ?mode=unread_threads
 * Returns ONLY peers who have unread messages to me.
 */
if ($mode === 'unread_threads') {
    try {
        // unread grouped by sender (peer_code), last message via max(id)
        $sql = "
            SELECT
                uu.peer_code,
                COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), u.friend_code, uu.peer_code) AS peer_display,
                f.feedbackdata AS last_message,
                f.created_at   AS last_time,
                uu.unread_count
            FROM (
                SELECT sender AS peer_code, COUNT(*) AS unread_count, MAX(id) AS last_id
                FROM feedback
                WHERE channel='user_user'
                  AND receiver = ?
                  AND is_read = 0
                GROUP BY sender
            ) uu
            JOIN feedback f ON f.id = uu.last_id
            LEFT JOIN users u ON UPPER(u.friend_code) = UPPER(uu.peer_code)
            ORDER BY f.created_at DESC, f.id DESC
            LIMIT 30
        ";
        $st = $dbh->prepare($sql);
        $st->execute([$meCode]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        $total = 0;

        foreach ($rows as $r) {
            $peerCode = (string)($r['peer_code'] ?? '');
            if ($peerCode === '') continue;

            $unread = (int)($r['unread_count'] ?? 0);
            $total += $unread;

            $items[] = [
                'peer_code'    => $peerCode,
                'peer_display' => (string)($r['peer_display'] ?? $peerCode),
                'last_message' => (string)($r['last_message'] ?? ''),
                'last_time'    => fmt_time_short((string)($r['last_time'] ?? '')),
                'unread_count' => $unread,
            ];
        }

        echo json_encode([
            'ok' => true,
            'items' => $items,
            'total_unread' => $total
        ]);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>'Server error']);
        exit;
    }
}

/**
 * MODE 2 (optional legacy): load messages in a thread + mark read
 * GET: ?reply=<FRIEND_CODE>&after=<ID>
 * NOTE: This supports friend_code style storage in feedback.
 */
$reply = trim((string)($_GET['reply'] ?? ''));
if ($reply === '') {
    echo json_encode(['ok'=>false,'error'=>'Missing reply']);
    exit;
}

if (!preg_match('/^[A-Z]{3}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $reply)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid friend code']);
    exit;
}

$peerCode = strtoupper($reply);

try {
    // load new messages after `after`
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
        ':me'    => $meCode,
        ':peer'  => $peerCode,
        ':peer2' => $peerCode,
        ':me2'   => $meCode,
    ]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Mark peer->me as read
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read=1, read_at=NOW()
        WHERE channel='user_user'
          AND receiver=:me
          AND sender=:peer
          AND is_read=0
    ");
    $mk->execute([':me'=>$meCode, ':peer'=>$peerCode]);

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id' => (int)$r['id'],
            'is_me' => (strcasecmp((string)$r['sender'], $meCode) === 0),
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
