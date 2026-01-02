<?php
// /Business_only3/user_sendreply.php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/identity_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$meEmail = myUserEmail();
$myRole  = myUserRoleId();
if ($meEmail === '' || !$myRole) die("Invalid session.");

$error = '';
$msg   = '';

$replyTo = trim(urldecode($_GET['reply'] ?? ''));
if ($replyTo === '') die("Missing reply target.");

$isAdminChat = ($replyTo === adminInboxKey());
$channel = $isAdminChat ? 'user_admin' : 'user_user';
$peerEmail = $isAdminChat ? adminInboxKey() : $replyTo;

// Validate user-user: must be email + same role + active
if (!$isAdminChat) {
    if (strpos($peerEmail, '@') === false) die("Invalid recipient.");

    $st = $dbh->prepare("SELECT id, name, email, role, status FROM users WHERE email = :e LIMIT 1");
    $st->execute([':e' => $peerEmail]);
    $peerRow = $st->fetch(PDO::FETCH_ASSOC);
    if (!$peerRow) die("Recipient not found.");
    if ((int)$peerRow['status'] !== 1) die("Recipient inactive.");
    if ((int)$peerRow['role'] !== $myRole) die("You can only chat with users in your same role.");
}

// Mark unread from peer -> me as read when opening
try {
    if ($isAdminChat) {
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE sender = 'Admin'
              AND receiver = :me
              AND channel = 'user_admin'
              AND is_read = 0
        ");
        $mk->execute([':me' => $meEmail]);
    } else {
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE sender = :peer
              AND receiver = :me
              AND channel = 'user_user'
              AND is_read = 0
        ");
        $mk->execute([':peer' => $peerEmail, ':me' => $meEmail]);
    }
} catch (Throwable $e) {}

// Send message
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
            // Insert message
            $stmt = $dbh->prepare("
                INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read)
                VALUES (:s, :r, :ch, 'Chat', :d, :a, 0)
            ");
            $stmt->execute([
                ':s'  => $meEmail,
                ':r'  => $peerEmail,
                ':ch' => $channel,
                ':d'  => $text,
                ':a'  => $attachment
            ]);

            // Notification to receiver
            if ($isAdminChat) {
                // receiver key is 'Admin' for admin-side inbox
                $controller->addNotification($meEmail, adminInboxKey(), 'New chat message');
            } else {
                // peer is an email => notification goes to that email
                $controller->addNotification($meEmail, $peerEmail, 'New chat message');
            }

            header("Location: user_sendreply.php?reply=" . urlencode($replyTo));
            exit;
        } catch (Throwable $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Load chat history
$rows = [];
try {
    if ($isAdminChat) {
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
            ':peer' => $peerEmail,
            ':peer2' => $peerEmail,
            ':me2' => $meEmail
        ]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = "Database error: " . $e->getMessage();
}

function fmt_dt($dt) { return $dt ? date('M d, Y h:i A', strtotime($dt)) : ''; }
function safe_text($txt) { return nl2br(htmlentities($txt ?? '')); }
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
    .chat-wrap{max-height:50vh;overflow:auto;padding:15px;background:#f7f7f7;border:1px solid #ddd;border-radius:6px;}
    .row-msg{display:flex;width:100%;margin:6px 0;}
    .row-left{justify-content:flex-start;}
    .row-right{justify-content:flex-end;}
    .bubble{display:inline-block;padding:10px 12px;border-radius:14px;max-width:75%;word-wrap:break-word;background:#eee;border:1px solid #e5e5e5;}
    .bubble-me{background:#dff1ff;border-color:#cbe8ff;}
    .meta{font-size:12px;color:#777;margin-top:2px;}
    .form-box{margin-top:15px;background:#fff;border:1px solid #ddd;border-radius:6px;padding:15px;}
    .text-muted{color:#777 !important;}
    textarea.form-control{background:#fff !important;color:#000 !important;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">
    Chat with: <strong><?php echo htmlentities($replyTo); ?></strong>
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
          <textarea id="chatInput" name="message" class="form-control" rows="5" placeholder="Type your message..."></textarea>
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
