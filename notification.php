<?php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/admin/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$email = $_SESSION['user_login'];

$msg = '';
$error = '';

// FILTER
$filter = $_GET['filter'] ?? 'all'; // all | unread | read
$whereRead = "";
if ($filter === 'unread') $whereRead = " AND is_read = 0 ";
if ($filter === 'read')   $whereRead = " AND is_read = 1 ";

// DELETE ONE
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $stmt = $dbh->prepare("DELETE FROM notification WHERE id = :id AND notireceiver = :email");
    $stmt->execute([':id' => $id, ':email' => $email]);
    $msg = "Notification deleted.";
}

// DELETE ALL
if (isset($_POST['delete_all'])) {
    $stmt = $dbh->prepare("DELETE FROM notification WHERE notireceiver = :email");
    $stmt->execute([':email' => $email]);
    $msg = "All notifications deleted.";
}

// LOAD COUNTS (for buttons/badges)
$unreadCount = 0;
$stmtC = $dbh->prepare("SELECT COUNT(*) FROM notification WHERE notireceiver = :email AND is_read = 0");
$stmtC->execute([':email' => $email]);
$unreadCount = (int)$stmtC->fetchColumn();

// LOAD NOTIFICATIONS
$stmt = $dbh->prepare("
    SELECT id, notiuser, notitype, created_at, is_read
    FROM notification
    WHERE notireceiver = :email
    $whereRead
    ORDER BY created_at DESC
");
$stmt->execute([':email' => $email]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : 'N/A';
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .succWrap{ padding:10px; background:#5cb85c; color:#fff; margin:0 0 15px; }
    .errorWrap{ padding:10px; background:#dd3d36; color:#fff; margin:0 0 15px; }
    .unread { font-weight:700; }
    .action-icons a { margin-right:10px; font-size:16px; }
    .top-actions { display:flex; gap:10px; justify-content:space-between; margin-bottom:10px; flex-wrap:wrap; }
    .filter-btns a { margin-right:8px; }
    .badge-red { background:red;color:#fff;border-radius:10px;padding:2px 8px;font-size:12px; }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">
    Notification
    <?php if ($unreadCount > 0): ?>
      <span class="badge-red"><?php echo $unreadCount; ?> Unread</span>
    <?php endif; ?>
  </h2>

  <?php if ($error): ?><div class="errorWrap"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="succWrap"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">Notification List</div>
    <div class="panel-body">

      <div class="top-actions">
        <div class="filter-btns">
          <a class="btn btn-default btn-sm" href="notification.php?filter=all">All</a>
          <a class="btn btn-warning btn-sm" href="notification.php?filter=unread">Unread</a>
          <a class="btn btn-success btn-sm" href="notification.php?filter=read">Read</a>
        </div>

        <div>
          <button class="btn btn-info btn-sm" id="btnMarkAll" type="button">
            <i class="fa fa-check"></i> Mark All Read
          </button>

          <form method="post" style="display:inline;">
            <button class="btn btn-danger btn-sm" type="submit" name="delete_all"
              onclick="return confirm('Delete ALL notifications?');"
              <?php echo empty($rows) ? 'disabled' : ''; ?>>
              <i class="fa fa-trash"></i> Delete All
            </button>
          </form>
        </div>
      </div>

      <table id="zctb" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>From</th>
            <th>Notification</th>
            <th>Date &amp; Time</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($rows as $r): ?>
          <tr class="<?php echo ((int)$r['is_read'] === 0) ? 'unread' : ''; ?>" data-row="<?php echo (int)$r['id']; ?>">
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlentities($r['notiuser']); ?></td>
            <td><?php echo htmlentities($r['notitype']); ?></td>
            <td><?php echo htmlentities(fmt_dt($r['created_at'])); ?></td>
            <td class="statusCell">
              <?php if ((int)$r['is_read'] === 1): ?>
                <span class="label label-success">Read</span>
              <?php else: ?>
                <span class="label label-warning">Unread</span>
              <?php endif; ?>
            </td>
            <td class="action-icons">
              <?php if ((int)$r['is_read'] === 0): ?>
              <a href="#" class="markReadBtn" data-id="<?php echo (int)$r['id']; ?>" title="Mark Read">
                <i class="fa fa-check text-success"></i>
              </a>
              <?php endif; ?>

              <a href="notification.php?filter=<?php echo urlencode($filter); ?>&del=<?php echo (int)$r['id']; ?>"
                 onclick="return confirm('Delete this notification?');" title="Delete">
                <i class="fa fa-trash text-danger"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

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
  $('#zctb').DataTable({
    pageLength: 10,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
  });

  // Mark ONE read
  $(document).on('click', '.markReadBtn', function(e){
    e.preventDefault();
    const id = $(this).data('id');

    if (!confirm('Mark this notification as read?')) return;

    $.post('/Business_only/ajax/user_mark_read.php', { id: id }, function(resp){
      if (resp && resp.ok) location.reload();
      else alert(resp.error || 'Failed to mark read');
    }, 'json').fail(function(){
      alert('Request failed. Check file path and PHP errors.');
    });
  });

  // Mark ALL read
  $('#btnMarkAll').on('click', function(){
    if (!confirm('Mark ALL notifications as read?')) return;

    $.post('/Business_only/ajax/user_mark_all_read.php', {}, function(resp){
      if (resp && resp.ok) location.reload();
      else alert(resp.error || 'Failed to mark all read');
    }, 'json').fail(function(){
      alert('Request failed. Check file path and PHP errors.');
    });
  });
});
</script>


</body>
</html>
