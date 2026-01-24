<?php
// /Business_only3/ajax/user_chat_poll.php
// - Long-poll chat messages: ?peer=USR-XXXX-YYYY&after=123&wait=20
// - Header dropdown unread threads: ?mode=unread_threads

declare(strict_types=1);
error_reporting(0);

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../includes/user_identity.php';
require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function j(array $arr): void {
    echo json_encode($arr);
    exit;
}

$controller = new Controller();
$dbh = $controller->pdo();

$meId    = function_exists('userId') ? (int)userId() : (int)($_SESSION['user_id'] ?? 0);
$meCode  = trim((string)userFriendCode());
$meEmail = trim((string)userEmail());
$meName  = trim((string)myUserName());

if ($meId <= 0 || ($meCode === '' && $meEmail === '')) {
    j(['ok' => false, 'error' => 'Not logged in']);
}

$mode = trim((string)($_GET['mode'] ?? ''));

// ---------------------------------------------------------------------
// MODE: unread_threads (for header dropdown)
// ---------------------------------------------------------------------
if ($mode === 'unread_threads') {
    try {
        $st = $dbh->prepare("
            SELECT
                u.friend_code AS peer_code,
                uc.display_name AS contact_name,
                COALESCE(NULLIF(uc.display_name,''), u.friend_code) AS peer_display,
                MAX(f.created_at) AS last_time,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR '\n'),
                    '\n',
                    1
                ) AS last_message,
                COUNT(*) AS unread_count
            FROM feedback f
            JOIN users u
              ON (u.friend_code = f.sender OR u.email = f.sender)
            LEFT JOIN user_contacts uc
              ON uc.owner_user_id = :meId
             AND uc.friend_user_id = u.id
            WHERE f.channel = 'user_user'
              AND (f.receiver = :meCode OR f.receiver = :meEmail)
              AND f.is_read = 0
            GROUP BY u.friend_code, peer_display
            ORDER BY last_time DESC
            LIMIT 12
        ");
        $st->execute([
            ':meId'   => $meId,
            ':meCode' => $meCode,
            ':meEmail'=> $meEmail,
        ]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = 0;
        $unknown = 0;
        foreach ($items as $it) {
            $c = (int)($it['unread_count'] ?? 0);
            $total += $c;
            if (trim((string)($it['contact_name'] ?? '')) === '') {
                $unknown += $c;
            }
        }

        j(['ok' => true, 'items' => $items, 'total_unread' => $total, 'unknown_unread' => $unknown]);
    } catch (Throwable $e) {
        j(['ok' => false, 'error' => 'Server error']);
    }
}

// ---------------------------------------------------------------------
// MODE: chat message long-poll
// ---------------------------------------------------------------------
$peerCode = strtoupper(trim((string)($_GET['peer'] ?? '')));
if ($peerCode === '') {
    // legacy param name used in some places
    $peerCode = strtoupper(trim((string)($_GET['reply'] ?? '')));
}

$after = (int)($_GET['after'] ?? 0);
$wait  = (int)($_GET['wait'] ?? 0);
if ($wait < 0) $wait = 0;
if ($wait > 25) $wait = 25; // keep within safe PHP execution times

$markRead = (int)($_GET['mark'] ?? 0); // only mark messages read when chat view explicitly requests it

if ($peerCode === '') j(['ok' => false, 'error' => 'Missing peer']);
if (!preg_match('/^[A-Z]{3}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $peerCode)) {
    j(['ok' => false, 'error' => 'Friend code required']);
}

try {
    // Resolve peer
    $st = $dbh->prepare("
        SELECT id, email, friend_code,
               COALESCE(NULLIF(name,''), NULLIF(username,''), friend_code) AS db_display
        FROM users
        WHERE UPPER(friend_code) = :c
          AND status = 1
        LIMIT 1
    ");
    $st->execute([':c' => strtoupper($peerCode)]);
    $peer = $st->fetch(PDO::FETCH_ASSOC);

    if (!$peer) j(['ok' => false, 'error' => 'Friend not found']);

    $peerId      = (int)($peer['id'] ?? 0);
    $peerEmail   = (string)($peer['email'] ?? '');

    // Prefer contact nickname, otherwise show only friend code (not their real name)
    $peerDisplay = $peerCode;
    try {
        $ns = $dbh->prepare("SELECT display_name FROM user_contacts WHERE owner_user_id = :me AND friend_user_id = :fid LIMIT 1");
        $ns->execute([':me' => $meId, ':fid' => $peerId]);
        $nick = trim((string)$ns->fetchColumn());
        if ($nick !== '') $peerDisplay = $nick;
    } catch (Throwable $e) {
        // ignore
    }

    $deadline = $wait > 0 ? (microtime(true) + $wait) : microtime(true);

    $items = [];
    $lastId = $after;

    do {
        // Pull new rows since last id
        $q = $dbh->prepare("
            SELECT id, sender, receiver, feedbackdata, attachment, created_at, is_read
            FROM feedback
            WHERE channel = 'user_user'
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

        if ($rows) {
            foreach ($rows as $r) {
                $id = (int)$r['id'];
                if ($id > $lastId) $lastId = $id;

                $sender = (string)$r['sender'];
                $isMe = (strcasecmp($sender, $meCode) === 0) || ($meEmail !== '' && strcasecmp($sender, $meEmail) === 0);

                $created = (string)($r['created_at'] ?? '');
                $ts = $created ? strtotime($created) : 0;

                $items[] = [
                    'id' => $id,
                    'is_me' => $isMe,
                    'sender_name' => $isMe ? (($meName !== '') ? $meName : 'You') : $peerDisplay,
                    'peer_name' => $peerDisplay,
                    'text' => (string)($r['feedbackdata'] ?? ''),
                    'attachment' => (string)($r['attachment'] ?? ''),
                    'created_at' => $created,
                    'time_label' => $ts ? date('M d, Y h:i A', $ts) : '',
                    'is_read' => (int)($r['is_read'] ?? 0),
                ];
            }
            break; // we have items; stop long-poll loop
        }

        if ($wait <= 0) break;
        usleep(250000); // 250ms
    } while (microtime(true) < $deadline);

    if ($markRead === 1) {
    // Mark peer->me as read (legacy supports receiver stored as email)
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE channel = 'user_user'
              AND receiver IN (:meCode, :meEmail)
              AND sender   IN (:peerCode, :peerEmail)
              AND is_read = 0
        ");
        $mk->execute([
            ':meCode'    => $meCode,
            ':meEmail'   => $meEmail,
            ':peerCode'  => $peerCode,
            ':peerEmail' => $peerEmail,
        ]);
    
        
}

j(['ok' => true, 'items' => $items, 'last_id' => $lastId, 'peer_display' => $peerDisplay]);
} catch (Throwable $e) {
    j(['ok' => false, 'error' => 'Server error']);
}
