<?php
// Business_only/admin/index.php

require_once __DIR__ . '/includes/session_admin.php'; // starts admin session cookie BUSINESS_ONLY_ADMIN
require_once __DIR__ . '/controller.php';          // controller.php is in /Business_only/controller.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password        = trim($_POST['password'] ?? '');
    $remember        = isset($_POST['remember']) ? 1 : 0;

    if ($usernameOrEmail === '' || $password === '') {
        $error = "Please enter username/email and password.";
    } else {

        try {
            $controller = new Controller();
            $dbh = $controller->pdo();

            // ✅ IMPORTANT: two placeholders, NOT the same one twice
            $sql = "SELECT idadmin, username, email, password, role, status
                    FROM admin
                    WHERE username = :u1 OR email = :u2
                    LIMIT 1";
            $stmt = $dbh->prepare($sql);

            // ✅ Must match placeholders exactly (:u1, :u2)
            $stmt->execute([
                ':u1' => $usernameOrEmail,
                ':u2' => $usernameOrEmail,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $error = "Login failed! Invalid username/email or password.";
            } elseif ((int)$row['status'] !== 1) {
                $error = "Account not confirmed/active.";
            } else {

                // ✅ Password check (supports password_hash or md5 fallback)
                $dbHash = (string)$row['password'];

                $ok = false;
                if (password_get_info($dbHash)['algo'] !== 0) {
                    $ok = password_verify($password, $dbHash);
                } else {
                    // legacy md5
                    $ok = (md5($password) === $dbHash);
                }

                if (!$ok) {
                    $error = "Login failed! Invalid username/email or password.";
                } else {

                    // ✅ Set ADMIN-only session keys (NO conflict with user site)
                    $_SESSION['admin_login'] = $row['username'];
                    $_SESSION['userRole']    = (int)$row['role'];
                    $_SESSION['admin_id']  = (int)$row['idadmin'];

                    // Optional cookie
                    if ($remember === 1) {
                        setcookie("admin_remember", "1", time() + (7 * 24 * 60 * 60), "/");
                    } else {
                        setcookie("admin_remember", "", time() - 3600, "/");
                    }

                    header("Location: dashboard.php");
                    exit;
                }
            }

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">

    <title>Admin Login</title>

    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        .errorWrap{
            padding:10px;
            margin:0 0 15px 0;
            background:#dd3d36;
            color:#fff;
            box-shadow:0 1px 1px 0 rgba(0,0,0,.1);
        }
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

                            <?php if ($error !== '') { ?>
                                <div class="errorWrap"><strong>ERROR:</strong> <?php echo htmlentities($error); ?></div>
                            <?php } ?>

                            <form method="post" autocomplete="off">
                                <label class="text-uppercase text-sm">Username or Email</label>
                                <input type="text" placeholder="Username or Email" name="username" class="form-control mb" required>

                                <label class="text-uppercase text-sm">Password</label>
                                <input type="password" placeholder="Password" name="password" class="form-control mb" required>

                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="remember" value="1"> Remember me
                                    </label>
                                </div>

                                <button class="btn btn-primary btn-block" type="submit">LOGIN</button>
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
