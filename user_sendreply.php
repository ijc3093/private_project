<?php
// /Business_only3/user_sendreply.php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/user_identity.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$meEmail = userEmail();
$meId    = myUserId();
$myRole  = userRoleId();

if ($meEmail === '' || $myRole <= 0) {
    die("Invalid session.");
}

$error = '';
$msg   = '';

/**
 * Resolve reply param:
 * - if email => use email
 * - if friend_code => lookup users.email
 * NOTE: user can ONLY chat with user, no Admin.
 */
function resolvePeerEmail(PDO $dbh, string $replyRaw): array
{
    $replyRaw = trim($replyRaw);

    if ($replyRaw === '') {
        return ['ok' => false, 'error' => 'Missing reply target.'];
    }

    // If it's email
    if (strpos($replyRaw, '@') !== false) {
        if (!filter_var($replyRaw, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email.'];
        }
        return ['ok' => true, 'email' => $replyRaw, 'label' => $replyRaw];
    }

    // Otherwise treat as friend_code
    $st = $dbh->prepare("SELECT email, friend_code FROM users WHERE friend_code = :fc LIMIT 1");
    $st->execute([':fc' => $replyRaw]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['ok' => false, 'error' => 'Friend code not found.'];
    }

    return ['ok' => true, 'email' => (string)$row['email'], 'label' => (string)$row['friend_code']];
}

/**
 * Display name priority:
 * 1) contacts table custom name (if exists)
 * 2) users.name
 * 3) email
 *
 * This tries contacts table but will safely fallback if table/columns do not exist.
 */
function getPeerDisplayName(PDO $dbh, string $myEmail, string $peerEmail): string
{
    // Try contacts table
    try {
        $st = $dbh->prepare("
            SELECT contact_name
            FROM contacts
            WHERE owner_email = :me AND contact_email = :peer
            LIMIT 1
        ");
        $st->execute([':me' => $myEmail, ':peer' => $peerEmail]);
        $cname = trim((string)$st->fetchColumn());
        if ($cname !== '') return $cname;
    } catch (Throwable $e) {
        // ignore if table/columns don't exist
    }

    // fallback to users.name
    $st2 = $dbh->prepare("SELECT name FROM users WHERE email = :e LIMIT 1");
    $st2->execute([':e' => $peerEmail]);
    $uname = trim((string)$st2->fetchColumn());
    if ($uname !== '') return $uname;

    return $peerEmail;
}

$replyRaw = trim(urldecode($_GET['reply'] ?? ''));
$res = resolvePeerEmail($dbh, $replyRaw);

if (!$res['ok']) {
    die($res['error'] ?? 'Invalid reply target.');
}

$peerEmail = $res['email'];
$channel   = 'user_user'; // user chat only

// Validate recipient: must exist, active, and same role
$st = $dbh->prepare("SELECT id, name, email, role, status FROM users WHERE email = :e LIMIT 1");
$st->execute([':e' => $peerEmail]);
$peerRow = $st->fetch(PDO::FETCH_ASSOC);

if (!$peerRow) die("Recipient not found.");
if ((int)$peerRow['status'] !== 1) die("Recipient inactive.");
if ((int)$peerRow['role'] !== (int)$myRole) die("You can only chat with users in your same role.");

// Display name
$peerDisplayName = getPeerDisplayName($dbh, $meEmail, $peerEmail);

// Mark unread from peer -> me as read when opening chat
try {
    $mk = $dbh->prepare("
        UPDATE feedback
        SET is_read = 1, read_at = NOW()
        WHERE sender = :peer
          AND receiver = :me
          AND channel = 'user_user'
          AND is_read = 0
    ");
    $mk->execute([':peer' => $peerEmail, ':me' => $meEmail]);
} catch (Throwable $e) {
    // ignore
}

/**
 * NOTE:
 * We no longer POST-send here with redirect.
 * Sending is done instantly via AJAX to: ajax/chat_send.php
 * This keeps UI live and avoids refresh.
 */

// Load chat history
$rows = [];
try {
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
        ':me'    => $meEmail,
        ':peer'  => $peerEmail,
        ':peer2' => $peerEmail,
        ':me2'   => $meEmail
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = "Database error: " . $e->getMessage();
}

// Helpers
function fmt_dt($dt) { return $dt ? date('M d, Y h:i A', strtotime($dt)) : ''; }
function safe_text($txt) { return nl2br(htmlentities((string)($txt ?? ''), ENT_QUOTES, 'UTF-8')); }
function h($s){ return htmlentities((string)$s, ENT_QUOTES, 'UTF-8'); }
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
    Chat with: <strong><?php echo h($peerDisplayName); ?></strong> (User → User)
  </h2>

  <!-- Hidden fields for JS polling/send -->
  <input type="hidden" id="peerEmail" value="<?php echo h($peerEmail); ?>">
  <input type="hidden" id="meEmail" value="<?php echo h($meEmail); ?>">

  <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo h($msg); ?></div><?php endif; ?>

  <div id="chatBox" class="chat-wrap">
    <?php if (empty($rows)): ?>
      <div class="alert alert-info">No messages yet.</div>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <?php
          $isMe = (strcasecmp((string)$r['sender'], $meEmail) === 0);
          $rowClass  = $isMe ? 'row-right' : 'row-left';
          $bubbleCls = $isMe ? 'bubble bubble-me' : 'bubble';
          $who = $isMe ? 'You' : $peerDisplayName;
        ?>
        <div class="row-msg <?php echo $rowClass; ?>" data-msg-id="<?php echo (int)$r['id']; ?>">
          <div class="<?php echo $bubbleCls; ?>">
            <?php echo safe_text($r['feedbackdata'] ?? ''); ?>

            <?php if (!empty($r['attachment'])): ?>
              <div style="margin-top:8px;">
                <i class="fa fa-paperclip"></i>
                <a target="_blank" href="attachment/<?php echo urlencode((string)$r['attachment']); ?>">
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
          <textarea id="chatInput" name="message" class="form-control" rows="5" placeholder="Type your message..."></textarea>
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

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>

<script>
(function () {
  const chatBox   = document.getElementById('chatBox');
  const peerEmail = (document.getElementById('peerEmail')?.value || '').trim();
  const meEmail   = (document.getElementById('meEmail')?.value || '').trim();
  const form      = document.getElementById('chatForm');
  const input     = document.getElementById('chatInput');
  const fileInput = document.getElementById('chatFile');

  // ✅ Debug helper (remove later if you want)
  // console.log({ peerEmail, meEmail });

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

  function fmtLocal(dtStr){
    if (!dtStr) return '';
    // dtStr = "YYYY-MM-DD HH:MM:SS"
    const iso = dtStr.replace(' ', 'T');
    const d = new Date(iso);
    return isNaN(d.getTime()) ? '' : d.toLocaleString();
  }

  // ✅ Derive lastId from DOM
  let lastId = 0;
  if (chatBox) {
    chatBox.querySelectorAll('[data-msg-id]').forEach(el => {
      const id = Number(el.getAttribute('data-msg-id')) || 0;
      if (id > lastId) lastId = id;
    });
  }

  // ✅ Keep a set to prevent duplicates even if timing overlaps
  const seen = new Set();
  if (chatBox) {
    chatBox.querySelectorAll('[data-msg-id]').forEach(el => {
      const id = Number(el.getAttribute('data-msg-id')) || 0;
      if (id) seen.add(id);
    });
  }

  function renderMessage(m){
    const id = Number(m.id) || 0;
    if (id && seen.has(id)) return;

    const sender = String(m.sender || '');
    const text   = String(m.feedbackdata || '');
    const attachment = m.attachment ? String(m.attachment) : '';
    const createdAt = m.created_at ? String(m.created_at) : '';

    const isMe = sender.toLowerCase() === meEmail.toLowerCase();
    const rowClass  = isMe ? 'row-right' : 'row-left';
    const bubbleCls = isMe ? 'bubble bubble-me' : 'bubble';
    const who = isMe ? 'You' : 'Friend';

    const row = document.createElement('div');
    row.className = `row-msg ${rowClass}`;
    row.setAttribute('data-msg-id', String(id || ''));

    const attachmentHtml = attachment
      ? `<div style="margin-top:8px;">
           <i class="fa fa-paperclip"></i>
           <a target="_blank" href="attachment/${encodeURIComponent(attachment)}">${esc(attachment)}</a>
         </div>`
      : '';

    const timeTxt = fmtLocal(createdAt);

    row.innerHTML = `
      <div class="${bubbleCls}">
        ${esc(text).replace(/\n/g,'<br>')}
        ${attachmentHtml}
        <div class="meta">${esc(who)}${timeTxt ? ' • ' + esc(timeTxt) : ''}</div>
      </div>
    `;

    chatBox.appendChild(row);

    if (id) {
      seen.add(id);
      if (id > lastId) lastId = id;
    }

    scrollChatToBottom(false);
  }

  // initial scroll
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  window.addEventListener('load', function(){
    scrollChatToBottom(true);
    setTimeout(() => scrollChatToBottom(true), 50);
    setTimeout(() => scrollChatToBottom(true), 200);
  });

  // ✅ Instant send (AJAX)
  if (form) {
    form.addEventListener('submit', async function(e){
      e.preventDefault();

      const msg = (input?.value || '').trim();
      const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

      if (!msg && !hasFile) {
        alert('Message cannot be empty (add text or attachment).');
        return;
      }

      if (!peerEmail || !meEmail) {
        alert('Chat identity missing (peer/me). Check hidden inputs peerEmail/meEmail.');
        return;
      }

      const fd = new FormData(form);
      fd.append('peer', peerEmail);

      try {
        const res = await fetch('ajax/chat_send.php', { method: 'POST', body: fd, cache: 'no-store' });
        const data = await res.json();

        if (!data.ok) {
          alert(data.error || 'Send failed.');
          return;
        }

        // ✅ Render immediately
        renderMessage(data.message);

        // clear input + file
        if (input) input.value = '';
        if (fileInput) fileInput.value = '';

      } catch (err) {
        alert('Network error sending message.');
      }
    });
  }

  // ✅ Poll incoming messages (<1 sec)
  let pollTimer = null;
  let inFlight = false;

  async function pollChat(){
    if (inFlight) return;
    inFlight = true;
    try {
      const url = `ajax/chat_poll.php?peer=${encodeURIComponent(peerEmail)}&last_id=${encodeURIComponent(lastId)}`;
      const res = await fetch(url, { cache: 'no-store' });
      const data = await res.json();
      if (data.ok && Array.isArray(data.messages)) {
        for (const m of data.messages) renderMessage(m);
      }
    } catch (e) {
      // ignore temporary errors
    } finally {
      inFlight = false;
    }
  }

  // ✅ Start polling only if all values exist
  if (chatBox && peerEmail && meEmail) {
    pollChat();
    pollTimer = setInterval(pollChat, 700);
  } else {
    // If this triggers, your hidden inputs are missing or empty.
    console.warn('Polling not started. Missing chatBox/peerEmail/meEmail.', { peerEmail, meEmail, chatBox });
  }

})();
</script>


</body>
</html>
