<?php
// /Business_only3/admin/sendreply.php

require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$msg = '';
$error = '';

// -----------------------------
// WHO AM I?
// -----------------------------
$me   = myUsername();   // username from session
$role = myRoleId();     // 1 Admin, 2 Manager, 3 Gospel, 4 Staff

if ($me === '' || !$role) {
    die("Invalid session.");
}

// -----------------------------
// TARGET (reply=...)
// - can be user email (public user) OR admin email OR admin username
// -----------------------------
$replyTo = trim(urldecode($_GET['reply'] ?? ''));
if ($replyTo === '') die("Missing reply target.");

$isEmailTarget = (strpos($replyTo, '@') !== false);

// -----------------------------
// HELPERS
// -----------------------------
function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : '';
}
function safe_text($txt) {
    return nl2br(htmlentities($txt ?? ''));
}

/**
 * Find admin account by username OR email (fixed placeholders)
 */
function getAdminByUsernameOrEmail(PDO $dbh, string $value) {
    $st = $dbh->prepare("
        SELECT idadmin, username, email, role, status
        FROM admin
        WHERE username = :u OR email = :e
        LIMIT 1
    ");
    $st->execute([
        ':u' => $value,
        ':e' => $value
    ]);
    return $st->fetch(PDO::FETCH_ASSOC);
}


/**
 * Return the admin "receiver key" we should use for INTERNAL notifications.
 * We decided: internal notifications use username.
 */
function notifyInternal(PDO $dbh, string $fromUsername, string $toUsername) {
    $st = $dbh->prepare("
        INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
        VALUES (:u, :r, 'New chat message', 0)
    ");
    $st->execute([
        ':u' => $fromUsername,
        ':r' => $toUsername
    ]);
}

// -----------------------------
// Decide MODE:
// A) user_admin (Admin <-> Public User email)  [only when replyTo is NOT an admin email]
// B) internal chat (Admin/Manager/Staff)       [replyTo is admin username OR admin email]
// -----------------------------
$channel  = '';
$mySender = '';     // stored in feedback.sender
$peerKey  = '';     // stored in feedback.receiver (for internal) OR user email (for user_admin)
$peerShow = $replyTo;

$adminRow = null;
if ($isEmailTarget) {
    // If this email belongs to an admin account => treat as INTERNAL chat
    $adminRow = getAdminByUsernameOrEmail($dbh, $replyTo);
}

// CASE 1: Admin <-> Public User (email)
// Only when:
// - you are Admin
// - replyTo is an email
// - that email is NOT an admin email
if ($role === 1 && $isEmailTarget && !$adminRow) {
    $channel  = 'user_admin';
    $mySender = 'Admin';
    $peerKey  = $replyTo; // user email
}
// CASE 2: Internal chat (Admin/Manager/Staff)
// - replyTo can be username OR admin email
else {
    // block manager/staff from public user email threads
    if ($isEmailTarget && !$adminRow) {
        die("Managers/Staff cannot access user chats.");
    }

    // normalize internal target to admin username
    if ($adminRow) {
        $peerUsername = $adminRow['username'];
        $peerRole     = (int)$adminRow['role'];
    } else {
        // replyTo is username
        $adminRow2 = getAdminByUsernameOrEmail($dbh, $replyTo);
        if (!$adminRow2) die("Invalid username/email target.");
        $peerUsername = $adminRow2['username'];
        $peerRole     = (int)$adminRow2['role'];
    }

    // ✅ allow Admin<->Manager and Admin<->Staff
    $isAdminToManager = ($role === 1 && $peerRole === 2) || ($role === 2 && $peerRole === 1);
    $isAdminToStaff   = ($role === 1 && $peerRole === 4) || ($role === 4 && $peerRole === 1);

    if ($isAdminToManager) {
        $channel = 'admin_manager';
    } elseif ($isAdminToStaff) {
        $channel = 'admin_staff';
    } else {
        die("This chat pair is not allowed.");
    }

    $mySender = $me;           // my username
    $peerKey  = $peerUsername; // peer username
    $peerShow = $peerUsername;
}

// -----------------------------
// Mark unread FROM peer TO me as read (open thread)
// -----------------------------
try {
    if ($channel === 'user_admin') {
        // user -> Admin inbox
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE sender = :peer
              AND receiver = 'Admin'
              AND channel = 'user_admin'
              AND is_read = 0
        ");
        $mk->execute([':peer' => $peerKey]);
    } else {
        // internal -> me (username)
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE sender = :peer
              AND receiver = :me
              AND channel = :ch
              AND is_read = 0
        ");
        $mk->execute([':peer' => $peerKey, ':me' => $me, ':ch' => $channel]);
    }
} catch (Throwable $e) {
    // ignore
}

// -----------------------------
// SEND MESSAGE
// -----------------------------
if (isset($_POST['send'])) {
    $text = trim($_POST['message'] ?? '');

    // attachment optional
    $attachment = null;
    $folder = __DIR__ . "/../attachment/";
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

            if (move_uploaded_file($file_loc, $folder . $final_file)) {
                $attachment = $final_file;
            } else {
                $error = "Attachment upload failed.";
            }
        }
    }

    if ($error === '' && $text === '' && !$attachment) {
        $error = "Message cannot be empty (add text or attachment).";
    }

    if ($error === '') {
        try {
            if ($channel === 'user_admin') {
                // Admin -> User email
                $stmt = $dbh->prepare("
                    INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read)
                    VALUES ('Admin', :receiver, 'user_admin', 'Chat Reply', :data, :attachment, 0)
                ");
                $stmt->execute([
                    ':receiver'   => $peerKey,
                    ':data'       => $text,
                    ':attachment' => $attachment
                ]);

                // notify USER email
                $stmtNoti = $dbh->prepare("
                    INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
                    VALUES ('Admin', :r, 'New chat message', 0)
                ");
                $stmtNoti->execute([':r' => $peerKey]);

            } else {
                // Internal username chat
                $stmt = $dbh->prepare("
                    INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read)
                    VALUES (:sender, :receiver, :ch, 'Chat', :data, :attachment, 0)
                ");
                $stmt->execute([
                    ':sender'     => $mySender,
                    ':receiver'   => $peerKey,
                    ':ch'         => $channel,
                    ':data'       => $text,
                    ':attachment' => $attachment
                ]);

                // notify peer username
                notifyInternal($dbh, $mySender, $peerKey);
            }

            header("Location: sendreply.php?reply=" . urlencode($peerShow));
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
                    (sender = :userEmail AND receiver = 'Admin')
                 OR (sender = 'Admin' AND receiver = :userEmail2)
              )
            ORDER BY created_at ASC
        ");
        $stmt->execute([
            ':userEmail'  => $peerKey,
            ':userEmail2' => $peerKey
        ]);
    } else {
        $stmt = $dbh->prepare("
            SELECT id, sender, receiver, feedbackdata, attachment, created_at
            FROM feedback
            WHERE channel = :ch
              AND (
                    (sender = :me AND receiver = :peer)
                 OR (sender = :peer2 AND receiver = :me2)
              )
            ORDER BY created_at ASC
        ");
        $stmt->execute([
            ':ch'    => $channel,
            ':me'    => $me,
            ':peer'  => $peerKey,
            ':peer2' => $peerKey,
            ':me2'   => $me
        ]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .chat-wrap{
      max-height:50vh; overflow:auto; padding:15px;
      background:#f7f7f7; border:1px solid #ddd; border-radius:6px;
    }
    .row-msg{ display:flex; width:100%; margin:6px 0; }
    .row-left{ justify-content:flex-start; }
    .row-right{ justify-content:flex-end; }

    .bubble{
      display:inline-block; padding:10px 12px; border-radius:14px;
      max-width:75%; word-wrap:break-word;
      background:#eee; border:1px solid #e5e5e5;
    }
    .bubble-me{ background:#dff1ff; border-color:#cbe8ff; }

    .meta{ font-size:12px; color:#777; margin-top:2px; }
    .attach a{ font-size:12px; }

    .form-box{
      margin-top:15px; background:#fff; border:1px solid #ddd;
      border-radius:6px; padding:15px;
    }

    .text-muted{ color:#777 !important; }
    textarea.form-control{ background:#fff !important; color:#000 !important; }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">
    Chat with: <strong><?php echo htmlentities($peerShow); ?></strong>
    <small style="margin-left:10px;color:#888;">(<?php echo htmlentities($channel); ?>)</small>
  </h2>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <div id="chatBox" class="chat-wrap">
    <?php if (empty($rows)): ?>
      <div class="alert alert-info">No messages yet.</div>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <?php
          // Blue = sender (me/right). Grey = receiver (peer/left)
          $isMe = (strcasecmp($r['sender'], $mySender) === 0);
          $rowClass  = $isMe ? 'row-right' : 'row-left';
          $bubbleCls = $isMe ? 'bubble bubble-me' : 'bubble';
          $who = $isMe ? 'You' : $r['sender'];
        ?>
        <div class="row-msg <?php echo $rowClass; ?>">
          <div class="<?php echo $bubbleCls; ?>">
            <?php echo safe_text($r['feedbackdata']); ?>

            <?php if (!empty($r['attachment'])): ?>
              <div class="attach" style="margin-top:8px;">
                <i class="fa fa-paperclip"></i>
                <a target="_blank" href="../attachment/<?php echo urlencode($r['attachment']); ?>">
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
          <textarea id="chatInput" name="message" class="form-control" rows="5"
            placeholder="Type your message..."></textarea>
        </div>

        <div class="col-md-4">
          <input type="file" name="attachment" class="form-control">
          <br/>
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

  const form = document.getElementById('chatForm');
  form.addEventListener('submit', function(e){
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
