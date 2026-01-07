<?php
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
$me     = myUsername();
$meId   = myAdminId();
$meRole = myRoleId();

if ($me === '' || $meId <= 0) die("Session missing username/id.");

function fmt_dt($dt) { return $dt ? date('M d, Y h:i A', strtotime($dt)) : ''; }
function isEmail($s): bool { return (strpos($s, '@') !== false); }
function h($s): string { return htmlentities((string)$s); }

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

function resolveUsernameFromFriendCode(PDO $dbh, string $friendCode): string {
    $st = $dbh->prepare("SELECT username FROM admin WHERE UPPER(friend_code) = :c AND status=1 LIMIT 1");
    $st->execute([':c' => strtoupper(trim($friendCode))]);
    return (string)($st->fetchColumn() ?: '');
}

/**
 * ==========================
 * ACTIONS
 * ==========================
 */

// MARK ONE THREAD READ
if (isset($_GET['mark']) && $_GET['mark'] !== '') {
    $peerKey = trim($_GET['mark']);
    try {
        if ($view === 'public') {
            if (!$adminMode || !isEmail($peerKey)) goBack($view, $filter);

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

        // INTERNAL: peerKey is friend_code -> resolve to username
        if (empty($internalChannels)) goBack($view, $filter);

        $peerUsername = resolveUsernameFromFriendCode($dbh, $peerKey);
        if ($peerUsername === '') goBack($view, $filter);

        $ph = implode(',', array_fill(0, count($internalChannels), '?'));
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE receiver = ?
              AND sender = ?
              AND channel IN ($ph)
              AND is_read = 0
        ");
        $mk->execute(array_merge([$me, $peerUsername], $internalChannels));
        goBack($view, $filter, 'threadread');

    } catch (Throwable $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// DELETE ONE THREAD
if (isset($_GET['del']) && $_GET['del'] !== '') {
    $peerKey = trim($_GET['del']);
    try {
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

        $peerUsername = resolveUsernameFromFriendCode($dbh, $peerKey);
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
        $del->execute(array_merge($internalChannels, [$me, $peerUsername, $peerUsername, $me]));
        goBack($view, $filter, 'deleted');

    } catch (Throwable $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// MARK ALL READ
if (isset($_POST['mark_all_read'])) {
    try {
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
        $mk->execute(array_merge([$me], $internalChannels));
        goBack($view, $filter, 'allread');

    } catch (Throwable $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// DELETE ALL (CURRENT VIEW)
if (isset($_POST['delete_all'])) {
    try {
        if ($view === 'public') {
            if (!$adminMode) goBack($view, $filter);

            $del = $dbh->prepare("DELETE FROM feedback WHERE receiver='Admin' AND channel='user_admin'");
            $del->execute();
            goBack($view, $filter, 'deletedall');
        }

        if (empty($internalChannels)) goBack($view, $filter);

        $ph = implode(',', array_fill(0, count($internalChannels), '?'));
        $del = $dbh->prepare("DELETE FROM feedback WHERE receiver=? AND channel IN ($ph)");
        $del->execute(array_merge([$me], $internalChannels));
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

/**
 * ==========================
 * FETCH THREADS (ONCE)
 * ==========================
 */
$threads = [];

try {
    if ($view === 'public') {
        if (!$adminMode) {
            $threads = [];
        } else {
            $sql = "
              SELECT
                f.sender AS peer_key,
                f.sender AS peer_display,
                MAX(f.created_at) AS last_time,
                SUM(CASE WHEN f.is_read=0 THEN 1 ELSE 0 END) AS unread_count,
                SUBSTRING_INDEX(
                  GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
                  ' ||| ', 1
                ) AS last_message
              FROM feedback f
              WHERE f.receiver='Admin'
                AND f.channel='user_admin'
              GROUP BY f.sender
              ORDER BY last_time DESC
            ";
            $stmt = $dbh->prepare($sql);
            $stmt->execute();
            $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } else {
        if (empty($internalChannels)) {
            $threads = [];
        } else {
            $ph = implode(',', array_fill(0, count($internalChannels), '?'));

            $sql = "
                  SELECT
                    a.friend_code AS peer_key,
                    CONCAT(
                      COALESCE(NULLIF(ac.display_name,''), NULLIF(a.fullname,''), a.username),
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
                  LEFT JOIN admin_contacts ac
                    ON ac.owner_admin_id = ?
                  AND ac.friend_admin_id = a.idadmin
                  WHERE (f.sender=? OR f.receiver=?)
                    AND f.channel IN ($ph)
                  GROUP BY a.friend_code, peer_display
                  ORDER BY last_time DESC
                ";

              

            $stmt = $dbh->prepare($sql);
            $params = array_merge([$me, $me, $meId, $me, $me], $internalChannels);
            $stmt->execute($params);
            $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

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

        /* Optional sticky sidebar */
    .ts-sidebar{ position: sticky; top: 70px; height: calc(100vh - 70px); overflow: auto; }

    /* ✅ DataTables scroll: keep header + search fixed and scroll only body */
    div.dataTables_wrapper div.dataTables_filter,
    div.dataTables_wrapper div.dataTables_length{
      position: sticky;
      top: 0;
      z-index: 20;
      background: #fff;
      padding: 8px 6px;
      border-bottom: 1px solid #eee;
    }

    /* DataTables creates scroll containers when scrollY is enabled */
    div.dataTables_scrollHead thead th{
      background: #fff !important;
    }

    /* Make scroll body nicer */
    div.dataTables_scrollBody{
      border: 1px solid #ddd;
      border-top: none;
    }
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
            <th><?php echo ($view === 'public') ? 'From (User Email)' : 'Peer (Name / Friend Code)'; ?></th>
            <th>Last Message</th>
            <th>Last Time</th>
            <th>Unread</th>
            <th style="width:180px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($threads as $t): ?>
          <?php
            $peerKey = (string)($t['peer_key'] ?? '');
            $peerDisplay = (string)($t['peer_display'] ?? $peerKey);
            $unread = (int)($t['unread_count'] ?? 0);
          ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo h($peerDisplay); ?><?php if ($unread > 0): ?>&nbsp;<span class="pill">new</span><?php endif; ?></td>
            <td class="msg-preview"><?php echo h($t['last_message'] ?? ''); ?></td>
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
  // ✅ DataTables scrolling body only (header + search stay fixed)
  const dt = $('#zctb').DataTable({
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
    order: [[4, 'desc']], // sort by Date & Time column (index 4)
    scrollY: '55vh',      // ✅ body scroll height (change if you want)
    scrollCollapse: true
  });
$(function(){
  $('#chatTable').DataTable({ pageLength:10, order:[[3,'desc']] });
  setTimeout(function(){ $('.alert-success,.alert-danger').fadeOut(); }, 2500);
});
</script>
</body>
</html>
