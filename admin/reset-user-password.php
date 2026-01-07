<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/controller.php';

$controller = new Controller();

$error = '';
$msg = '';
$tempPass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUsername = trim($_POST['username'] ?? '');

    if ($targetUsername === '') {
        $error = "Please enter the user username.";
    } else {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);

        $res = $controller->adminResetUserPassword($adminId, $targetUsername);

        if (!$res['ok']) {
            $error = $res['error'] ?? 'Failed';
        } else {
            $msg = "User password reset successfully. An email was sent to the user.";
            $tempPass = $res['temp_password'] ?? '';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset User Password</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
  <div class="container-fluid">
    <h2 class="page-title">Admin: Reset User Password</h2>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

    <div class="panel panel-default">
      <div class="panel-heading">Reset</div>
      <div class="panel-body">
        <form method="post">
          <div class="form-group">
            <label>User Username</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <button class="btn btn-primary" type="submit">Reset Password</button>
        </form>

        <?php if ($tempPass): ?>
          <hr>
          <div class="alert alert-warning">
            Temporary password (also emailed): <b><?php echo htmlentities($tempPass); ?></b>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>
</div>
</body>
</html>
