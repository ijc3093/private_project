<?php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/identity_user.php';
require_once __DIR__ . '/admin/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$meId = myUserId();
$msg = '';
$error = '';

function upsertContactPair(PDO $dbh, int $a, int $b): void {
    $st = $dbh->prepare("INSERT IGNORE INTO contacts (user_id, contact_user_id) VALUES (:a,:b),(:b,:a)");
    $st->execute([':a'=>$a, ':b'=>$b]);
}

if (isset($_POST['accept'])) {
    $reqId = (int)($_POST['id'] ?? 0);
    if ($reqId > 0) {
        $dbh->beginTransaction();
        $rowSt = $dbh->prepare("SELECT from_user_id, to_user_id FROM contact_requests WHERE id=:id AND to_user_id=:me AND status='pending' LIMIT 1");
        $rowSt->execute([':id'=>$reqId, ':me'=>$meId]);
        $row = $rowSt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $from = (int)$row['from_user_id'];
            $to   = (int)$row['to_user_id'];

            $up = $dbh->prepare("UPDATE contact_requests SET status='accepted' WHERE id=:id");
            $up->execute([':id'=>$reqId]);

            // also mark reverse as accepted (optional)
            $ins = $dbh->prepare("
                INSERT INTO contact_requests (from_user_id, to_user_id, status)
                VALUES (:f,:t,'accepted')
                ON DUPLICATE KEY UPDATE status='accepted'
            ");
            $ins->execute([':f'=>$to, ':t'=>$from]);

            upsertContactPair($dbh, $from, $to);
            $dbh->commit();
            $msg = "Request accepted. You are now contacts.";
        } else {
            $dbh->rollBack();
            $error = "Request not found.";
        }
    }
}

if (isset($_POST['decline'])) {
    $reqId = (int)($_POST['id'] ?? 0);
    if ($reqId > 0) {
        $st = $dbh->prepare("UPDATE contact_requests SET status='declined' WHERE id=:id AND to_user_id=:me AND status='pending'");
        $st->execute([':id'=>$reqId, ':me'=>$meId]);
        $msg = "Request declined.";
    }
}

$stmt = $dbh->prepare("
  SELECT cr.id, cr.created_at, u.name, u.username, u.email
  FROM contact_requests cr
  JOIN users u ON u.id = cr.from_user_id
  WHERE cr.to_user_id = :me AND cr.status='pending'
  ORDER BY cr.created_at DESC
");
$stmt->execute([':me'=>$meId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmt_dt($dt){ return $dt ? date('M d, Y h:i A', strtotime($dt)) : ''; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contact Requests</title>
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">Contact Requests</h2>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">Pending Requests</div>
    <div class="panel-body">
      <?php if (empty($requests)): ?>
        <div class="alert alert-info">No pending requests.</div>
      <?php else: ?>
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>From</th>
              <th>Username</th>
              <th>Email</th>
              <th>Time</th>
              <th style="width:200px;">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td><?php echo htmlentities($r['name'] ?? ''); ?></td>
              <td><?php echo htmlentities($r['username'] ?? ''); ?></td>
              <td><?php echo htmlentities($r['email'] ?? ''); ?></td>
              <td><?php echo htmlentities(fmt_dt($r['created_at'])); ?></td>
              <td>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-success btn-xs" name="accept" type="submit">
                    <i class="fa fa-check"></i> Accept
                  </button>
                </form>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-danger btn-xs" name="decline" type="submit">
                    <i class="fa fa-times"></i> Decline
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
setTimeout(function(){ $('.alert-success,.alert-danger').fadeOut(); }, 2500);
</script>
</body>
</html>
