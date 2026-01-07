<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/admin/controller.php';

$controller = new Controller();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if ($username === '') {
        $error = "Please enter your username.";
    } else {
        // Always returns ok=true (prevents username probing)
        $res = $controller->createUserReset($username);

        if (!empty($res['email']) && !empty($res['token'])) {
            // Send email with reset link
            require_once __DIR__ . '/includes/mailer.php';

            $link = "http://localhost:8888/Business_only3/reset.php?type=user&token=" . urlencode($res['token']);

            $subject = "Reset your password";
            $html = "
              <h3>Reset Password</h3>
              <p>Click the link below to reset your password (expires in 30 minutes):</p>
              <p><a href='".htmlspecialchars($link)."'>Reset Password</a></p>
              <p>If you did not request this, you can ignore this email.</p>
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
  <title>Forgot Password</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body class="container" style="max-width:520px;margin-top:40px;">
  <h3>Forgot Password</h3>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <form method="post">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <button class="btn btn-primary" type="submit">Send reset link</button>
    <a class="btn btn-link" href="index.php">Back to login</a>
  </form>
</body>
</html>
