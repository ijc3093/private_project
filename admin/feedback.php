<?php
// /Business_only3/admin/feedback.php
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

$filter = strtolower(trim($_GET['filter'] ?? 'all'));
$filter = in_array($filter, ['all','unread','read'], true) ? $filter : 'all';

$adminMode = isAdmin(); // role 1 only

$meUser  = myUsername(); // internal sender/receiver = username
$meId    = myAdminId();
$meRole  = myRoleId();

if ($meUser === '' || $meId <= 0 || $meRole <= 0) die("Session missing username/id.");

function h($s): string { return htmlentities((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_dt($dt): string { return $dt ? date('M d, Y h:i A', strtotime($dt)) : ''; }
function isEmail($s): bool { return (strpos((string)$s, '@') !== false); }

$internalChannels = allowedInternalChannelsForMe();

/**
 * Admin role 1 can switch: public/internal
 * Other roles: internal only
 */
$view = strtolower(trim($_GET['view'] ?? ($adminMode ? 'public' : 'internal')));
$view = in_array($view, ['public','internal'], true) ? $view : 'internal';
if (!$adminMode) $view = 'internal';

function goBack(string $view, string $filter, string $msgKey = ''): void {
    $q = "view=" . urlencode($view) . "&filter=" . urlencode($filter);
    if ($msgKey !== '') $q .= "&msg=" . urlencode($msgKey);
    header("Location: feedback.php?$q");
    exit;
}

/**
 * Resolve peer username from peer key
 * - peer key can be friend_code OR username
 */
function resolvePeerUsername(PDO $dbh, string $peerKey): string {
    $peerKey = trim($peerKey);
    if ($peerKey === '') return '';

    // Try friend_code first
    $st = $dbh->prepare("SELECT username FROM admin WHERE UPPER(friend_code) = :c AND status=1 LIMIT 1");
    $st->execute([':c' => strtoupper($peerKey)]);
    $u = (string)($st->fetchColumn() ?: '');
    if ($u !== '') return $u;

    // Fallback: username
    $st2 = $dbh->prepare("SELECT username FROM admin WHERE username = :u AND status=1 LIMIT 1");
    $st2->execute([':u' => $peerKey]);
    $u2 = (string)($st2->fetchColumn() ?: '');
    return $u2;
}

/* ==========================================================
   ACTIONS
========================================================== */

// MARK ONE THREAD READ
if (isset($_GET['mark']) && $_GET['mark'] !== '') {
    $peerKey = trim($_GET['mark']);

    try {
        // PUBLIC
        if ($view === 'public') {
            if (!$adminMode || !isEmail($peerKey)) goBack($view, $filter);

            // mark user->Admin unread as read
            $mk = $dbh->prepare("
                UPDATE feedback
                SET is_read = 1, read_at = NOW()
                WHERE channel='user_admin'
                  AND receiver='Admin'
                  AND sender = :peer
                  AND is_read = 0
            ");
            $mk->execute([':peer' => $peerKey]);
            goBack($view, $filter, 'threadread');
        }

        // INTERNAL
        if (empty($internalChannels)) goBack($view, $filter);

        $peerUsername = resolvePeerUsername($dbh, $peerKey);
        if ($peerUsername === '') goBack($view, $filter);

        $ph = implode(',', array_fill(0, count($internalChannels), '?'));

        // mark peer->me unread as read (receiver=me)
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE receiver = ?
              AND sender = ?
              AND channel IN ($ph)
              AND is_read = 0
        ");
        $mk->execute(array_merge([$meUser, $peerUsername], $internalChannels));
        goBack($view, $filter, 'threadread');

    } catch (Throwable $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// DELETE ONE THREAD
if (isset($_GET['del']) && $_GET['del'] !== '') {
    $peerKey = trim($_GET['del']);

    try {
        // PUBLIC
        if ($view === 'public') {
            if (!$adminMode || !isEmail($peerKey)) goBack($view, $filter);

            $del = $dbh->prepare("
                DELETE FROM feedback
                WHERE channel='user_admin'
                  AND (
                        (sender=:peer AND receiver='Admin')
                     OR (sender='Admin' AND receiver=:peer2)
                  )
            ");
            $del->execute([':peer'=>$peerKey, ':peer2'=>$peerKey]);
            goBack($view, $filter, 'deleted');
        }

        // INTERNAL
        if (empty($internalChannels)) goBack($view, $filter);

        $peerUsername = resolvePeerUsername($dbh, $peerKey);
        if ($peerUsername === '') goBack($view, $filter);

        $ph = implode(',', array_fill(0, count($internalChannels), '?'));

        $del = $dbh->prepare("
            DELETE FROM feedback
            WHERE channel IN ($ph)
              AND (
                    (sender = ? AND receiver = ?)
                 OR (sender = ? AND receiver = ?)
              )
        ");
        $del->execute(array_merge($internalChannels, [$meUser, $peerUsername, $peerUsername, $meUser]));
        goBack($view, $filter, 'deleted');

    } catch (Throwable $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// MARK ALL READ
if (isset($_POST['mark_all_read'])) {
    try {
        // PUBLIC
        if ($view === 'public') {
            if (!$adminMode) goBack($view, $filter);

            $mk = $dbh->prepare("
                UPDATE feedback
                SET is_read=1, read_at=NOW()
                WHERE receiver='Admin'
                  AND channel='user_admin'
                  AND is_read=0
            ");
            $mk->execute();
            goBack($view, $filter, 'allread');
        }

        // INTERNAL
        if (empty($internalChannels)) goBack($view, $filter);

        $ph = implode(',', array_fill(0, count($internalChannels), '?'));
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read=1, read_at=NOW()
            WHERE receiver=?
              AND channel IN ($ph)
              AND is_read=0
        ");
        $mk->execute(array_merge([$meUser], $internalChannels));
        goBack($view, $filter, 'allread');

    } catch (Throwable $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// DELETE ALL (CURRENT VIEW)
if (isset($_POST['delete_all'])) {
    try {
        // PUBLIC
        if ($view === 'public') {
            if (!$adminMode) goBack($view, $filter);

            $del = $dbh->prepare("DELETE FROM feedback WHERE channel='user_admin' AND (receiver='Admin' OR sender='Admin')");
            $del->execute();
            goBack($view, $filter, 'deletedall');
        }

        // INTERNAL
        if (empty($internalChannels)) goBack($view, $filter);

        $ph = implode(',', array_fill(0, count($internalChannels), '?'));
        $del = $dbh->prepare("
            DELETE FROM feedback
            WHERE channel IN ($ph)
              AND (sender=? OR receiver=?)
        ");
        $del->execute(array_merge($internalChannels, [$meUser, $meUser]));
        goBack($view, $filter, 'deletedall');

    } catch (Throwable $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// UI messages
if (($_GET['msg'] ?? '') === 'allread')    $msg = "All messages marked as read.";
if (($_GET['msg'] ?? '') === 'threadread') $msg = "Thread marked as read.";
if (($_GET['msg'] ?? '') === 'deleted')    $msg = "Thread deleted.";
if (($_GET['msg'] ?? '') === 'deletedall') $msg = "All threads deleted.";

/* ==========================================================
   INITIAL THREAD LOAD (SERVER RENDER)
   (After this, JS will poll ajax/threads_poll.php and refresh list live)
========================================================== */

$threads = [];

try {
    if ($view === 'public') {
        if (!$adminMode) {
            $threads = [];
        } else {
            // ✅ FIXED: include BOTH directions (Admin<->User) in thread grouping
            $sql = "
              SELECT
                peer,
                MAX(id) AS last_id,
                MAX(created_at) AS last_time,
                SUM(CASE
                      WHEN receiver='Admin' AND sender=peer AND is_read=0 THEN 1
                      ELSE 0
                    END) AS unread_count
              FROM (
                SELECT
                  id, sender, receiver, created_at, is_read,
                  CASE WHEN sender='Admin' THEN receiver ELSE sender END AS peer
                FROM feedback
                WHERE channel='user_admin'
                  AND (sender='Admin' OR receiver='Admin')
              ) x
              GROUP BY peer
              ORDER BY last_id DESC
            ";
            $stmt = $dbh->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // fetch last message preview by last_id
            $lastMap = [];
            if ($rows) {
                $ids = array_values(array_filter(array_map(fn($r) => (int)$r['last_id'], $rows)));
                if ($ids) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $q = $dbh->prepare("SELECT id, feedbackdata FROM feedback WHERE id IN ($in)");
                    $q->execute($ids);
                    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $lastMap[(int)$r['id']] = (string)($r['feedbackdata'] ?? '');
                    }
                }
            }

            foreach ($rows as $r) {
                $peer = (string)($r['peer'] ?? '');
                $lastId = (int)($r['last_id'] ?? 0);
                $lastMsg = $lastMap[$lastId] ?? '';
                $threads[] = [
                    'peer_key' => $peer,           // email
                    'peer_display' => $peer,
                    'last_time' => (string)($r['last_time'] ?? ''),
                    'unread_count' => (int)($r['unread_count'] ?? 0),
                    'last_message' => $lastMsg
                ];
            }
        }

    } else {
        if (empty($internalChannels)) {
            $threads = [];
        } else {
            $ph = implode(',', array_fill(0, count($internalChannels), '?'));

            // ✅ FIXED: peer_key falls back to username if friend_code empty
            $sql = "
              SELECT
                COALESCE(NULLIF(a.friend_code,''), a.username) AS peer_key,
                CONCAT(
                  COALESCE(NULLIF(a.fullname,''), a.username),
                  ' • ',
                  COALESCE(NULLIF(a.friend_code,''), a.username)
                ) AS peer_display,
                MAX(f.created_at) AS last_time,
                SUM(CASE WHEN f.is_read=0 AND f.receiver=? THEN 1 ELSE 0 END) AS unread_count,
                SUBSTRING_INDEX(
                  GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
                  ' ||| ', 1
                ) AS last_message
              FROM feedback f
              JOIN admin a
                ON a.username = CASE WHEN f.sender=? THEN f.receiver ELSE f.sender END
              WHERE (f.sender=? OR f.receiver=?)
                AND f.channel IN ($ph)
              GROUP BY peer_key, peer_display
              ORDER BY last_time DESC
            ";

            $stmt = $dbh->prepare($sql);
            $params = array_merge([$meUser, $meUser, $meUser, $meUser], $internalChannels);
            $stmt->execute($params);
            $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // filter unread/read/all
    if ($filter !== 'all') {
        $threads = array_values(array_filter($threads, function($t) use ($filter){
            $u = (int)($t['unread_count'] ?? 0);
            return ($filter === 'unread') ? ($u > 0) : ($u === 0);
        }));
    }

} catch (Throwable $e) {
    $error = "DB error: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat Inbox</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .actions-bar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin:10px 0 15px;flex-wrap:wrap;}
    .tabs a{margin-right:8px;}
    .pill{display:inline-block;padding:4px 10px;border-radius:14px;background:#eef5ff;color:#0b5ed7;font-weight:600;font-size:12px;}
    .unread-dot{display:inline-block;min-width:18px;text-align:center;background:red;color:#fff;border-radius:10px;padding:2px 6px;font-size:11px;font-weight:700;}
    .msg-preview{max-width:520px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">
    <?php echo ($view === 'public') ? 'Chat Inbox (Public Users → Admin)' : 'Chat Inbox (Internal Roles)'; ?>
  </h2>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo h($msg); ?></div><?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">Threads</div>
    <div class="panel-body">

      <div class="actions-bar">
        <div class="tabs">
          <?php if ($adminMode): ?>
            <a class="btn btn-info btn-sm <?php echo ($view==='public')?'active':''; ?>"
               href="feedback.php?view=public&filter=<?php echo urlencode($filter); ?>">Public Users</a>
            <a class="btn btn-info btn-sm <?php echo ($view==='internal')?'active':''; ?>"
               href="feedback.php?view=internal&filter=<?php echo urlencode($filter); ?>">Internal Roles</a>
          <?php else: ?>
            <a class="btn btn-info btn-sm active"
               href="feedback.php?view=internal&filter=<?php echo urlencode($filter); ?>">Internal Roles</a>
          <?php endif; ?>

          <span style="margin:0 10px;"></span>

          <a class="btn btn-default btn-sm <?php echo ($filter==='all')?'active':''; ?>"
             href="feedback.php?view=<?php echo urlencode($view); ?>&filter=all">All</a>
          <a class="btn btn-default btn-sm <?php echo ($filter==='unread')?'active':''; ?>"
             href="feedback.php?view=<?php echo urlencode($view); ?>&filter=unread">Unread</a>
          <a class="btn btn-default btn-sm <?php echo ($filter==='read')?'active':''; ?>"
             href="feedback.php?view=<?php echo urlencode($view); ?>&filter=read">Read</a>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php if ($view === 'internal'): ?>
            <a class="btn btn-default btn-sm" href="contacts.php"><i class="fa fa-users"></i> Contacts</a>
          <?php endif; ?>

          <a class="btn btn-success btn-sm" href="compose.php"><i class="fa fa-plus"></i> New Message</a>

          <form method="post" style="margin:0;">
            <button type="submit" name="mark_all_read" class="btn btn-success btn-sm"
                    onclick="return confirm('Mark ALL as read?');"
                    <?php echo empty($threads) ? 'disabled' : ''; ?>>
              <i class="fa fa-check"></i> Mark All Read
            </button>
          </form>

          <form method="post" style="margin:0;">
            <button type="submit" name="delete_all" class="btn btn-danger btn-sm"
                    onclick="return confirm('Delete ALL threads in this view?');"
                    <?php echo empty($threads) ? 'disabled' : ''; ?>>
              <i class="fa fa-trash"></i> Delete All
            </button>
          </form>
        </div>
      </div>

      <table id="chatTable" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th><?php echo ($view === 'public') ? 'Peer (User Email)' : 'Peer (Name / Friend Code)'; ?></th>
            <th>Last Message</th>
            <th>Last Time</th>
            <th>Unread</th>
            <th style="width:180px;">Action</th>
          </tr>
        </thead>
        <tbody id="threadsBody">
        <?php $i=1; foreach ($threads as $t): ?>
          <?php
            $peerKey = (string)($t['peer_key'] ?? '');
            $peerDisplay = (string)($t['peer_display'] ?? $peerKey);
            $unread = (int)($t['unread_count'] ?? 0);

            $lastMsg = (string)($t['last_message'] ?? '');
            if (mb_strlen($lastMsg) > 140) $lastMsg = mb_substr($lastMsg, 0, 140) . '...';
          ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo h($peerDisplay); ?><?php if ($unread > 0): ?>&nbsp;<span class="pill">new</span><?php endif; ?></td>
            <td class="msg-preview"><?php echo h($lastMsg); ?></td>
            <td><?php echo h(fmt_dt($t['last_time'] ?? '')); ?></td>
            <td>
              <?php if ($unread > 0): ?>
                <span class="unread-dot"><?php echo $unread; ?></span>
              <?php else: ?>
                <span class="label label-success">0</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-primary btn-xs" href="sendreply.php?reply=<?php echo urlencode($peerKey); ?>">
                <i class="fa fa-mail-reply"></i> Reply
              </a>

              <?php if ($unread > 0): ?>
                <a class="btn btn-default btn-xs"
                   href="feedback.php?view=<?php echo urlencode($view); ?>&filter=<?php echo urlencode($filter); ?>&mark=<?php echo urlencode($peerKey); ?>"
                   onclick="return confirm('Mark this thread read?');">
                  <i class="fa fa-check"></i>
                </a>
              <?php endif; ?>

              <a class="btn btn-danger btn-xs"
                 href="feedback.php?view=<?php echo urlencode($view); ?>&filter=<?php echo urlencode($filter); ?>&del=<?php echo urlencode($peerKey); ?>"
                 onclick="return confirm('Delete this thread?');">
                <i class="fa fa-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if (empty($threads)): ?>
        <div class="alert alert-info" style="margin-top:12px;">No chat threads found.</div>
      <?php endif; ?>

      <small class="text-muted">
        ✅ Live updates enabled (no page refresh). New threads/messages will appear automatically.
      </small>

    </div>
  </div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap.min.js"></script>

<script>
(function(){
  // DataTables init (ONLY once)
  let dt = null;
  $(function(){
    dt = $('#chatTable').DataTable({ pageLength: 10, order:[[3,'desc']] });
    setTimeout(function(){ $('.alert-success,.alert-danger').fadeOut(); }, 2500);
  });

  const view   = <?php echo json_encode($view); ?>;
  const filter = <?php echo json_encode($filter); ?>;

  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[c]));
  }

  function renderRows(items){
    const tbody = document.getElementById('threadsBody');
    if (!tbody) return;

    let i = 1;
    const html = (items || []).map(t => {
      const peerKey = t.peer_key || '';
      const peerDisplay = t.peer_display || peerKey;
      const unread = parseInt(t.unread_count || 0, 10);
      const lastMsg = (t.last_message || '').length > 140 ? (t.last_message || '').slice(0, 140) + '...' : (t.last_message || '');
      const lastTime = t.last_time_human || '';

      const unreadHtml = unread > 0
        ? `<span class="unread-dot">${unread}</span>`
        : `<span class="label label-success">0</span>`;

      const pill = unread > 0 ? `&nbsp;<span class="pill">new</span>` : '';

      const markBtn = unread > 0
        ? `<a class="btn btn-default btn-xs"
              href="feedback.php?view=${encodeURIComponent(view)}&filter=${encodeURIComponent(filter)}&mark=${encodeURIComponent(peerKey)}"
              onclick="return confirm('Mark this thread read?');">
              <i class="fa fa-check"></i>
           </a>`
        : '';

      return `
        <tr>
          <td>${i++}</td>
          <td>${escapeHtml(peerDisplay)}${pill}</td>
          <td class="msg-preview">${escapeHtml(lastMsg)}</td>
          <td>${escapeHtml(lastTime)}</td>
          <td>${unreadHtml}</td>
          <td>
            <a class="btn btn-primary btn-xs" href="sendreply.php?reply=${encodeURIComponent(peerKey)}">
              <i class="fa fa-mail-reply"></i> Reply
            </a>
            ${markBtn}
            <a class="btn btn-danger btn-xs"
               href="feedback.php?view=${encodeURIComponent(view)}&filter=${encodeURIComponent(filter)}&del=${encodeURIComponent(peerKey)}"
               onclick="return confirm('Delete this thread?');">
              <i class="fa fa-trash"></i>
            </a>
          </td>
        </tr>
      `;
    }).join('');

    // If DataTables is active, rebuild via API (prevents UI bugs)
    if (dt) {
      dt.clear();
      const temp = document.createElement('tbody');
      temp.innerHTML = html;
      const rows = Array.from(temp.querySelectorAll('tr')).map(tr => Array.from(tr.children).map(td => td.innerHTML));
      dt.rows.add(rows);
      dt.order([3,'desc']).draw(false);
    } else {
      tbody.innerHTML = html;
    }
  }

  async function pollThreads(){
    try{
      const r = await fetch(`ajax/threads_poll.php?view=${encodeURIComponent(view)}&filter=${encodeURIComponent(filter)}`, { cache:'no-store' });
      const d = await r.json();
      if (!d || !d.ok) return;
      renderRows(d.threads || []);
    }catch(e){}
  }

  // ✅ Live inbox updates (no browser refresh)
  pollThreads();
  setInterval(pollThreads, 1500);
})();
</script>

</body>
</html>
