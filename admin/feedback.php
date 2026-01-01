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

$filter = strtolower(trim($_GET['filter'] ?? 'all')); // all | unread | read
$filter = in_array($filter, ['all','unread','read'], true) ? $filter : 'all';

$me = myUsername();            // username from session
$role = myRoleId();            // 1 Admin, 2 Manager, 3 Gospel, 4 Staff
$adminMode = isAdmin();        // admin role?

if ($me === '') die("Session missing username.");

function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : '';
}
function isEmail($s): bool {
    return (strpos($s, '@') !== false);
}

/**
 * Internal channels I am allowed to see.
 * Must be defined in identity.php.
 */
$internalChannels = allowedInternalChannelsForMe();
if (!is_array($internalChannels)) $internalChannels = [];

// -----------------------------
// WHERE for filter
// -----------------------------
$readWhere = "";
if ($filter === 'unread') $readWhere = " AND f.is_read = 0 ";
if ($filter === 'read')   $readWhere = " AND f.is_read = 1 ";

// -----------------------------
// ACTION: MARK ALL READ
// -----------------------------
if (isset($_POST['mark_all_read'])) {
    try {
        if ($adminMode) {
            // mark user->Admin inbox as read
            $mk = $dbh->prepare("
                UPDATE feedback
                SET is_read = 1, read_at = NOW()
                WHERE receiver = 'Admin'
                  AND channel = 'user_admin'
                  AND is_read = 0
            ");
            $mk->execute();

            // mark internal messages TO this admin username as read too
            if (!empty($internalChannels)) {
                $ph = implode(',', array_fill(0, count($internalChannels), '?'));
                $mk2 = $dbh->prepare("
                    UPDATE feedback
                    SET is_read = 1, read_at = NOW()
                    WHERE receiver = ?
                      AND channel IN ($ph)
                      AND is_read = 0
                ");
                $mk2->execute(array_merge([$me], $internalChannels));
            }
        } else {
            if (!empty($internalChannels)) {
                $ph = implode(',', array_fill(0, count($internalChannels), '?'));
                $mk = $dbh->prepare("
                    UPDATE feedback
                    SET is_read = 1, read_at = NOW()
                    WHERE receiver = ?
                      AND channel IN ($ph)
                      AND is_read = 0
                ");
                $mk->execute(array_merge([$me], $internalChannels));
            }
        }

        header("Location: feedback.php?filter=" . urlencode($filter) . "&msg=allread");
        exit;
    } catch (PDOException $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// -----------------------------
// ACTION: MARK ONE THREAD READ
// mark = peer identifier shown in table
// - if peer contains @ => user email (admin only)
// - else => internal username (all roles)
// -----------------------------
if (isset($_GET['mark']) && $_GET['mark'] !== '') {
    $peer = trim($_GET['mark']);

    try {
        if ($adminMode && isEmail($peer)) {
            // mark user thread read (user -> Admin)
            $mk = $dbh->prepare("
                UPDATE feedback
                SET is_read = 1, read_at = NOW()
                WHERE receiver = 'Admin'
                  AND channel = 'user_admin'
                  AND sender = :peer
                  AND is_read = 0
            ");
            $mk->execute([':peer' => $peer]);
        } else {
            // internal thread read: peer is username
            if (isEmail($peer)) {
                // non-admin cannot mark user-email chats
                header("Location: feedback.php?filter=" . urlencode($filter));
                exit;
            }

            if (!empty($internalChannels)) {
                $ph = implode(',', array_fill(0, count($internalChannels), '?'));
                $mk = $dbh->prepare("
                    UPDATE feedback
                    SET is_read = 1, read_at = NOW()
                    WHERE receiver = ?
                      AND channel IN ($ph)
                      AND sender = ?
                      AND is_read = 0
                ");
                $mk->execute(array_merge([$me], $internalChannels, [$peer]));
            }
        }

        header("Location: feedback.php?filter=" . urlencode($filter) . "&msg=threadread");
        exit;

    } catch (PDOException $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// -----------------------------
// ACTION: DELETE ONE THREAD
// del = peer identifier
// -----------------------------
if (isset($_GET['del']) && $_GET['del'] !== '') {
    $peer = trim($_GET['del']);

    try {
        if ($adminMode && isEmail($peer)) {
            // delete user thread both directions
            $del = $dbh->prepare("
                DELETE FROM feedback
                WHERE channel = 'user_admin'
                  AND (
                        (sender = :peer AND receiver = 'Admin')
                     OR (sender = 'Admin' AND receiver = :peer2)
                  )
            ");
            $del->execute([':peer' => $peer, ':peer2' => $peer]);
        } else {
            // delete internal thread both directions
            if (isEmail($peer)) {
                header("Location: feedback.php?filter=" . urlencode($filter));
                exit;
            }

            if (!empty($internalChannels)) {
                $ph = implode(',', array_fill(0, count($internalChannels), '?'));
                $del = $dbh->prepare("
                    DELETE FROM feedback
                    WHERE channel IN ($ph)
                      AND (
                            (sender = ? AND receiver = ?)
                         OR (sender = ? AND receiver = ?)
                      )
                ");
                $del->execute(array_merge($internalChannels, [$me, $peer, $peer, $me]));
            }
        }

        header("Location: feedback.php?filter=" . urlencode($filter) . "&msg=deleted");
        exit;

    } catch (PDOException $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// -----------------------------
// ACTION: DELETE ALL (for this view)
// -----------------------------
if (isset($_POST['delete_all'])) {
    try {
        if ($adminMode) {
            // delete user->Admin inbox
            $del = $dbh->prepare("DELETE FROM feedback WHERE receiver='Admin' AND channel='user_admin'");
            $del->execute();

            // delete internal messages TO me
            if (!empty($internalChannels)) {
                $ph = implode(',', array_fill(0, count($internalChannels), '?'));
                $del2 = $dbh->prepare("
                    DELETE FROM feedback
                    WHERE receiver = ?
                      AND channel IN ($ph)
                ");
                $del2->execute(array_merge([$me], $internalChannels));
            }
        } else {
            if (!empty($internalChannels)) {
                $ph = implode(',', array_fill(0, count($internalChannels), '?'));
                $del = $dbh->prepare("
                    DELETE FROM feedback
                    WHERE receiver = ?
                      AND channel IN ($ph)
                ");
                $del->execute(array_merge([$me], $internalChannels));
            }
        }

        header("Location: feedback.php?filter=" . urlencode($filter) . "&msg=deletedall");
        exit;

    } catch (PDOException $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// -----------------------------
// UI messages
// -----------------------------
if (($_GET['msg'] ?? '') === 'allread')     $msg = "All messages marked as read.";
if (($_GET['msg'] ?? '') === 'threadread')  $msg = "Thread marked as read.";
if (($_GET['msg'] ?? '') === 'deleted')     $msg = "Thread deleted.";
if (($_GET['msg'] ?? '') === 'deletedall')  $msg = "All threads deleted.";

// -----------------------------
// FETCH THREADS
// Show:
// - Admin: user emails (user_admin) + internal threads to/from $me
// - Non-admin: internal threads to/from $me only
// -----------------------------
$threads = [];

try {
    if ($adminMode) {
        // 1) User inbox threads: group by user email (sender)
        $sqlUser = "
            SELECT
              f.sender AS peer,
              MAX(f.created_at) AS last_time,
              SUM(CASE WHEN f.is_read = 0 THEN 1 ELSE 0 END) AS unread_count,
              SUBSTRING_INDEX(
                GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
                ' ||| ', 1
              ) AS last_message
            FROM feedback f
            WHERE f.receiver = 'Admin'
              AND f.channel = 'user_admin'
              $readWhere
            GROUP BY f.sender
        ";

        // 2) Internal threads (all allowed channels): group by other participant
        $sqlInternal = "";
        $paramsInternal = [];
        if (!empty($internalChannels)) {
            $ph = implode(',', array_fill(0, count($internalChannels), '?'));
            $sqlInternal = "
                SELECT
                  CASE
                    WHEN f.sender = ? THEN f.receiver
                    ELSE f.sender
                  END AS peer,
                  MAX(f.created_at) AS last_time,
                  SUM(CASE WHEN f.is_read = 0 AND f.receiver = ? THEN 1 ELSE 0 END) AS unread_count,
                  SUBSTRING_INDEX(
                    GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
                    ' ||| ', 1
                  ) AS last_message
                FROM feedback f
                WHERE (f.sender = ? OR f.receiver = ?)
                  AND f.channel IN ($ph)
                  $readWhere
                GROUP BY peer
            ";
            $paramsInternal = array_merge([$me, $me, $me, $me], $internalChannels);
        }

        // Combine both lists
        $all = [];

        $stmt1 = $dbh->prepare($sqlUser);
        $stmt1->execute();
        $all = array_merge($all, $stmt1->fetchAll(PDO::FETCH_ASSOC));

        if ($sqlInternal !== "") {
            $stmt2 = $dbh->prepare($sqlInternal);
            $stmt2->execute($paramsInternal);
            $all = array_merge($all, $stmt2->fetchAll(PDO::FETCH_ASSOC));
        }

        // Sort by last_time desc
        usort($all, function($a, $b){
            return strtotime($b['last_time'] ?? '1970-01-01') <=> strtotime($a['last_time'] ?? '1970-01-01');
        });

        $threads = $all;

    } else {
        // Non-admin: internal threads only
        if (!empty($internalChannels)) {
            $ph = implode(',', array_fill(0, count($internalChannels), '?'));

            $sql = "
                SELECT
                  CASE
                    WHEN f.sender = ? THEN f.receiver
                    ELSE f.sender
                  END AS peer,
                  MAX(f.created_at) AS last_time,
                  SUM(CASE WHEN f.is_read = 0 AND f.receiver = ? THEN 1 ELSE 0 END) AS unread_count,
                  SUBSTRING_INDEX(
                    GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
                    ' ||| ', 1
                  ) AS last_message
                FROM feedback f
                WHERE (f.sender = ? OR f.receiver = ?)
                  AND f.channel IN ($ph)
                  $readWhere
                GROUP BY peer
                ORDER BY last_time DESC
            ";

            $stmt = $dbh->prepare($sql);
            $params = array_merge([$me, $me, $me, $me], $internalChannels);
            $stmt->execute($params);
            $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $threads = [];
        }
    }

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
  <title><?php echo $adminMode ? 'Admin Chat Inbox' : 'My Chat Inbox'; ?></title>

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

  <h2 class="page-title"><?php echo $adminMode ? 'Chat Inbox' : 'My Chat Inbox'; ?></h2>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">
      <?php echo $adminMode ? 'Messages (Users + Internal)' : 'My Threads'; ?>
    </div>

    <div class="panel-body">

      <div class="actions-bar">
        <div class="tabs">
          <a class="btn btn-default btn-sm <?php echo ($filter==='all')?'active':''; ?>" href="feedback.php?filter=all">All</a>
          <a class="btn btn-default btn-sm <?php echo ($filter==='unread')?'active':''; ?>" href="feedback.php?filter=unread">Unread</a>
          <a class="btn btn-default btn-sm <?php echo ($filter==='read')?'active':''; ?>" href="feedback.php?filter=read">Read</a>
        </div>

        <div style="display:flex;gap:8px;">
          <a class="btn btn-success btn-sm" href="compose.php">
            <i class="fa fa-plus"></i> New Message
          </a>

          <form method="post" style="margin:0;">
            <button type="submit" name="mark_all_read"
                    class="btn btn-default btn-sm"
                    onclick="return confirm('Mark ALL as read?');"
                    <?php echo empty($threads) ? 'disabled' : ''; ?>>
              <i class="fa fa-check"></i> Mark All Read
            </button>
          </form>

          <form method="post" style="margin:0;">
            <button type="submit" name="delete_all"
                    class="btn btn-danger btn-sm"
                    onclick="return confirm('Delete ALL threads?');"
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
            <th><?php echo $adminMode ? 'Peer (Email or Username)' : 'Peer'; ?></th>
            <th>Last Message</th>
            <th>Last Time</th>
            <th>Unread</th>
            <th style="width:180px;">Action</th>
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
                 href="sendreply.php?reply=<?php echo urlencode($peer); ?>"
                 title="Open chat & reply">
                <i class="fa fa-mail-reply"></i> Reply
              </a>

              <?php if ((int)$t['unread_count'] > 0): ?>
                <a class="btn btn-default btn-xs"
                   href="feedback.php?filter=<?php echo urlencode($filter); ?>&mark=<?php echo urlencode($peer); ?>"
                   title="Mark read"
                   onclick="return confirm('Mark this thread read?');">
                  <i class="fa fa-check"></i>
                </a>
              <?php endif; ?>

              <a class="btn btn-danger btn-xs"
                 href="feedback.php?filter=<?php echo urlencode($filter); ?>&del=<?php echo urlencode($peer); ?>"
                 title="Delete thread"
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
$(function(){
  $('#chatTable').DataTable({
    pageLength: 10,
    lengthMenu: [[10,25,50,100],[10,25,50,100]],
    order: [[3, 'desc']]
  });

  setTimeout(function(){
    $('.alert-success,.alert-danger').fadeOut();
  }, 3000);
});
</script>

</body>
</html>
