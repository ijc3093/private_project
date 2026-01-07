<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/admin/controller.php';

$controller = new Controller();

$type  = trim($_GET['type'] ?? '');
$token = trim($_GET['token'] ?? '');

$error = '';
$msg = '';

if (!in_array($type, ['user','admin'], true) || $token === '') {
    $error = "Invalid reset link.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $new = trim($_POST['newpassword'] ?? '');
    $confirm = trim($_POST['confirmpassword'] ?? '');

    if ($new === '' || $confirm === '') {
        $error = "Please fill both password fields.";
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($new) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $res = $controller->resetPasswordWithToken($type, $token, $new);
        if (!$res['ok']) {
            $error = $res['error'] ?? 'Reset failed.';
        } else {
            $msg = "Password reset successful. You may login now.";
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body class="container" style="max-width:520px;margin-top:40px;">
  <h3>Reset Password</h3>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <?php if (!$msg && !$error): ?>
  <form method="post">
    <div class="form-group">
      <label>New Password</label>
      <input type="password" name="newpassword" class="form-control" required>
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="confirmpassword" class="form-control" required>
    </div>
    <button class="btn btn-primary" type="submit">Reset password</button>
  </form>
  <?php endif; ?>

  <hr>
  <a href="<?php echo $type === 'admin' ? 'admin/index.php' : 'index.php'; ?>">Back to login</a>
</body>
</html>
