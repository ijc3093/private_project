<?php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/admin/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$userEmail = $_SESSION['user_login'];  // logged-in user email
$receiver  = 'Admin';                 // ONLY chat with Admin

$msg = '';
$error = '';

// -----------------------------
// SEND (User -> Admin)
// -----------------------------
if (isset($_POST['send'])) {
    $text = trim($_POST['message'] ?? '');

    // ✅ Attachment upload (optional) - SAME as admin logic
    $attachment = null;
    $folder = __DIR__ . "/attachment/";

    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

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

    // ✅ Require at least text OR attachment
    if ($error === '' && $text === '' && !$attachment) {
        $error = "Message cannot be empty (add text or attachment).";
    }

    if ($error === '') {
        try {
            // chat message stored in feedback
            $st = $dbh->prepare("
                INSERT INTO feedback (sender, receiver, title, feedbackdata, attachment, is_read)
                VALUES (:sender, :receiver, :title, :data, :attachment, 0)
            ");
            $st->execute([
                ':sender'     => $userEmail,
                ':receiver'   => $receiver,
                ':title'      => 'Chat',
                ':data'       => $text,
                ':attachment' => $attachment
            ]);

            // notification for Admin
            $stN = $dbh->prepare("
                INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
                VALUES (:u, :r, :t, 0)
            ");
            $stN->execute([
                ':u' => $userEmail,
                ':r' => 'Admin',
                ':t' => 'New chat message'
            ]);

            header("Location: feedback.php?reply=Admin");
            exit;

        } catch (PDOException $e) {
            $error = "DB error: " . $e->getMessage();
        }
    }
}

// -----------------------------
// MARK Admin->User as read (when user opens chat)
// -----------------------------
try {
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read = 1, read_at = NOW()
        WHERE sender = :admin
          AND receiver = :me
          AND is_read = 0
    ");
    $mk->execute([
        ':admin' => 'Admin',
        ':me'    => $userEmail
    ]);
} catch (PDOException $e) {}

// -----------------------------
// LOAD CHAT HISTORY
// -----------------------------
$rows = [];
try {
    $st = $dbh->prepare("
        SELECT id, sender, receiver, feedbackdata, attachment, created_at
        FROM feedback
        WHERE (sender = :me AND receiver = :admin1)
           OR (sender = :admin2 AND receiver = :me2)
        ORDER BY created_at ASC
    ");
    $st->execute([
        ':me'     => $userEmail,
        ':admin1' => 'Admin',
        ':admin2' => 'Admin',
        ':me2'    => $userEmail
    ]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "DB error: " . $e->getMessage();
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
  <title>Chat with Admin</title>

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .chat-box{
      background:#fff;border:1px solid #ddd;border-radius:8px;
      padding:15px;height:380px;overflow:auto;
    }
    .msg-row{margin-bottom:12px;display:flex;}
    .msg-row.me{justify-content:flex-end;}
    .bubble{max-width:70%;padding:10px 12px;border-radius:12px;border:1px solid #e5e5e5;background:#f7f7f7;}
    .me .bubble{background:#dff0ff;border-color:#cfe8ff;}
    .meta{font-size:12px;opacity:.7;margin-top:4px;}
    .attach a{font-size:12px;}
    .errorWrap{padding:10px;background:#dd3d36;color:#fff;margin:0 0 15px;}

    /* ✅ same style as admin form-box */
    .form-box{
      margin-top:15px;border:1px solid #ddd;
      border-radius:6px;padding:15px;
    }
    .row-flex{display:flex;gap:12px;flex-wrap:wrap;}
    .col-left{flex:1 1 65%;min-width:260px;}
    .col-right{flex:1 1 30%;min-width:220px;}

    @media (max-width:768px){
      .col-left,.col-right{flex:1 1 100%;}
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">Chat with Admin</h2>

  <?php if ($error): ?>
    <div class="errorWrap"><?php echo htmlentities($error); ?></div>
  <?php endif; ?>

  <div class="chat-box" id="chatBox" data-last-id="<?php echo !empty($rows) ? (int)end($rows)['id'] : 0; ?>">
    <?php if (empty($rows)): ?>
      <div class="alert alert-info">No messages yet. Say hi 👋</div>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <?php $isMe = (strcasecmp($r['sender'], $userEmail) === 0); ?>
        <div class="msg-row <?php echo $isMe ? 'me' : 'them'; ?>">
          <div class="bubble">
            <div><?php echo nl2br(htmlentities($r['feedbackdata'] ?? '')); ?></div>

            <?php if (!empty($r['attachment'])): ?>
              <div class="attach" style="margin-top:8px;">
                <i class="fa fa-paperclip"></i>
                <a target="_blank" href="attachment/<?php echo urlencode($r['attachment']); ?>">
                  <?php echo htmlentities($r['attachment']); ?>
                </a>
              </div>
            <?php endif; ?>

            <div class="meta">
              <?php echo $isMe ? 'You' : 'Admin'; ?> • <?php echo htmlentities(fmt_dt($r['created_at'])); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ✅ FORM: Attachment on RIGHT of message -->
  <div class="form-box">
    <form method="post" enctype="multipart/form-data">
      <div class="row-flex">
        <div class="col-left">
          <div class="form-group" style="margin:0;">
            <!-- <label>New Message</label> -->
            <textarea name="message" class="form-control" rows="4" placeholder="Type your message..."></textarea>
          </div>
        </div>
        <div class="col-right">
          <div class="form-group" style="margin:0;">
            <!-- <label>Attachment (optional)</label> -->
            <input type="file" name="attachment" class="form-control">
            <small>Allowed: jpg, jpeg, png, pdf, doc, docx</small>
          </div>
          <button type="submit" name="send" class="btn btn-primary">
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
  if (!box) return;

  let lastId = parseInt(box.getAttribute('data-last-id') || '0', 10);

  function nearBottom(el){
    return (el.scrollHeight - el.scrollTop - el.clientHeight) < 120;
  }

  function esc(s){
    const div = document.createElement('div');
    div.textContent = s ?? '';
    return div.innerHTML;
  }

  function appendMsg(m){
    const isMe = (String(m.sender).toLowerCase() !== 'admin');
    const rowClass = isMe ? 'msg-row me' : 'msg-row them';
    const who = isMe ? 'You' : 'Admin';

    let attachHtml = '';
    if (m.attachment) {
      const file = encodeURIComponent(m.attachment);
      attachHtml = `
        <div class="attach" style="margin-top:8px;">
          <i class="fa fa-paperclip"></i>
          <a target="_blank" href="attachment/${file}">${esc(m.attachment)}</a>
        </div>`;
    }

    const html = `
      <div class="${rowClass}">
        <div class="bubble">
          ${esc(m.feedbackdata || '').replace(/\n/g,'<br>')}
          ${attachHtml}
          <div class="meta">${who} • ${esc(m.created_at || '')}</div>
        </div>
      </div>
    `;
    box.insertAdjacentHTML('beforeend', html);
  }

  async function poll(){
    const shouldScroll = nearBottom(box);

    try{
      const res = await fetch(`ajax/chat_poll.php?last_id=${lastId}`, { cache:'no-store' });
      const data = await res.json();
      if (!data.ok) return;

      (data.messages || []).forEach(m => {
        appendMsg(m);
        lastId = Math.max(lastId, parseInt(m.id, 10));
      });

      box.setAttribute('data-last-id', String(lastId));
      if (shouldScroll) box.scrollTop = box.scrollHeight;
    }catch(e){}
  }

  box.scrollTop = box.scrollHeight;
  poll();
  setInterval(poll, 2500);
})();
</script>

</body>
</html>
