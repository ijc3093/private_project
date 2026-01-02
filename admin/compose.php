<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$me   = myUsername();
$role = myRoleId();

if ($me === '') die("Session missing username.");

$error = '';
$targets = [];

// ✅ Admin can message Manager + Staff
// ✅ Manager can message Admin only
// ✅ Staff can message Admin only
// (Gospel role 3 = no internal chat, unless you add it)
try {
    if ($role === 1) {
    $stmt = $dbh->prepare("SELECT username, role FROM admin WHERE role IN (1,2,4) AND status=1 AND username <> :me ORDER BY role, username");
    $stmt->execute([':me'=>$me]);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role === 2) {
        $stmt = $dbh->prepare("SELECT username, role FROM admin WHERE role IN (1,2) AND status=1 AND username <> :me ORDER BY role, username");
        $stmt->execute([':me'=>$me]);
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role === 4) {
        $stmt = $dbh->prepare("SELECT username, role FROM admin WHERE role IN (1,4) AND status=1 AND username <> :me ORDER BY role, username");
        $stmt->execute([':me'=>$me]);
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {
    $targets = [];
}

$roleMap = [1=>'Admin',2=>'Manager',3=>'Gospel',4=>'Staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    if ($to === '') {
        $error = "Please select a recipient.";
    } else {
        // Redirect into the same chat UI you already have
        header("Location: sendreply.php?reply=" . urlencode($to));
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Start a Private Chat</title>

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

  <!-- <h2 class="page-title">Compose</h2> -->

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlentities($error); ?></div>
  <?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">Start a private chat</div>
    <div class="panel-body">

      <?php if (empty($targets)): ?>
        <div class="alert alert-info">No available recipients for your role.</div>
      <?php else: ?>

      <form method="post" autocomplete="off">
        <div class="form-group">
          <label>To (username)</label>
          <select class="form-control" name="to" required>
            <option value="">-- Select --</option>
            <?php foreach ($targets as $t): ?>
              <option value="<?php echo htmlentities($t['username']); ?>">
                <?php echo htmlentities($t['username']); ?>
                (<?php echo htmlentities($roleMap[(int)$t['role']] ?? ''); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fa fa-paper-plane"></i> Start Chat
        </button>
      </form>

      <?php endif; ?>

    </div>
  </div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
