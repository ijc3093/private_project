<?php
// /Business_only3/compose_user.php
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

$error = '';
$targets = [];

try {
    // Admin is always available
    $targets[] = [
        'type'  => 'admin',
        'value' => adminInboxKey(),
        'label' => 'Admin (Support)'
    ];

    // Same-role users (exclude self)
    $st = $dbh->prepare("
        SELECT id, name, email, role
        FROM users
        WHERE role = :r
          AND status = 1
          AND email <> :me
        ORDER BY name ASC
    ");
    $st->execute([':r' => $myRole, ':me' => $meEmail]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $u) {
        $targets[] = [
            'type'  => 'user',
            'value' => $u['email'],
            'label' => $u['name'] . " (" . $u['email'] . ")"
        ];
    }
} catch (Throwable $e) {
    $targets = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    if ($to === '') {
        $error = "Please select a recipient.";
    } else {
        header("Location: user_sendreply.php?reply=" . urlencode($to));
        exit;
    }
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Start a chat</title>
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

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlentities($error); ?></div>
  <?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">Start a chat</div>
    <div class="panel-body">

      <?php if (empty($targets)): ?>
        <div class="alert alert-info">No available recipients.</div>
      <?php else: ?>
        <form method="post">
          <div class="form-group">
            <label>To</label>
            <select class="form-control" name="to" required>
              <option value="">-- Select --</option>
              <?php foreach ($targets as $t): ?>
                <option value="<?php echo htmlentities($t['value']); ?>">
                  <?php echo htmlentities($t['label']); ?>
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
