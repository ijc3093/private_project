<?php
// /Business_only3/sendreply_user.php

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meEmail = myUserEmail();
$meId    = myUserId();
$meRole  = myUserRoleId();

if ($meEmail === '' || $meId <= 0) die("Invalid session.");

function fmt_dt($dt) { return $dt ? date('M d, Y h:i A', strtotime($dt)) : ''; }
function safe_text($txt) { return nl2br(htmlentities($txt ?? '')); }
function isEmailStr($s): bool { return (strpos($s, '@') !== false); }

// -----------------------------
// TARGET: reply can be friend_code OR email OR "Admin"
// -----------------------------
$replyRaw = trim(urldecode($_GET['reply'] ?? ''));
if ($replyRaw === '') die("Missing reply target.");

// Normalize support center
if (strcasecmp($replyRaw, 'admin') === 0 || strcasecmp($replyRaw, 'support') === 0) {
    $replyRaw = 'Admin';
}

// We'll resolve to:
// - $channel: user_admin OR user_user
// - $peerEmail: email (for user_user) OR user email (for user_admin receiver)
// - $peerLabel: display label for UI
$channel   = '';
$peerEmail = '';
$peerLabel = '';

// -----------------------------
// Helper: get contact label
// -----------------------------
function getPeerLabel(PDO $dbh, int $meId, string $peerEmail): string {
    // Try contact display_name
    $st = $dbh->prepare("
        SELECT uc.display_name
        FROM user_contacts uc
        JOIN users u ON u.id = uc.friend_user_id
        WHERE uc.owner_user_id = :meId
          AND u.email = :email
        LIMIT 1
    ");
    $st->execute([':meId'=>$meId, ':email'=>$peerEmail]);
    $name = $st->fetchColumn();
    if ($name) return (string)$name;

    // Try friend_code
    $st2 = $dbh->prepare("SELECT friend_code FROM users WHERE email = :email LIMIT 1");
    $st2->execute([':email'=>$peerEmail]);
    $code = $st2->fetchColumn();
    if ($code) return (string)$code;

    return $peerEmail;
}

// -----------------------------
// Resolve target to admin or user
// -----------------------------
if ($replyRaw === 'Admin') {
    $channel = 'user_admin';
    $peerLabel = 'Support Center';
} else {

    // If user typed email, use it. If not, treat as friend_code.
    if (isEmailStr($replyRaw)) {
        $peerEmail = $replyRaw;
    } else {
        // friend_code -> email
        $st = $dbh->prepare("SELECT email FROM users WHERE friend_code = :c LIMIT 1");
        $st->execute([':c' => $replyRaw]);
        $peerEmail = (string)($st->fetchColumn() ?: '');
        if ($peerEmail === '') die("Friend code not found.");
    }

    // Check if email belongs to an admin
    $stAdmin = $dbh->prepare("SELECT 1 FROM admin WHERE email = :e AND status=1 LIMIT 1");
    $stAdmin->execute([':e' => $peerEmail]);
    $isAdminEmail = (bool)$stAdmin->fetchColumn();

    if ($isAdminEmail) {
        $channel = 'user_admin';
        $peerLabel = 'Support Center';
    } else {
        // Must be active user + same role
        $stUser = $dbh->prepare("SELECT id, email, role, status FROM users WHERE email = :e LIMIT 1");
        $stUser->execute([':e' => $peerEmail]);
        $peer = $stUser->fetch(PDO::FETCH_ASSOC);

        if (!$peer) die("Recipient user not found.");
        if ((int)$peer['status'] !== 1) die("Recipient user is inactive.");

        $peerRole = (int)($peer['role'] ?? 0);
        if ($meRole <= 0 || $peerRole <= 0) die("User roles not configured in session/database.");
        if ($meRole !== $peerRole) die("You can only chat with users in the same role.");

        $channel = 'user_user';
        $peerLabel = getPeerLabel($dbh, $meId, $peerEmail);
    }
}

// -----------------------------
// Mark unread FROM peer TO me as read (open thread)
// -----------------------------
try {
    if ($channel === 'user_admin') {
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE channel = 'user_admin'
              AND sender = 'Admin'
              AND receiver = :me
              AND is_read = 0
        ");
        $mk->execute([':me' => $meEmail]);
    } else {
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE channel = 'user_user'
              AND sender = :peer
              AND receiver = :me
              AND is_read = 0
        ");
        $mk->execute([':peer' => $peerEmail, ':me' => $meEmail]);
    }
} catch (Throwable $e) {}

// -----------------------------
// SEND MESSAGE
// -----------------------------
$error = '';
if (isset($_POST['send'])) {
    $text = trim($_POST['message'] ?? '');

    $attachment = null;
    $folder = __DIR__ . "/attachment/";
    if (!is_dir($folder)) mkdir($folder, 0755, true);

    if (!empty($_FILES['attachment']['name'])) {
        $file     = $_FILES['attachment']['name'];
        $file_loc = $_FILES['attachment']['tmp_name'];
        $ext      = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','pdf','doc','docx'];

        if (!in_array($ext, $allowed, true)) {
            $error = "Invalid attachment type.";
        } else {
            $base = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($file, PATHINFO_FILENAME));
            $final_file = strtolower($base . '-' . time() . '.' . $ext);

            if (move_uploaded_file($file_loc, $folder . $final_file)) $attachment = $final_file;
            else $error = "Attachment upload failed.";
        }
    }

    if ($error === '' && $text === '' && !$attachment) {
        $error = "Message cannot be empty (add text or attachment).";
    }

    if ($error === '') {
        try {
            if ($channel === 'user_admin') {
                // User -> Admin
                $stmt = $dbh->prepare("
                    INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read)
                    VALUES (:sender, 'Admin', 'user_admin', 'User Chat', :data, :attachment, 0)
                ");
                $stmt->execute([
                    ':sender'     => $meEmail,
                    ':data'       => $text,
                    ':attachment' => $attachment
                ]);

                // notify Admin (receiver key 'Admin')
                $stmtNoti = $dbh->prepare("
                    INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
                    VALUES (:u, 'Admin', 'New chat message', 0)
                ");
                $stmtNoti->execute([':u' => $meEmail]);

            } else {
                // User -> User
                $stmt = $dbh->prepare("
                    INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read)
                    VALUES (:sender, :receiver, 'user_user', 'User Chat', :data, :attachment, 0)
                ");
                $stmt->execute([
                    ':sender'     => $meEmail,
                    ':receiver'   => $peerEmail,
                    ':data'       => $text,
                    ':attachment' => $attachment
                ]);

                $stmtNoti = $dbh->prepare("
                    INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
                    VALUES (:u, :r, 'New chat message', 0)
                ");
                $stmtNoti->execute([':u'=>$meEmail, ':r'=>$peerEmail]);
            }

            // Keep the same reply string the user typed
            header("Location: sendreply_user.php?reply=" . urlencode($replyRaw === 'Admin' ? 'Admin' : $replyRaw));
            exit;

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// -----------------------------
// LOAD CHAT HISTORY
// -----------------------------
$rows = [];
try {
    if ($channel === 'user_admin') {
        $stmt = $dbh->prepare("
            SELECT id, sender, receiver, feedbackdata, attachment, created_at
            FROM feedback
            WHERE channel = 'user_admin'
              AND (
                    (sender = :me AND receiver = 'Admin')
                 OR (sender = 'Admin' AND receiver = :me2)
              )
            ORDER BY created_at ASC
        ");
        $stmt->execute([':me'=>$meEmail, ':me2'=>$meEmail]);
    } else {
        $stmt = $dbh->prepare("
            SELECT id, sender, receiver, feedbackdata, attachment, created_at
            FROM feedback
            WHERE channel = 'user_user'
              AND (
                    (sender = :me AND receiver = :peer)
                 OR (sender = :peer2 AND receiver = :me2)
              )
            ORDER BY created_at ASC
        ");
        $stmt->execute([':me'=>$meEmail, ':peer'=>$peerEmail, ':peer2'=>$peerEmail, ':me2'=>$meEmail]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Chat</title>

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
    Chat with: <strong><?php echo htmlentities($peerLabel ?: $replyRaw); ?></strong>
    <small style="margin-left:10px;color:#888;">(<?php echo htmlentities($channel); ?>)</small>
  </h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlentities($error); ?></div>
  <?php endif; ?>

  <div id="chatBox" class="chat-wrap">
    <?php if (empty($rows)): ?>
      <div class="alert alert-info">No messages yet.</div>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <?php
          $isMe = (strcasecmp($r['sender'], $meEmail) === 0);
          $rowClass  = $isMe ? 'row-right' : 'row-left';
          $bubbleCls = $isMe ? 'bubble bubble-me' : 'bubble';
          $who = $isMe ? 'You' : ($r['sender'] === 'Admin' ? 'Support Center' : $r['sender']);
        ?>
        <div class="row-msg <?php echo $rowClass; ?>">
          <div class="<?php echo $bubbleCls; ?>">
            <?php echo safe_text($r['feedbackdata']); ?>

            <?php if (!empty($r['attachment'])): ?>
              <div style="margin-top:8px;">
                <i class="fa fa-paperclip"></i>
                <a target="_blank" href="attachment/<?php echo urlencode($r['attachment']); ?>">
                  <?php echo htmlentities($r['attachment']); ?>
                </a>
              </div>
            <?php endif; ?>

            <div class="meta"><?php echo htmlentities($who); ?> • <?php echo htmlentities(fmt_dt($r['created_at'])); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="form-box">
    <form id="chatForm" method="post" enctype="multipart/form-data" autocomplete="off">
      <div class="row">
        <div class="col-md-8">
          <textarea id="chatInput" name="message" class="form-control" rows="4" placeholder="Type your message..."></textarea>
        </div>
        <div class="col-md-4">
          <input type="file" name="attachment" class="form-control">
          <br>
          <button type="submit" name="send" class="btn btn-primary btn-block">
            <i class="fa fa-send"></i> Send
          </button>
          <small class="text-muted">Allowed: jpg, jpeg, png, pdf, doc, docx</small>
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
  function scrollChatToBottom(force=false){
    const box = document.getElementById('chatBox');
    if (!box) return;
    const nearBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 120;
    if (force || nearBottom) box.scrollTop = box.scrollHeight;
  }

  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

  window.addEventListener('load', function(){
    scrollChatToBottom(true);
    setTimeout(() => scrollChatToBottom(true), 50);
    setTimeout(() => scrollChatToBottom(true), 200);
  });

  document.getElementById('chatForm').addEventListener('submit', function(e){
    const msg = (document.getElementById('chatInput').value || '').trim();
    const file = document.querySelector('input[type="file"]').files.length;
    if (!msg && !file) {
      e.preventDefault();
      alert('Message cannot be empty (add text or attachment).');
    }
  });
})();
</script>

</body>
</html>
