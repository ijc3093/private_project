<?php
// /Business_only3/user_feedback.php
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

$msg = '';
$error = '';

$filter = strtolower(trim($_GET['filter'] ?? 'all')); // all | unread | read
$filter = in_array($filter, ['all','unread','read'], true) ? $filter : 'all';

$readWhere = "";
if ($filter === 'unread') $readWhere = " AND f.is_read = 0 ";
if ($filter === 'read')   $readWhere = " AND f.is_read = 1 ";

function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : '';
}

function isEmail($s): bool {
    return (strpos($s, '@') !== false);
}

// -----------------------------
// ACTION: MARK ALL READ (user inbox)
// -----------------------------
if (isset($_POST['mark_all_read'])) {
    try {
        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE receiver = :me
              AND channel IN ('user_admin','user_user')
              AND is_read = 0
        ");
        $mk->execute([':me' => $meEmail]);

        header("Location: user_feedback.php?filter=" . urlencode($filter) . "&msg=allread");
        exit;
    } catch (PDOException $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// -----------------------------
// ACTION: MARK ONE THREAD READ
// -----------------------------
if (isset($_GET['mark']) && $_GET['mark'] !== '') {
    $peer = trim($_GET['mark']);

    try {
        if ($peer === adminInboxKey()) {
            // Admin thread: mark Admin -> me unread
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
            if (!isEmail($peer)) {
                header("Location: user_feedback.php?filter=" . urlencode($filter));
                exit;
            }

            // Ensure same role
            $st = $dbh->prepare("SELECT role FROM users WHERE email = :e LIMIT 1");
            $st->execute([':e' => $peer]);
            $peerRole = (int)$st->fetchColumn();
            if ($peerRole !== $myRole) die("Not allowed.");

            $mk = $dbh->prepare("
                UPDATE feedback
                SET is_read = 1, read_at = NOW()
                WHERE sender = :peer
                  AND receiver = :me
                  AND channel = 'user_user'
                  AND is_read = 0
            ");
            $mk->execute([':peer' => $peer, ':me' => $meEmail]);
        }

        header("Location: user_feedback.php?filter=" . urlencode($filter) . "&msg=threadread");
        exit;
    } catch (PDOException $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// -----------------------------
// UI messages
// -----------------------------
if (($_GET['msg'] ?? '') === 'allread')    $msg = "All messages marked as read.";
if (($_GET['msg'] ?? '') === 'threadread') $msg = "Thread marked as read.";

// -----------------------------
// FETCH THREADS (Admin + same-role users)
// -----------------------------
$threads = [];

try {
    // A) Admin thread (single "peer" = Admin)
    $sqlAdmin = "
        SELECT
          'Admin' AS peer,
          MAX(f.created_at) AS last_time,
          SUM(CASE WHEN f.is_read = 0 AND f.receiver = :me THEN 1 ELSE 0 END) AS unread_count,
          SUBSTRING_INDEX(
            GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
            ' ||| ', 1
          ) AS last_message
        FROM feedback f
        WHERE f.channel = 'user_admin'
          AND (
                (f.sender = :me2 AND f.receiver = 'Admin')
             OR (f.sender = 'Admin' AND f.receiver = :me3)
          )
        HAVING last_time IS NOT NULL
    ";
    $stA = $dbh->prepare($sqlAdmin);
    $stA->execute([':me' => $meEmail, ':me2' => $meEmail, ':me3' => $meEmail]);
    $adminThread = $stA->fetch(PDO::FETCH_ASSOC);

    if ($adminThread && !empty($adminThread['last_time'])) {
        if ($filter === 'unread' && (int)$adminThread['unread_count'] === 0) {
            // skip
        } elseif ($filter === 'read' && (int)$adminThread['unread_count'] > 0) {
            // skip
        } else {
            $threads[] = $adminThread;
        }
    }

    // B) User↔User same role threads (channel=user_user)
    $sqlUsers = "
        SELECT
          CASE
            WHEN f.sender = :me THEN f.receiver
            ELSE f.sender
          END AS peer,
          MAX(f.created_at) AS last_time,
          SUM(CASE WHEN f.is_read = 0 AND f.receiver = :me2 THEN 1 ELSE 0 END) AS unread_count,
          SUBSTRING_INDEX(
            GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
            ' ||| ', 1
          ) AS last_message
        FROM feedback f
        INNER JOIN users upeer
          ON upeer.email = (CASE WHEN f.sender = :me3 THEN f.receiver ELSE f.sender END)
        WHERE f.channel = 'user_user'
          AND (f.sender = :me4 OR f.receiver = :me5)
          AND upeer.role = :r
          $readWhere
        GROUP BY peer
        ORDER BY last_time DESC
    ";
    $stU = $dbh->prepare($sqlUsers);
    $stU->execute([
        ':me' => $meEmail, ':me2' => $meEmail, ':me3' => $meEmail,
        ':me4' => $meEmail, ':me5' => $meEmail,
        ':r' => $myRole
    ]);
    $userThreads = $stU->fetchAll(PDO::FETCH_ASSOC);

    // merge and sort by last_time desc
    $threads = array_merge($threads, $userThreads);
    usort($threads, fn($a,$b) => strtotime($b['last_time']) <=> strtotime($a['last_time']));

} catch (PDOException $e) {
    $error = "DB error: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Chats</title>

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

  <!-- <h2 class="page-title">My Chat Inbox</h2> -->

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">My Chat Inbox - Threads</div>
    <div class="panel-body">

      <div class="actions-bar">
        <div class="tabs">
          <a class="btn btn-default btn-sm <?php echo ($filter==='all')?'active':''; ?>" href="user_feedback.php?filter=all">All</a>
          <a class="btn btn-default btn-sm <?php echo ($filter==='unread')?'active':''; ?>" href="user_feedback.php?filter=unread">Unread</a>
          <a class="btn btn-default btn-sm <?php echo ($filter==='read')?'active':''; ?>" href="user_feedback.php?filter=read">Read</a>
        </div>

        <div style="display:flex;gap:8px;">
          <a class="btn btn-success btn-sm" href="compose_user.php">
            <i class="fa fa-plus"></i> New Message
          </a>

          <form method="post" style="margin:0;">
            <button type="submit" name="mark_all_read"
                    class="btn btn-success btn-sm"
                    onclick="return confirm('Mark ALL as read?');"
                    <?php echo empty($threads) ? 'disabled' : ''; ?>>
              <i class="fa fa-check"></i> Mark All Read
            </button>
          </form>
        </div>
      </div>

      <table id="chatTable" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Peer</th>
            <th>Last Message</th>
            <th>Last Time</th>
            <th>Unread</th>
            <th style="width:220px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($threads as $t): ?>
          <?php $peer = $t['peer']; ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td>
              <?php echo htmlentities($peer); ?>
              <?php if ((int)$t['unread_count'] > 0): ?>
                &nbsp;<span class="pill">new</span>
              <?php endif; ?>
            </td>
            <td class="msg-preview"><?php echo htmlentities($t['last_message'] ?? ''); ?></td>
            <td><?php echo htmlentities(fmt_dt($t['last_time'])); ?></td>
            <td>
              <?php if ((int)$t['unread_count'] > 0): ?>
                <span class="unread-dot"><?php echo (int)$t['unread_count']; ?></span>
              <?php else: ?>
                <span class="label label-success">0</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-primary btn-xs"
                 href="user_sendreply.php?reply=<?php echo urlencode($peer); ?>"
                 title="Open chat & reply">
                <i class="fa fa-mail-reply"></i> Reply
              </a>

              <?php if ((int)$t['unread_count'] > 0): ?>
                <a class="btn btn-default btn-xs"
                   href="user_feedback.php?filter=<?php echo urlencode($filter); ?>&mark=<?php echo urlencode($peer); ?>"
                   title="Mark read"
                   onclick="return confirm('Mark this thread read?');">
                  <i class="fa fa-check"></i>
                </a>
              <?php endif; ?>
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
$(function(){
  $('#chatTable').DataTable({
    pageLength: 10,
    lengthMenu: [[10,25,50,100],[10,25,50,100]],
    order: [[3, 'desc']]
  });
});
</script>

</body>
</html>
