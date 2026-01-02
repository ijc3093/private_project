<?php
// /Business_only3/user_chat.php

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/includes/identity_user.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$meEmail = myUserKey();
$myRole  = myUserRoleId();

if ($meEmail === '' || $myRole <= 0) die("Invalid user session.");

$toRaw = trim(urldecode($_GET['to'] ?? ''));
if ($toRaw === '') die("Missing chat target.");

$isAdminChat = (strcasecmp($toRaw, 'Admin') === 0);
$isEmailTarget = (strpos($toRaw, '@') !== false);

$channel = '';
$peer    = ''; // peer identity used in DB

// Decide mode
if ($isAdminChat) {
    $channel = 'user_admin';
    $peer = 'Admin';
} else {
    if (!$isEmailTarget) die("Invalid target.");
    $peer = $toRaw;

    // Enforce same-role user chat
    $chk = $dbh->prepare("
        SELECT role, status
        FROM users
        WHERE email = :e
        LIMIT 1
    ");
    $chk->execute([':e' => $peer]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$row) die("User not found.");
    if ((int)$row['status'] !== 1) die("User inactive.");
    if ((int)$row['role'] !== $myRole) die("You can only chat with users in your same role.");

    $channel = 'user_user';
}

function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : '';
}
function safe_text($txt) {
    return nl2br(htmlentities($txt ?? ''));
}

// Mark unread incoming messages as read (when opening chat)
try {
    if ($channel === 'user_admin') {
        // Admin -> me
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
        // peer -> me
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE channel = 'user_user'
              AND sender = :peer
              AND receiver = :me
              AND is_read = 0
        ");
        $mk->execute([':peer' => $peer, ':me' => $meEmail]);
    }
} catch (Throwable $e) {}

// Send message
$error = '';
if (isset($_POST['send'])) {
    $text = trim($_POST['message'] ?? '');

    // optional attachment
    $attachment = null;
    $folder = __DIR__ . "/attachment/";
    if (!is_dir($folder)) @mkdir($folder, 0755, true);

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
                // user -> Admin (shared inbox)
                $st = $dbh->prepare("
                    INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read)
                    VALUES (:s, 'Admin', 'user_admin', 'Chat', :d, :a, 0)
                ");
                $st->execute([
                    ':s' => $meEmail,
                    ':d' => $text,
                    ':a' => $attachment
                ]);

                // notify admin shared key
                $nt = $dbh->prepare("
                    INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
                    VALUES (:u, 'Admin', 'New chat message', 0)
                ");
                $nt->execute([':u' => $meEmail]);

            } else {
                // user -> user
                $st = $dbh->prepare("
                    INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read)
                    VALUES (:s, :r, 'user_user', 'Chat', :d, :a, 0)
                ");
                $st->execute([
                    ':s' => $meEmail,
                    ':r' => $peer,
                    ':d' => $text,
                    ':a' => $attachment
                ]);

                // notify peer email
                $nt = $dbh->prepare("
                    INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
                    VALUES (:u, :r, 'New chat message', 0)
                ");
                $nt->execute([':u' => $meEmail, ':r' => $peer]);
            }

            header("Location: user_chat.php?to=" . urlencode($toRaw));
            exit;

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Load history
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
        $stmt->execute([':me' => $meEmail, ':me2' => $meEmail]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt->execute([
            ':me' => $meEmail,
            ':peer' => $peer,
            ':peer2' => $peer,
            ':me2' => $meEmail
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "DB error: " . $e->getMessage();
}

$pageTitle = $isAdminChat ? 'Chat with Admin' : ('Chat with ' . $peer);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlentities($pageTitle); ?></title>

  <link rel="stylesheet" href="admin/css/bootstrap.min.css">
  <link rel="stylesheet" href="admin/css/font-awesome.min.css">

  <style>
    body{background:#f5f6f7;}
    .wrap{max-width:1000px;margin:20px auto;padding:0 15px;}
    .chat-wrap{max-height:55vh;overflow:auto;padding:15px;background:#f7f7f7;border:1px solid #ddd;border-radius:8px;}
    .row-msg{display:flex;width:100%;margin:6px 0;}
    .row-left{justify-content:flex-start;}
    .row-right{justify-content:flex-end;}
    .bubble{padding:10px 12px;border-radius:14px;max-width:75%;word-wrap:break-word;background:#eee;border:1px solid #e5e5e5;}
    .bubble-me{background:#dff1ff;border-color:#cbe8ff;}
    .meta{font-size:12px;color:#777;margin-top:4px;}
    .form-box{margin-top:12px;background:#fff;border:1px solid #ddd;border-radius:8px;padding:12px;}
    textarea.form-control{background:#fff;color:#000;}
    .text-muted{color:#777!important;}
  </style>
</head>
<body>

<div class="wrap">
  <h3 style="margin-top:0;">
    <?php echo htmlentities($pageTitle); ?>
    <small class="text-muted" style="margin-left:8px;">(<?php echo htmlentities($channel); ?>)</small>
  </h3>

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
          $who = $isMe ? 'You' : $r['sender'];
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

  <p style="margin-top:10px;">
    <a class="btn btn-default" href="compose_user.php"><i class="fa fa-arrow-left"></i> Back</a>
  </p>
</div>

<script>
(function(){
  function scrollBottom(force=false){
    const box = document.getElementById('chatBox');
    if (!box) return;
    const nearBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 120;
    if (force || nearBottom) box.scrollTop = box.scrollHeight;
  }

  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

  window.addEventListener('load', function(){
    scrollBottom(true);
    setTimeout(() => scrollBottom(true), 50);
    setTimeout(() => scrollBottom(true), 200);
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

<script src="admin/js/jquery.min.js"></script>
<script src="admin/js/bootstrap.min.js"></script>
</body>
</html>
