<?php
// /Business_only3/admin/sendreply.php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

function h($s){ return htmlentities((string)$s, ENT_QUOTES, 'UTF-8'); }
function safe_text($txt){ return nl2br(h($txt ?? '')); }
function fmt_dt($dt){ return $dt ? date('M d, Y h:i A', strtotime((string)$dt)) : ''; }

$meUser = myUsername();   // internal identity is username
$meId   = myAdminId();
$meRole = myRoleId();

if ($meUser === '' || $meId <= 0 || $meRole <= 0) {
    die("Invalid session.");
}

/**
 * Resolve reply target:
 * - if email is provided, find admin by email => return username
 * - else treat as friend_code OR username
 */
function resolvePeerUsername(PDO $dbh, string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') return ['ok'=>false, 'error'=>'Missing reply target.'];

    // email -> username
    if (strpos($raw, '@') !== false) {
        if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) return ['ok'=>false, 'error'=>'Invalid peer email.'];
        $st = $dbh->prepare("SELECT username, fullname, friend_code, role, status FROM admin WHERE email = :e LIMIT 1");
        $st->execute([':e'=>$raw]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['ok'=>false,'error'=>'Peer not found (email).'];
        return ['ok'=>true,'username'=>(string)$row['username'], 'row'=>$row];
    }

    // friend_code -> username
    $st = $dbh->prepare("SELECT username, fullname, friend_code, role, status FROM admin WHERE UPPER(friend_code) = :c LIMIT 1");
    $st->execute([':c'=>strtoupper($raw)]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) return ['ok'=>true,'username'=>(string)$row['username'], 'row'=>$row];

    // username -> username
    $st2 = $dbh->prepare("SELECT username, fullname, friend_code, role, status FROM admin WHERE username = :u LIMIT 1");
    $st2->execute([':u'=>$raw]);
    $row2 = $st2->fetch(PDO::FETCH_ASSOC);
    if ($row2) return ['ok'=>true,'username'=>(string)$row2['username'], 'row'=>$row2];

    return ['ok'=>false,'error'=>'Target not found (friend code/username/email).'];
}

$replyRaw = trim((string)($_GET['reply'] ?? ''));
$res = resolvePeerUsername($dbh, $replyRaw);
if (!$res['ok']) die(h($res['error'] ?? 'Invalid target'));

$peerUser = trim((string)$res['username']);
if ($peerUser === '') die("Missing peer username.");
if (strcasecmp($peerUser, $meUser) === 0) die("You cannot message yourself.");

// peer info
$peerRow = $res['row'] ?? [];
if (!$peerRow) {
    $chk = $dbh->prepare("SELECT username, fullname, friend_code, role, status FROM admin WHERE username=:u LIMIT 1");
    $chk->execute([':u'=>$peerUser]);
    $peerRow = $chk->fetch(PDO::FETCH_ASSOC) ?: [];
}
if (!$peerRow) die("Peer not found.");
if ((int)($peerRow['status'] ?? 0) !== 1) die("Peer is inactive.");

$peerRole = (int)($peerRow['role'] ?? 0);
$channel  = channelForAdminRoles($meRole, $peerRole);
if ($channel === '') die("You cannot chat with this role.");

// display name
$peerDisplay = trim((string)($peerRow['fullname'] ?? ''));
if ($peerDisplay === '') $peerDisplay = $peerUser;
$peerCode = (string)($peerRow['friend_code'] ?? '');
if ($peerCode !== '') $peerDisplay .= " • " . $peerCode;

// mark unread peer->me read on open
try {
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read = 1, read_at = NOW()
        WHERE channel = :ch
          AND sender = :peer
          AND receiver = :me
          AND is_read = 0
    ");
    $mk->execute([':ch'=>$channel, ':peer'=>$peerUser, ':me'=>$meUser]);
} catch (Throwable $e) {}

