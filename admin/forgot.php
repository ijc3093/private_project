<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/controller.php';

$controller = new Controller();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if ($username === '') {
        $error = "Please enter your admin username.";
    } else {
        $res = $controller->createAdminReset($username);

        if (!empty($res['email']) && !empty($res['token'])) {
            require_once __DIR__ . '/../includes/mailer.php';

            $link = "http://localhost:8888/Business_only3/reset.php?type=admin&token=" . urlencode($res['token']);

            $subject = "Reset your admin password";
            $html = "
              <h3>Admin Password Reset</h3>
              <p>Click the link below to reset your password (expires in 30 minutes):</p>
              <p><a href='".htmlspecialchars($link)."'>Reset Admin Password</a></p>
            ";
            sendNotificationEmail($res['email'], $subject, $html);
        }

        $msg = "If that username exists, a reset link has been sent to the email on file.";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Forgot Password</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body class="container" style="max-width:520px;margin-top:40px;">
  <h3>Admin Forgot Password</h3>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <form method="post">
    <div class="form-group">
      <label>Admin Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <button class="btn btn-primary" type="submit">Send reset link</button>
    <a class="btn btn-link" href="index.php">Back to login</a>
  </form>
</body>
</html>
