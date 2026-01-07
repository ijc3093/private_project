<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/includes/identity.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$meUsername = myUsername();     // feedback.sender
$meId       = myAdminId();      // admin.idadmin
$meRole     = myRoleId();       // role int

$msg = '';
$error = '';

function h($s): string { return htmlentities((string)$s); }

if ($meUsername === '' || $meId <= 0 || $meRole <= 0) {
    die("Invalid session.");
}

/**
 * Resolve a directory peer (NOT contacts) by:
 * - friend_code (ADM-2-3670, XXXX-XXXX-XXXX, etc)
 * - username
 * - fullname (admin.fullname)
 *
 * Returns assoc:
 *  idadmin, username, fullname, role, status, friend_code
 */
function resolveDirectoryPeer(PDO $dbh, string $term): ?array
{
    $term = trim($term);
    if ($term === '') return null;

    $termUpper = strtoupper($term);
    $like      = '%' . $term . '%';
    $likeUpper = '%' . $termUpper . '%';

    // Prefer exact friend_code/username match, otherwise fullname/username like
    $sql = "
        SELECT idadmin, username, fullname, role, status, friend_code
        FROM admin
        WHERE status = 1
          AND (
                UPPER(friend_code) = :codeExact
             OR username = :userExact
             OR fullname = :nameExact
             OR fullname LIKE :nameLike
             OR username LIKE :userLike
             OR UPPER(friend_code) LIKE :codeLike
          )
        ORDER BY
          (UPPER(friend_code) = :codeExact2) DESC,
          (username = :userExact2) DESC,
          (fullname = :nameExact2) DESC,
          fullname ASC
        LIMIT 1
    ";

    $st = $dbh->prepare($sql);
    $st->execute([
        ':codeExact'  => $termUpper,
        ':userExact'  => $term,
        ':nameExact'  => $term,
        ':nameLike'   => $like,
        ':userLike'   => $like,
        ':codeLike'   => $likeUpper,
        ':codeExact2' => $termUpper,
        ':userExact2' => $term,
        ':nameExact2' => $term,
    ]);

    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Quick check: can myRole chat with peerRole?
 */
function canChatRole(int $myRole, int $peerRole): bool
{
    return channelForAdminRoles($myRole, $peerRole) !== '';
}

// ----------------------------------------------------
// Prefill: /admin/compose.php?to=xxxx
// ----------------------------------------------------
$prefill = null;
$toParam = trim($_GET['to'] ?? '');
if ($toParam !== '') {
    $prefill = resolveDirectoryPeer($dbh, $toParam);
    // If found but not allowed by roles, do not prefill (avoid confusion)
    if ($prefill && !canChatRole($meRole, (int)$prefill['role'])) {
        $prefill = null;
    }
}

// -----------------------------
// SEND MESSAGE
// -----------------------------
if (isset($_POST['send'])) {

    // selected via search dropdown
    $friendAdminId = (int)($_POST['friend_admin_id'] ?? 0);

    // server fallback: user typed something but didn't click dropdown
    $fallbackTo = trim((string)($_POST['to_fallback'] ?? ''));

    $text = trim((string)($_POST['message'] ?? ''));

    // If user didn't select an ID, try resolve from what they typed
    if ($friendAdminId <= 0 && $fallbackTo !== '') {
        $r = resolveDirectoryPeer($dbh, $fallbackTo);
        if ($r) $friendAdminId = (int)$r['idadmin'];
    }

    if ($friendAdminId <= 0) {
        $error = "Please choose a person (search and select).";
    } elseif ($text === '') {
        $error = "Message cannot be empty.";
    }

    // Load peer from admin table (directory)
    $peer = null;
    if ($error === '') {
        $st = $dbh->prepare("
            SELECT idadmin, username, fullname, role, status, friend_code
            FROM admin
            WHERE idadmin = :id
            LIMIT 1
        ");
        $st->execute([':id' => $friendAdminId]);
        $peer = $st->fetch(PDO::FETCH_ASSOC);

        if (!$peer) $error = "Recipient not found.";
        elseif ((int)$peer['status'] !== 1) $error = "Recipient is inactive.";
        elseif ((int)$peer['idadmin'] === $meId) $error = "You cannot message yourself.";
    }

    // Determine internal channel by roles
    $channel = '';
    if ($error === '') {
        $peerRole = (int)($peer['role'] ?? 0);
        $channel = channelForAdminRoles($meRole, $peerRole);
        if ($channel === '') {
            $error = "You can't message this role from your role.";
        }
    }

    if ($error === '') {
        // Insert feedback message (internal chat)
        $stmt = $dbh->prepare("
            INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, is_read)
            VALUES (:s, :r, :ch, 'Internal Chat', :msg, 0)
        ");
        $stmt->execute([
            ':s'   => $meUsername,
            ':r'   => (string)$peer['username'], // receiver is username
            ':ch'  => $channel,
            ':msg' => $text
        ]);

        // Open thread using friend_code (preferred) else username
        $replyKey = (string)($peer['friend_code'] ?? '');
        if ($replyKey === '') $replyKey = (string)$peer['username'];

        header("Location: sendreply.php?reply=" . urlencode($replyKey));
        exit;
    }
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Message</title>

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .box{background:#fff;border:1px solid #ddd;border-radius:10px;padding:18px;}
    .search-wrap{position:relative;}
    .results{
      position:absolute; left:0; right:0; top:100%;
      background:#fff; border:1px solid #ddd; border-top:none;
      z-index:9999; max-height:260px; overflow:auto;
      display:none;
    }
    .item{padding:10px 12px; cursor:pointer; border-bottom:1px solid #f1f1f1;}
    .item:hover{background:#f7f7f7;}
    .small{font-size:12px;color:#777;}
    .selected-pill{
      display:inline-block; padding:6px 10px; border-radius:18px;
      background:#eef5ff; color:#0b5ed7; font-weight:700;
      margin-top:8px;
    }
    .meta-pill{font-size:12px;color:#6b7280;margin-top:4px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">New Message</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
  <?php elseif ($msg): ?>
    <div class="alert alert-success"><?php echo h($msg); ?></div>
  <?php endif; ?>

  <div class="box">
    <form method="post" autocomplete="off">
      <input type="hidden" name="friend_admin_id" id="friend_admin_id" value="<?php echo (int)($prefill['idadmin'] ?? 0); ?>">
      <input type="hidden" name="to_fallback" id="to_fallback" value="<?php echo h($toParam); ?>">

      <div class="form-group">
        <label>To (Search full name, username, or friend code)</label>
        <div class="search-wrap">
          <input type="text"
                 id="toSearch"
                 class="form-control"
                 placeholder="Type full name, username, or friend code..."
                 autocomplete="off"
                 value="<?php echo h($prefill['fullname'] ?? $prefill['username'] ?? $toParam); ?>">
          <div id="results" class="results"></div>
        </div>

        <div id="selectedInfo" class="selected-pill" style="<?php echo $prefill ? '' : 'display:none;'; ?>">
          <?php if ($prefill): ?>
            <?php
              $dn = trim((string)($prefill['fullname'] ?? ''));
              if ($dn === '') $dn = (string)($prefill['username'] ?? '');
              $fc = (string)($prefill['friend_code'] ?? '');
            ?>
            <?php echo h($dn . ($fc ? " • " . $fc : "")); ?>
          <?php endif; ?>
        </div>

        <div class="meta-pill">Directory search (no contacts needed). Only active accounts appear.</div>
      </div>

      <div class="form-group">
        <label>Message</label>
        <textarea name="message" class="form-control" rows="5" placeholder="Type message..."></textarea>
      </div>

      <button class="btn btn-primary" name="send" type="submit">
        <i class="fa fa-send"></i> Send
      </button>

      <a class="btn btn-default" href="feedback.php?view=internal">
        <i class="fa fa-inbox"></i> Inbox
      </a>
    </form>
  </div>

</div>
</div>
</div>

<script>
(function(){
  const input = document.getElementById('toSearch');
  const results = document.getElementById('results');
  const hiddenId = document.getElementById('friend_admin_id');
  const selectedInfo = document.getElementById('selectedInfo');
  const toFallback = document.getElementById('to_fallback');

  let timer = null;

  function clearResults(){
    results.innerHTML = '';
    results.style.display = 'none';
  }

  function escapeHtml(s){
    return String(s || '').replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }

  function setSelected(item){
    hiddenId.value = item.idadmin || '';
    toFallback.value = item.friend_code || item.username || input.value || '';

    input.value = item.fullname || item.username || '';
    selectedInfo.style.display = 'inline-block';
    selectedInfo.textContent =
      (item.fullname || item.username || '') + (item.friend_code ? " • " + item.friend_code : "");

    clearResults();
  }

  async function search(term){
    term = (term || '').trim();
    if (!term || term.length < 1) { clearResults(); return; }

    try{
      const r = await fetch('ajax/admin_directory_search.php?term=' + encodeURIComponent(term), { cache: 'no-store' });
      const data = await r.json();

      if (!data || !data.ok) { clearResults(); return; }
      const items = data.items || [];

      if (items.length === 0){
        results.innerHTML = '<div class="item"><b>No match</b><div class="small">Try another name, username, or code</div></div>';
        results.style.display = 'block';
        return;
      }

      results.innerHTML = items.map(x => `
        <div class="item"
             data-id="${x.idadmin}"
             data-fullname="${encodeURIComponent(x.fullname || '')}"
             data-username="${encodeURIComponent(x.username || '')}"
             data-code="${encodeURIComponent(x.friend_code || '')}">
          <div><b>${escapeHtml(x.fullname || x.username || '')}</b></div>
          <div class="small">
            Username: ${escapeHtml(x.username || '')}
            ${x.friend_code ? ' • Friend Code: ' + escapeHtml(x.friend_code) : ''}
          </div>
        </div>
      `).join('');

      results.style.display = 'block';
    }catch(e){
      clearResults();
    }
  }

  input.addEventListener('input', function(){
    hiddenId.value = '';
    selectedInfo.style.display = 'none';
    toFallback.value = input.value || '';

    clearTimeout(timer);
    timer = setTimeout(() => search(input.value), 200);
  });

  results.addEventListener('click', function(e){
    const row = e.target.closest('.item');
    if (!row) return;

    const item = {
      idadmin: row.getAttribute('data-id'),
      fullname: decodeURIComponent(row.getAttribute('data-fullname') || ''),
      username: decodeURIComponent(row.getAttribute('data-username') || ''),
      friend_code: decodeURIComponent(row.getAttribute('data-code') || '')
    };
    setSelected(item);
  });

  document.addEventListener('click', function(e){
    if (!e.target.closest('.search-wrap')) clearResults();
  });
})();
</script>

</body>
</html>
