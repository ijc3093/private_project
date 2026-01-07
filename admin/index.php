<?php
// /Business_only3/admin/index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/session_admin.php'; // starts admin session
require_once __DIR__ . '/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$error = '';

// already logged in
if (!empty($_SESSION['admin_login']) && !empty($_SESSION['admin_id']) && !empty($_SESSION['userRole'])) {
    header("Location: dashboard.php");
    exit;
}

if (isset($_POST['login'])) {
    $login = trim($_POST['username'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if ($login === '' || $pass === '') {
        $error = "Please enter username/email and password.";
    } else {

        // Fetch admin row for status / lockout / force_password_change
        $st = $dbh->prepare("
            SELECT
                idadmin, fullname, username, email, password, role, status,
                force_password_change, failed_login_attempts, locked_until,
                friend_code, image
            FROM admin
            WHERE (username = :u OR email = :e)
            LIMIT 1
        ");
        $st->execute([
            ':u' => $login,
            ':e' => $login
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $error = "Invalid login credentials.";
        } elseif ((int)$row['status'] !== 1) {
            $error = "Your account is inactive. Contact Admin.";
        } elseif (!empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            $error = "Account temporarily locked. Try again later.";
        } else {

            // Verify password using Controller helper (supports legacy hashes + upgrades)
            $admin = $controller->adminLogin($login, $pass);

            if (!$admin) {
                // Failed login
                $attempts = (int)($row['failed_login_attempts'] ?? 0) + 1;
                $lockedUntil = null;

                if ($attempts >= 5) {
                    $lockedUntil = date('Y-m-d H:i:s', time() + 15 * 60);
                    $attempts = 0; // reset after lock
                }

                $up = $dbh->prepare("
                    UPDATE admin
                    SET failed_login_attempts = :attempts,
                        locked_until = :locked_until
                    WHERE idadmin = :idadmin
                    LIMIT 1
                ");
                $up->execute([
                    ':attempts'     => $attempts,
                    ':locked_until' => $lockedUntil,
                    ':idadmin'      => (int)$row['idadmin'],
                ]);

                $error = "Invalid login credentials.";
            } else {
                // Success login
                $up = $dbh->prepare("
                    UPDATE admin
                    SET failed_login_attempts = 0,
                        locked_until = NULL,
                        last_login_at = NOW()
                    WHERE idadmin = :idadmin
                    LIMIT 1
                ");
                $up->execute([
                    ':idadmin' => (int)$row['idadmin'],
                ]);

                // Set session (admin cookie name handled in session_admin.php)
                setAdminSession([
                    'idadmin'     => (int)$row['idadmin'],
                    'username'    => (string)$row['username'],
                    'email'       => (string)$row['email'],
                    'role'        => (int)$row['role'],
                    'image'       => (string)($row['image'] ?? 'default.jpg'),
                    'friend_code' => (string)($row['friend_code'] ?? ''),
                ]);

                // Force password change
                if ((int)($row['force_password_change'] ?? 0) === 1) {
                    header("Location: change-password.php?force=1");
                    exit;
                }

                header("Location: dashboard.php");
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .errorWrap{padding:10px;margin:0 0 15px 0;background:#dd3d36;color:#fff;}
  </style>
</head>
<body>
<div class="login-page bk-img" style="background-image: url(img/background.jpg);">
  <div class="form-content">
    <div class="container">
      <div class="row">
        <div class="col-md-6 col-md-offset-3">

          <h1 class="text-center text-bold mt-4x">Admin Login</h1>

          <div class="well row pt-2x pb-3x bk-light">
            <div class="col-md-8 col-md-offset-2">

              <?php if ($error !== ''): ?>
                <div class="errorWrap"><strong>ERROR:</strong> <?php echo htmlentities($error); ?></div>
              <?php endif; ?>

              <form method="post" autocomplete="off">
                <label class="text-uppercase text-sm">Username or Email</label>
                <input type="text" placeholder="Username or Email" name="username" class="form-control mb" required>

                <label class="text-uppercase text-sm">Password</label>
                <input type="password" placeholder="Password" name="password" class="form-control mb" required>

                <button class="btn btn-primary btn-block" type="submit" name="login" value="1">LOGIN</button>

                <div style="margin-top:10px;text-align:center;">
                  <a href="forgot.php">Forgot password?</a>
                </div>
              </form>

            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
