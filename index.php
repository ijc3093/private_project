<?php
require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$error = '';

if (isset($_POST['login'])) {
    $email    = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = "Please enter email and password.";
    } else {
        $db = new Controller();
        $user = $db->userLogin($email, $password);

        if ($user) {
            setUserSession($user);
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid Details Or Account Not Confirmed";
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

    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap-social.css">
    <link rel="stylesheet" href="css/bootstrap-select.css">
    <link rel="stylesheet" href="css/fileinput.min.css">
    <link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
<div class="login-page bk-img">
    <div class="form-content">
        <div class="container">
            <div class="row">
                <div class="col-md-6 col-md-offset-3">
                    <h1 class="text-center text-bold mt-4x">Login</h1>

                    <div class="well row pt-2x pb-3x bk-light">
                        <div class="col-md-8 col-md-offset-2">

                            <?php if (!empty($error)) { ?>
                                <div class="alert alert-danger">
                                    <?php echo htmlentities($error); ?>
                                </div>
                            <?php } ?>

                            <form method="post" autocomplete="off">
                                <label class="text-uppercase text-sm">Your Email</label>
                                <input type="text" placeholder="Email" name="username" class="form-control mb" required>

                                <label class="text-uppercase text-sm">Password</label>
                                <input type="password" placeholder="Password" name="password" class="form-control mb" required>

                                <button class="btn btn-primary btn-block" name="login" type="submit">LOGIN</button>
                            </form>

                            <br>
                            <p>Don't Have an Account? <a href="register.php">Signup</a></p>

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
