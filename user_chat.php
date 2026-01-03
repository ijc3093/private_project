<?php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/identity_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meEmail = userEmail();
$meId = userId();

$to = trim(urldecode($_GET['to'] ?? ''));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    die("Missing or invalid recipient.");
}
if (strcasecmp($to, $meEmail) === 0) die("You cannot message yourself.");

// Optional: ensure recipient exists
$chk = $dbh->prepare("SELECT id, email FROM users WHERE email = :e LIMIT 1");
$chk->execute([':e' => $to]);
$recipient = $chk->fetch(PDO::FETCH_ASSOC);
if (!$recipient) die("Recipient not found.");

$channel = 'user_user';
$error = '';

// Mark unread from peer to me as read
try {
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read = 1, read_at = NOW()
        WHERE channel = 'user_user'
          AND sender = :peer
          AND receiver = :me
          AND is_read = 0
    ");
    $mk->execute([':peer' => $to, ':me' => $meEmail]);
} catch (Throwable $e) {}

// Send message
if (isset($_POST['send'])) {
    $text = trim($_POST['message'] ?? '');

    if ($text === '') {
        $error = "Message cannot be empty.";
    } else {
        $st = $dbh->prepare("
            INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, is_read)
            VALUES (:s, :r, 'user_user', 'Chat', :m, 0)
        ");
        $st->execute([
            ':s' => $meEmail,
            ':r' => $to,
            ':m' => $text
        ]);

        // Notification (optional) — notify receiver email
        $noti = $dbh->prepare("
            INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
            VALUES (:u, :r, 'New chat message', 0)
        ");
        $noti->execute([
            ':u' => $meEmail,
            ':r' => $to
        ]);

        header("Location: user_chat.php?to=" . urlencode($to));
        exit;
    }
}

// Load chat history
$st = $dbh->prepare("
    SELECT id, sender, receiver, feedbackdata, created_at
    FROM feedback
    WHERE channel = 'user_user'
      AND (
            (sender = :me AND receiver = :peer)
         OR (sender = :peer2 AND receiver = :me2)
      )
    ORDER BY created_at ASC
");
$st->execute([
    ':me' => $meEmail,
    ':peer' => $to,
    ':peer2' => $to,
    ':me2' => $meEmail
]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function safe_text($txt) {
    return nl2br(htmlentities($txt ?? ''));
}
function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : '';
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat</title>

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .chat-wrap{max-height:55vh;overflow:auto;padding:15px;background:#f7f7f7;border:1px solid #ddd;border-radius:8px;}
    .row-msg{display:flex;width:100%;margin:6px 0;}
    .row-left{justify-content:flex-start;}
    .row-right{justify-content:flex-end;}
    .bubble{display:inline-block;padding:10px 12px;border-radius:14px;max-width:75%;word-wrap:break-word;background:#eee;border:1px solid #e5e5e5;}
    .bubble-me{background:#dff1ff;border-color:#cbe8ff;}
    .meta{font-size:12px;color:#777;margin-top:2px;}
    .form-box{margin-top:12px;background:#fff;border:1px solid #ddd;border-radius:8px;padding:12px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">
    Chat with: <strong><?php echo htmlentities($to); ?></strong>
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
          $who = $isMe ? 'You' : 'Friend';
        ?>
        <div class="row-msg <?php echo $rowClass; ?>">
          <div class="<?php echo $bubbleCls; ?>">
            <?php echo safe_text($r['feedbackdata']); ?>
            <div class="meta"><?php echo htmlentities($who); ?> • <?php echo htmlentities(fmt_dt($r['created_at'])); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="form-box">
    <form method="post" autocomplete="off">
      <div class="row">
        <div class="col-md-9">
          <textarea name="message" class="form-control" rows="3" placeholder="Type message..."></textarea>
        </div>
        <div class="col-md-3">
          <button type="submit" name="send" class="btn btn-primary btn-block" style="height:100%;">
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
})();
</script>
</body>
</html>
