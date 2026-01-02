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

$keys = myNotificationReceiverKeys();
if (empty($keys)) die("Session missing username.");

$ph = implode(',', array_fill(0, count($keys), '?'));

// FILTER
$view = $_GET['view'] ?? 'all';
$view = in_array($view, ['all','unread','read'], true) ? $view : 'all';

$whereRead = '';
if ($view === 'unread') $whereRead = " AND is_read = 0 ";
if ($view === 'read')   $whereRead = " AND is_read = 1 ";

// MARK ONE READ
if (isset($_GET['mark']) && $_GET['mark'] !== '') {
    $id = (int)$_GET['mark'];
    try {
        $sql = "UPDATE notification SET is_read=1, read_at=NOW()
                WHERE id = ? AND notireceiver IN ($ph)";
        $st = $dbh->prepare($sql);
        $st->execute(array_merge([$id], $keys));
        header("Location: notification.php?view=" . urlencode($view) . "&msg=readone");
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// MARK ALL READ
if (isset($_POST['mark_all_read'])) {
    try {
        $sql = "UPDATE notification SET is_read=1, read_at=NOW()
                WHERE notireceiver IN ($ph) AND is_read=0";
        $st = $dbh->prepare($sql);
        $st->execute($keys);
        header("Location: notification.php?view=" . urlencode($view) . "&msg=readall");
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// DELETE ONE
if (isset($_GET['del']) && $_GET['del'] !== '') {
    $id = (int)$_GET['del'];
    try {
        $sql = "DELETE FROM notification WHERE id = ? AND notireceiver IN ($ph)";
        $st = $dbh->prepare($sql);
        $st->execute(array_merge([$id], $keys));
        header("Location: notification.php?view=" . urlencode($view) . "&msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// DELETE ALL
if (isset($_POST['delete_all'])) {
    try {
        $sql = "DELETE FROM notification WHERE notireceiver IN ($ph)";
        $st = $dbh->prepare($sql);
        $st->execute($keys);
        header("Location: notification.php?view=" . urlencode($view) . "&msg=deletedall");
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

if (($_GET['msg'] ?? '') === 'readone')    $msg = "Notification marked as read.";
if (($_GET['msg'] ?? '') === 'readall')    $msg = "All notifications marked as read.";
if (($_GET['msg'] ?? '') === 'deleted')    $msg = "Notification deleted.";
if (($_GET['msg'] ?? '') === 'deletedall') $msg = "All notifications deleted.";

// RESULTS
$results = [];
try {
    $sql = "SELECT id, notiuser, notitype, created_at, is_read
            FROM notification
            WHERE notireceiver IN ($ph)
            $whereRead
            ORDER BY created_at DESC";
    $st = $dbh->prepare($sql);
    $st->execute($keys);
    $results = $st->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $error = $e->getMessage();
}

// COUNTS
$countAll = $countUnread = $countRead = 0;
try {
    $st = $dbh->prepare("SELECT COUNT(*) FROM notification WHERE notireceiver IN ($ph)");
    $st->execute($keys);
    $countAll = (int)$st->fetchColumn();

    $st = $dbh->prepare("SELECT COUNT(*) FROM notification WHERE notireceiver IN ($ph) AND is_read=0");
    $st->execute($keys);
    $countUnread = (int)$st->fetchColumn();

    $st = $dbh->prepare("SELECT COUNT(*) FROM notification WHERE notireceiver IN ($ph) AND is_read=1");
    $st->execute($keys);
    $countRead = (int)$st->fetchColumn();
} catch (Throwable $e) {}

function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : 'N/A';
}

$title = isAdmin() ? 'Admin Notifications' : 'My Notifications';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notification List</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">


  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">Notification List</div>
    <div class="panel-body">

      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
          <a class="btn btn-default btn-sm <?php echo ($view==='all')?'btn-primary':''; ?>" href="notification.php?view=all">All (<?php echo $countAll; ?>)</a>
          <a class="btn btn-default btn-sm <?php echo ($view==='unread')?'btn-primary':''; ?>" href="notification.php?view=unread">Unread (<?php echo $countUnread; ?>)</a>
          <a class="btn btn-default btn-sm <?php echo ($view==='read')?'btn-primary':''; ?>" href="notification.php?view=read">Read (<?php echo $countRead; ?>)</a>
        </div>

        <div style="display:flex;gap:8px;">
          <form method="post" style="margin:0;">
            <button class="btn btn-info btn-sm" name="mark_all_read" type="submit"
              onclick="return confirm('Mark ALL as read?');"
              <?php echo ($countUnread===0) ? 'disabled' : ''; ?>>
              <i class="fa fa-check"></i> Mark All Read
            </button>
          </form>

          <form method="post" style="margin:0;">
            <button class="btn btn-danger btn-sm" name="delete_all" type="submit"
              onclick="return confirm('Delete ALL notifications?');"
              <?php echo ($countAll===0) ? 'disabled' : ''; ?>>
              <i class="fa fa-trash"></i> Delete All
            </button>
          </form>
        </div>
      </div>

      <table id="notiTable" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>From</th>
            <th>Type</th>
            <th>Date</th>
            <th>Status</th>
            <th style="width:140px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($results as $r): ?>
          <tr class="<?php echo ((int)$r->is_read===0) ? 'unread-row' : ''; ?>">
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlentities($r->notiuser); ?></td>
            <td><?php echo htmlentities($r->notitype); ?></td>
            <td><?php echo htmlentities(fmt_dt($r->created_at)); ?></td>
            <td>
              <?php if ((int)$r->is_read===1): ?>
                <span class="label label-success">Read</span>
              <?php else: ?>
                <span class="label label-warning">Unread</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int)$r->is_read===0): ?>
                <a class="btn btn-success btn-xs"
                   href="notification.php?view=<?php echo urlencode($view); ?>&mark=<?php echo (int)$r->id; ?>"
                   onclick="return confirm('Mark as read?');">
                   <i class="fa fa-check"></i>
                </a>
              <?php endif; ?>

              <a class="btn btn-danger btn-xs"
                 href="notification.php?view=<?php echo urlencode($view); ?>&del=<?php echo (int)$r->id; ?>"
                 onclick="return confirm('Delete this notification?');">
                 <i class="fa fa-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if (empty($results)): ?>
        <div class="alert alert-info" style="margin-top:12px;">No notifications found.</div>
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
  $('#notiTable').DataTable({ pageLength: 10 });
});
</script>

</body>
</html>