// load history
$rows = [];
try {
    $st = $dbh->prepare("
        SELECT id, sender, receiver, feedbackdata, attachment, created_at
        FROM feedback
        WHERE channel = :ch
          AND (
                (sender = :me AND receiver = :peer)
             OR (sender = :peer2 AND receiver = :me2)
          )
        ORDER BY id ASC
    ");
    $st->execute([
        ':ch'=>$channel,
        ':me'=>$meUser, ':peer'=>$peerUser,
        ':peer2'=>$peerUser, ':me2'=>$meUser
    ]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Internal Chat</title>

  <link rel="stylesheet" href="../css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/style.css">

  <style>
    .chat-wrap{max-height:55vh;overflow:auto;padding:15px;background:#f7f7f7;border:1px solid #ddd;border-radius:8px;}
    .row-msg{display:flex;width:100%;margin:6px 0;}
    .row-left{justify-content:flex-start;}
    .row-right{justify-content:flex-end;}
    .bubble{display:inline-block;padding:10px 12px;border-radius:14px;max-width:75%;word-wrap:break-word;background:#eee;border:1px solid #e5e5e5;}
    .bubble-me{background:#dff1ff;border-color:#cbe8ff;}
    .meta{font-size:12px;color:#777;margin-top:6px;}
    .form-box{margin-top:12px;background:#fff;border:1px solid #ddd;border-radius:8px;padding:12px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
    <h3>Chat with: <b><?php echo h($peerDisplay); ?></b></h3>
    <a class="btn btn-default" href="feedback.php?view=internal"><i class="fa fa-arrow-left"></i> Back</a>
  </div>

  <input type="hidden" id="peerUser" value="<?php echo h($peerUser); ?>">
  <input type="hidden" id="meUser" value="<?php echo h($meUser); ?>">

  <div id="chatBox" class="chat-wrap">
    <?php if (empty($rows)): ?>
      <div class="alert alert-info">No messages yet.</div>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <?php
          $isMe = (strcasecmp((string)$r['sender'], $meUser) === 0);
          $rowClass  = $isMe ? 'row-right' : 'row-left';
          $bubbleCls = $isMe ? 'bubble bubble-me' : 'bubble';
          $who = $isMe ? 'You' : $peerDisplay;
        ?>
        <div class="row-msg <?php echo $rowClass; ?>" data-msg-id="<?php echo (int)$r['id']; ?>">
          <div class="<?php echo $bubbleCls; ?>">
            <?php echo safe_text($r['feedbackdata'] ?? ''); ?>

            <?php if (!empty($r['attachment'])): ?>
              <div style="margin-top:8px;">
                <i class="fa fa-paperclip"></i>
                <a target="_blank" href="../attachment/<?php echo urlencode((string)$r['attachment']); ?>">
                  <?php echo h($r['attachment']); ?>
                </a>
              </div>
            <?php endif; ?>

            <div class="meta"><?php echo h($who); ?> • <?php echo h(fmt_dt($r['created_at'] ?? null)); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="form-box">
    <form id="chatForm" method="post" enctype="multipart/form-data" autocomplete="off">
      <div class="row">
        <div class="col-md-8">
          <textarea id="chatInput" name="message" class="form-control" rows="4" placeholder="Type message..."></textarea>
        </div>
        <div class="col-md-4">
          <input id="chatFile" type="file" name="attachment" class="form-control">
          <br/>
          <button type="submit" class="btn btn-primary btn-block">
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

<script>
(function () {
  const chatBox  = document.getElementById('chatBox');
  const peerUser = document.getElementById('peerUser')?.value || '';
  const meUser   = document.getElementById('meUser')?.value || '';
  const form     = document.getElementById('chatForm');
  const input    = document.getElementById('chatInput');
  const fileInput= document.getElementById('chatFile');

  function scrollChatToBottom(force=false){
    if (!chatBox) return;
    const nearBottom = (chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight) < 120;
    if (force || nearBottom) chatBox.scrollTop = chatBox.scrollHeight;
  }

  function esc(s){
    return String(s ?? "").replace(/[&<>"']/g, c => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[c]));
  }

  let lastId = 0;
  if (chatBox) {
    chatBox.querySelectorAll('[data-msg-id]').forEach(el => {
      const id = Number(el.getAttribute('data-msg-id')) || 0;
      if (id > lastId) lastId = id;
    });
  }

  function renderMessage(m){
    const id = Number(m.id) || 0;
    if (id && id <= lastId) return;

    const sender = String(m.sender || '');
    const text   = String(m.feedbackdata || '');
    const attachment = m.attachment ? String(m.attachment) : '';

    const isMe = sender.toLowerCase() === meUser.toLowerCase();
    const rowClass  = isMe ? 'row-right' : 'row-left';
    const bubbleCls = isMe ? 'bubble bubble-me' : 'bubble';
    const who = isMe ? 'You' : 'Peer';

    const row = document.createElement('div');
    row.className = `row-msg ${rowClass}`;
    row.setAttribute('data-msg-id', String(id));

    const attachmentHtml = attachment
      ? `<div style="margin-top:8px;">
           <i class="fa fa-paperclip"></i>
           <a target="_blank" href="../attachment/${encodeURIComponent(attachment)}">${esc(attachment)}</a>
         </div>`
      : '';

    row.innerHTML = `
      <div class="${bubbleCls}">
        ${esc(text).replace(/\\n/g,'<br>')}
        ${attachmentHtml}
        <div class="meta">${esc(who)}</div>
      </div>
    `;

    chatBox.appendChild(row);
    if (id > lastId) lastId = id;
    scrollChatToBottom(false);
  }

  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  window.addEventListener('load', function(){
    scrollChatToBottom(true);
    setTimeout(() => scrollChatToBottom(true), 50);
    setTimeout(() => scrollChatToBottom(true), 200);
  });

  // instant send
  if (form) {
    form.addEventListener('submit', async function(e){
      e.preventDefault();

      const msg = (input?.value || '').trim();
      const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

      if (!msg && !hasFile) {
        alert('Message cannot be empty (add text or attachment).');
        return;
      }

      const fd = new FormData();
      fd.append('peer', peerUser);
      fd.append('message', msg);
      if (hasFile) fd.append('attachment', fileInput.files[0]);

      try {
        const res = await fetch('ajax/chat_send.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (!data.ok) {
          alert(data.error || 'Send failed.');
          return;
        }

        renderMessage(data.message);

        if (input) input.value = '';
        if (fileInput) fileInput.value = '';
      } catch (err) {
        alert('Network error sending message.');
      }
    });
  }

  // poll incoming messages (<1s)
  async function pollChat(){
    try {
      const url = `ajax/chat_poll.php?peer=${encodeURIComponent(peerUser)}&last_id=${encodeURIComponent(lastId)}`;
      const res = await fetch(url, { cache: 'no-store' });
      const data = await res.json();
      if (!data.ok || !Array.isArray(data.messages)) return;
      for (const m of data.messages) renderMessage(m);
    } catch (e) {}
  }

  if (chatBox && peerUser && meUser) {
    pollChat();
    setInterval(pollChat, 700);
  }
})();
</script>

</body>
</html>
