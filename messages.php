<?php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/admin/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$me = $_SESSION['user_login'];

$error = '';
$msg   = '';

// filter: all | unread | read
$filter = strtolower(trim($_GET['filter'] ?? 'all'));
if (!in_array($filter, ['all','unread','read'], true)) $filter = 'all';

// --------------------
// Delete one
// --------------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $dbh->prepare("DELETE FROM feedback WHERE id = :id AND receiver = :me");
        $stmt->execute([':id' => $id, ':me' => $me]);
        header("Location: messages.php?filter=" . urlencode($filter) . "&msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

// --------------------
// Delete all (only my inbox)
// --------------------
if (isset($_POST['delete_all'])) {
    try {
        $stmt = $dbh->prepare("DELETE FROM feedback WHERE receiver = :me");
        $stmt->execute([':me' => $me]);
        header("Location: messages.php?msg=all_deleted");
        exit;
    } catch (PDOException $e) {
        $error = "DB error: " . $e->getMessage();
    }
}

if (($_GET['msg'] ?? '') === 'deleted') $msg = "Message deleted successfully.";
if (($_GET['msg'] ?? '') === 'all_deleted') $msg = "All messages deleted successfully.";

// --------------------
// Load inbox (Admin -> user)
// (If you want to show ALL senders to this user, remove sender='Admin')
// --------------------
$where = "receiver = :me";
$params = [':me' => $me];

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where .= " AND is_read = 1";
}

try {
    $stmt = $dbh->prepare("
        SELECT id, sender, feedbackdata, created_at, is_read
        FROM feedback
        WHERE $where
        ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rows = [];
    $error = "DB error: " . $e->getMessage();
}

// counts for tabs
try {
    $cAll = (int)$dbh->prepare("SELECT COUNT(*) FROM feedback WHERE receiver = :me")
        ->execute([':me'=>$me]) ?: 0;
} catch(Throwable $e) { $cAll = null; }

try {
    $st = $dbh->prepare("SELECT COUNT(*) FROM feedback WHERE receiver = :me AND is_read=0");
    $st->execute([':me'=>$me]);
    $unreadCount = (int)$st->fetchColumn();
} catch(Throwable $e) { $unreadCount = 0; }

try {
    $st = $dbh->prepare("SELECT COUNT(*) FROM feedback WHERE receiver = :me AND is_read=1");
    $st->execute([':me'=>$me]);
    $readCount = (int)$st->fetchColumn();
} catch(Throwable $e) { $readCount = 0; }

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
<title>Messages</title>

<link rel="stylesheet" href="css/font-awesome.min.css">
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
<link rel="stylesheet" href="css/style.css">

<style>
.errorWrap{padding:10px;background:#dd3d36;color:#fff;margin-bottom:15px;}
.succWrap{padding:10px;background:#5cb85c;color:#fff;margin-bottom:15px;}
.actions-bar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin:10px 0 15px;flex-wrap:wrap;}
.unreadRow{font-weight:700;}
.tabbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
.tabbar a{padding:6px 12px;border:1px solid #ddd;border-radius:18px;text-decoration:none;}
.tabbar a.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.badge-pill{background:red;color:#fff;border-radius:12px;padding:2px 7px;font-size:11px;margin-left:6px;}
</style>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

<h2 class="page-title">Messages</h2>

<?php if($error): ?><div class="errorWrap"><?php echo htmlentities($error); ?></div><?php endif; ?>
<?php if($msg): ?><div class="succWrap"><?php echo htmlentities($msg); ?></div><?php endif; ?>

<div class="tabbar">
  <a class="<?php echo $filter==='all'?'active':''; ?>" href="messages.php?filter=all">
    All
  </a>

  <a class="<?php echo $filter==='unread'?'active':''; ?>" href="messages.php?filter=unread">
    Unread <?php if($unreadCount>0): ?><span class="badge-pill"><?php echo $unreadCount>99?'99+':$unreadCount; ?></span><?php endif; ?>
  </a>

  <a class="<?php echo $filter==='read'?'active':''; ?>" href="messages.php?filter=read">
    Read
  </a>
</div>

<div class="panel panel-default">
<div class="panel-heading">
  Inbox: <strong><?php echo htmlentities($me); ?></strong>
</div>

<div class="panel-body">

<div class="actions-bar">
  <div>
    <strong>Total shown:</strong> <?php echo (int)count($rows); ?>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <button class="btn btn-info btn-sm" id="btnMarkAll" <?php echo ($unreadCount===0?'disabled':''); ?>>
      <i class="fa fa-check"></i> Mark All Read
    </button>

    <form method="post" style="margin:0;">
      <button type="submit" name="delete_all" class="btn btn-danger btn-sm"
        onclick="return confirm('Delete ALL messages? This cannot be undone!');"
        <?php echo (count($rows)===0?'disabled':''); ?>>
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
  <th>Message</th>
  <th>Date &amp; Time</th>
  <th>Status</th>
  <th style="width:120px;">Action</th>
</tr>
</thead>
<tbody>
<?php $i=1; foreach($rows as $r): ?>
<tr class="<?php echo ((int)$r['is_read']===0)?'unreadRow':''; ?>">
  <td><?php echo $i++; ?></td>
  <td><?php echo htmlentities($r['sender']); ?></td>
  <td><?php echo htmlentities($r['feedbackdata']); ?></td>
  <td><?php echo htmlentities(fmt_dt($r['created_at'])); ?></td>
  <td>
    <?php if((int)$r['is_read']===1): ?>
      <span class="label label-success">Read</span>
    <?php else: ?>
      <span class="label label-warning">Unread</span>
    <?php endif; ?>
  </td>
  <td>
    <a href="#" class="markReadBtn" data-id="<?php echo (int)$r['id']; ?>" title="Mark Read">
      <i class="fa fa-check text-success"></i>
    </a>

    <a href="feedback.php?reply=<?php echo urlencode($r['sender']); ?>" title="Reply">
      <i class="fa fa-mail-reply text-primary"></i>
    </a>

    <a href="messages.php?filter=<?php echo urlencode($filter); ?>&delete=<?php echo (int)$r['id']; ?>"
       onclick="return confirm('Delete this message?');" title="Delete">
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
    lengthMenu: [[10,25,50,100],[10,25,50,100]]
  });

  setTimeout(()=>$('.succWrap,.errorWrap').fadeOut(), 3000);

  // mark one read
  $(document).on('click', '.markReadBtn', async function(e){
    e.preventDefault();
    const id = $(this).data('id');
    const fd = new FormData();
    fd.append('id', id);
    const res = await fetch('api/chat_mark_read.php', { method:'POST', body: fd });
    const data = await res.json();
    if (data.ok) location.reload();
    else alert(data.error || 'Failed');
  });

  // mark all read
  $('#btnMarkAll').on('click', async function(){
    if(!confirm('Mark ALL messages as read?')) return;
    const res = await fetch('api/chat_mark_all_read.php', { method:'POST' });
    const data = await res.json();
    if (data.ok) location.reload();
    else alert(data.error || 'Failed');
  });
});
</script>

</body>
</html>
