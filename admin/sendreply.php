<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$meUsername = myUsername();   // admin.username (used in feedback.sender)
$meId       = myAdminId();    // admin.idadmin
$meRole     = myRoleId();     // role int

if ($meUsername === '' || $meId <= 0 || $meRole <= 0) {
    die("Invalid session.");
}

function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : '';
}
function safe_text($txt) {
    return nl2br(htmlentities((string)($txt ?? '')));
}
function isEmailStr(string $s): bool {
    return (strpos($s, '@') !== false);
}

// Role map (adjust if you add more roles)
$roleMap = [
  1 => 'Admin',
  2 => 'Manager',
  3 => 'Gospel',
  4 => 'Staff',
  5 => 'Teacher',
];

/**
 * Resolve internal peer by either:
 * - friend_code
 * - username
 * Returns:
 *  [peerId, peerUsername, peerRole, peerFriendCode, peerDisplayName]
 */
function resolveInternalPeer(PDO $dbh, int $meId, string $replyRaw): array
{
    $replyRaw = trim($replyRaw);
    if ($replyRaw === '') return [];

    $peer = null;

    // Try friend_code first
    if (strpos($replyRaw, '-') !== false) {
        $code = strtoupper(trim($replyRaw));
        $code = preg_replace('/\s+/', '', $code);

        $st = $dbh->prepare("
            SELECT idadmin, username, role, friend_code, fullname
            FROM admin
            WHERE UPPER(friend_code) = :c AND status = 1
            LIMIT 1
        ");
        $st->execute([':c' => $code]);
        $peer = $st->fetch(PDO::FETCH_ASSOC);
    }

    // Else try username
    if (!$peer) {
        $st = $dbh->prepare("
            SELECT idadmin, username, role, friend_code, fullname
            FROM admin
            WHERE username = :u AND status = 1
            LIMIT 1
        ");
        $st->execute([':u' => $replyRaw]);
        $peer = $st->fetch(PDO::FETCH_ASSOC);
    }

    if (!$peer) return [];

    $peerId       = (int)$peer['idadmin'];
    $peerUsername = (string)$peer['username'];
    $peerRole     = (int)$peer['role'];
    $peerCode     = (string)($peer['friend_code'] ?? '');
    $peerFullname = trim((string)($peer['fullname'] ?? ''));

    // Optional: display name from contacts table (if exists)
    $dn = '';
    try {
        $st2 = $dbh->prepare("
            SELECT COALESCE(NULLIF(display_name,''),'') AS dn
            FROM admin_contacts
            WHERE owner_admin_id = :o AND friend_admin_id = :f
            LIMIT 1
        ");
        $st2->execute([':o' => $meId, ':f' => $peerId]);
        $dn = trim((string)$st2->fetchColumn());
    } catch (Throwable $e) {
        $dn = '';
    }

    $peerDisplay = $dn !== ''
        ? $dn
        : ($peerFullname !== '' ? $peerFullname : ($peerCode !== '' ? $peerCode : $peerUsername));

    return [$peerId, $peerUsername, $peerRole, $peerCode, $peerDisplay];
}

// -----------------------------
// TARGET (reply param)
// -----------------------------
$reply = trim(urldecode($_GET['reply'] ?? ''));
if ($reply === '') die("Missing recipient.");

$isEmail = isEmailStr($reply);

$channel        = '';
$peerUsername   = '';
$peerId         = 0;
$peerDisplay    = '';   // what we show for internal peer (name)
$peerFriendCode = '';
$peerRole       = 0;

// For the header label
$peerHeaderName = '';
$peerHeaderRole = 'Unknown';

// -----------------------------
// Resolve MODE
// -----------------------------
if ($isEmail) {
    // Admin support chat with a public user (only role 1)
    if (!isAdmin()) {
        die("Only Admin can message public users.");
    }

    $channel = 'user_admin';

    // peerDisplay holds public user's email (receiver value in DB)
    $peerDisplay = $reply;

    // Lookup user name+role for header
    $peerHeaderName = $peerDisplay;
    $peerHeaderRole = 'User';

    $stU = $dbh->prepare("SELECT name, role FROM users WHERE email = :e LIMIT 1");
    $stU->execute([':e' => $peerDisplay]);
    $u = $stU->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $peerHeaderName = trim((string)($u['name'] ?? $peerDisplay)) ?: $peerDisplay;
        $peerHeaderRole = $roleMap[(int)($u['role'] ?? 0)] ?? 'User';
    }

} else {
    // Internal chat (reply could be username OR friend_code)
    $resolved = resolveInternalPeer($dbh, $meId, $reply);
    if (empty($resolved)) {
        die("Recipient not found (or not active).");
    }

    [$peerId, $peerUsername, $peerRole, $peerFriendCode, $peerDisplay] = $resolved;

    $channel = channelForAdminRoles($meRole, $peerRole);
    if ($channel === '') die("You cannot chat with this role.");

    // Header values
    $peerHeaderName = $peerDisplay;
    $peerHeaderRole = $roleMap[$peerRole] ?? 'Internal';
}

// -----------------------------
// Mark unread as read (when opening)
// -----------------------------
try {
    if ($channel === 'user_admin') {
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE channel = 'user_admin'
              AND receiver = 'Admin'
              AND sender = :u
              AND is_read = 0
        ");
        $mk->execute([':u' => $peerDisplay]); // sender is user email
    } else {
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE channel = :ch
              AND receiver = :me
              AND sender = :peer
              AND is_read = 0
        ");
        $mk->execute([
            ':ch'   => $channel,
            ':me'   => $meUsername,
            ':peer' => $peerUsername
        ]);
    }
} catch (Throwable $e) {}

// -----------------------------
// Send message
// -----------------------------
$error = '';
if (isset($_POST['send'])) {
    $text = trim($_POST['message'] ?? '');

    if ($text === '') {
        $error = "Message cannot be empty.";
    } else {
        try {
            if ($channel === 'user_admin') {
                // Admin -> public user
                $stmt = $dbh->prepare("
                    INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, is_read)
                    VALUES ('Admin', :r, 'user_admin', 'Admin Chat', :d, 0)
                ");
                $stmt->execute([':r' => $peerDisplay, ':d' => $text]);

                // notify public user (receiver is email)
                $n = $dbh->prepare("
                    INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
                    VALUES ('Admin', :r, 'New chat message', 0)
                ");
                $n->execute([':r' => $peerDisplay]);

            } else {
                // Internal: me(username) -> peer(username)
                $stmt = $dbh->prepare("
                    INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, is_read)
                    VALUES (:s, :r, :ch, 'Internal Chat', :d, 0)
                ");
                $stmt->execute([
                    ':s'  => $meUsername,
                    ':r'  => $peerUsername,
                    ':ch' => $channel,
                    ':d'  => $text
                ]);
            }

            // Redirect: internal uses friend_code if available; public uses email
            $redirectReply = ($channel === 'user_admin')
                ? $peerDisplay
                : ($peerFriendCode !== '' ? $peerFriendCode : $peerUsername);

            header("Location: sendreply.php?reply=" . urlencode($redirectReply));
            exit;

        } catch (PDOException $e) {
            $error = "DB error: " . $e->getMessage();
        }
    }
}

// -----------------------------
// Load chat history
// -----------------------------
$rows = [];
try {
    if ($channel === 'user_admin') {
        $stmt = $dbh->prepare("
            SELECT id, sender, receiver, feedbackdata, created_at
            FROM feedback
            WHERE channel='user_admin'
              AND (
                    (sender='Admin' AND receiver=:u1)
                 OR (sender=:u2 AND receiver='Admin')
              )
            ORDER BY created_at ASC
        ");
        $stmt->execute([':u1'=>$peerDisplay, ':u2'=>$peerDisplay]);
    } else {
        $stmt = $dbh->prepare("
            SELECT id, sender, receiver, feedbackdata, created_at
            FROM feedback
            WHERE channel = :ch
              AND (
                    (sender=:me AND receiver=:peer)
                 OR (sender=:peer2 AND receiver=:me2)
              )
            ORDER BY created_at ASC
        ");
        $stmt->execute([
            ':ch'    => $channel,
            ':me'    => $meUsername,
            ':peer'  => $peerUsername,
            ':peer2' => $peerUsername,
            ':me2'   => $meUsername
        ]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "DB error: " . $e->getMessage();
}

// Helper: label each message sender
function senderLabel(string $sender, string $meUsername, string $peerLabel, string $channel): string {
    if ($sender === 'Admin') {
        // In admin chat, Admin could be "You" if you're the admin user sending
        return 'You';
    }
    if ($sender === $meUsername) return 'You';
    return $peerLabel;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .chat-wrap{max-height:55vh;overflow:auto;padding:15px;background:#f7f7f7;border:1px solid #ddd;border-radius:6px;}
    .row-msg{display:flex;width:100%;margin:6px 0;}
    .row-left{justify-content:flex-start;}
    .row-right{justify-content:flex-end;}
    .bubble{display:inline-block;padding:10px 12px;border-radius:14px;max-width:75%;word-wrap:break-word;background:#eee;border:1px solid #e5e5e5;}
    .bubble-me{background:#dff1ff;border-color:#cbe8ff;}
    .meta{font-size:12px;color:#777;margin-top:2px;}
    .form-box{margin-top:15px;background:#fff;border:1px solid #ddd;border-radius:6px;padding:15px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">
    
    <strong><?php echo htmlentities($peerHeaderName); ?></strong>  • 
    <?php echo htmlentities($peerHeaderRole); ?>
  </h2>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>

  <div id="chatBox" class="chat-wrap">
    <?php if (empty($rows)): ?>
      <div class="alert alert-info">No messages yet.</div>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <?php
          $sender = (string)($r['sender'] ?? '');
          $isMe = ($sender === $meUsername) || ($sender === 'Admin'); // Admin is "You" in this admin view
          $rowClass  = $isMe ? 'row-right' : 'row-left';
          $bubbleCls = $isMe ? 'bubble bubble-me' : 'bubble';
          $who = senderLabel($sender, $meUsername, $peerHeaderName, $channel);
        ?>
        <div class="row-msg <?php echo $rowClass; ?>">
          <div class="<?php echo $bubbleCls; ?>">
            <?php echo safe_text($r['feedbackdata']); ?>
            <div class="meta"><?php echo htmlentities($who); ?> • <?php echo htmlentities(fmt_dt($r['created_at'] ?? null)); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="form-box">
    <form method="post" autocomplete="off">
      <div class="row">
        <div class="col-md-9">
          <textarea name="message" class="form-control" rows="3" placeholder="Type your message..."></textarea>
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary btn-block" type="submit" name="send">
            <i class="fa fa-send"></i> Send
          </button>
        </div>
      </div>
    </form>
  </div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
(function(){
  const box = document.getElementById('chatBox');
  if (box) box.scrollTop = box.scrollHeight;
})();
</script>

</body>
</html>
