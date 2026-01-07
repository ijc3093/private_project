<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

function getClientIp(): string {
    $keys = ['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return 'unknown';
}

function getUserAgent(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return substr($ua, 0, 255);
}

function logSecurity(PDO $dbh, ?string $email, ?int $adminId, string $action, bool $success, array $meta = []): void {
    $st = $dbh->prepare("
        INSERT INTO admin_security_log (email, admin_id, action, success, ip, user_agent, meta)
        VALUES (:email, :admin_id, :action, :success, :ip, :ua, :meta)
    ");
    $st->execute([
        ':email'    => $email,
        ':admin_id' => $adminId,
        ':action'   => $action,
        ':success'  => $success ? 1 : 0,
        ':ip'       => getClientIp(),
        ':ua'       => getUserAgent(),
        ':meta'     => $meta ? json_encode($meta) : null
    ]);
}

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');

$error = '';
$msg = '';

if ($email === '' || $token === '') {
    die("Invalid reset link.");
}

$tokenHash = hash('sha256', $token);

$st = $dbh->prepare("
    SELECT idadmin, reset_token_hash, reset_token_expires, status
    FROM admin
    WHERE email = :e
    LIMIT 1
");
$st->execute([':e' => $email]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row || (int)$row['status'] !== 1) {
    logSecurity($dbh, $email, null, 'reset_password', false, ['reason' => 'not_found_or_inactive']);
    die("Reset link expired or invalid.");
}

$adminId = (int)$row['idadmin'];

if (empty($row['reset_token_hash']) || empty($row['reset_token_expires'])) {
    logSecurity($dbh, $email, $adminId, 'reset_password', false, ['reason' => 'missing_token']);
    die("Reset link expired or invalid.");
}

if (!hash_equals((string)$row['reset_token_hash'], $tokenHash)) {
    logSecurity($dbh, $email, $adminId, 'reset_password', false, ['reason' => 'token_mismatch']);
    die("Reset link expired or invalid.");
}

if (strtotime((string)$row['reset_token_expires']) < time()) {
    logSecurity($dbh, $email, $adminId, 'reset_password', false, ['reason' => 'token_expired']);
    die("Reset link expired.");
}

if (isset($_POST['submit'])) {
    $new = trim($_POST['newpassword'] ?? '');
    $confirm = trim($_POST['confirmpassword'] ?? '');

    if ($new === '' || $confirm === '') {
        $error = "All fields required.";
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);

        $up = $dbh->prepare("
            UPDATE admin
            SET password = :p,
                force_password_change = 0,
                reset_token_hash = NULL,
                reset_token_expires = NULL
            WHERE idadmin = :id
            LIMIT 1
        ");
        $up->execute([
            ':p' => $newHash,
            ':id' => $adminId
        ]);

        logSecurity($dbh, $email, $adminId, 'reset_password', true, ['note' => 'password_updated']);

        $msg = "Password reset successful. You can login now.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container" style="max-width:520px;margin-top:40px;">
  <h3>Reset Password</h3>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <?php if (!$msg): ?>
  <form method="post" autocomplete="off">
    <div class="form-group">
      <label>New Password</label>
      <input class="form-control" type="password" name="newpassword" required>
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input class="form-control" type="password" name="confirmpassword" required>
    </div>
    <button class="btn btn-primary" name="submit" type="submit">Save New Password</button>
  </form>
  <?php else: ?>
    <a class="btn btn-default" href="index.php" style="margin-top:10px;">Back to Login</a>
  <?php endif; ?>
</div>
</body>
</html>
