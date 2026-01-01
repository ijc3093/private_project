<?php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/admin/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$msg = '';
$error = '';

$userEmail = $_SESSION['user_login'] ?? '';
if ($userEmail === '') {
    clearUserSession();
    header("Location: index.php");
    exit;
}

// Helper: verify password against DB hash (password_hash OR md5)
function verifyPassword(string $plain, string $dbHash): bool {
    // password_hash?
    if (password_get_info($dbHash)['algo'] !== 0) {
        return password_verify($plain, $dbHash);
    }
    // legacy md5
    return md5($plain) === $dbHash;
}

if (isset($_POST['submit'])) {

    $currentRaw = (string)($_POST['password'] ?? '');
    $newRaw     = (string)($_POST['newpassword'] ?? '');
    $confirmRaw = (string)($_POST['confirmpassword'] ?? '');

    if ($newRaw !== $confirmRaw) {
        $error = "New Password and Confirm Password do not match!";
    } elseif (strlen($newRaw) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        // 1) Load current hash from DB
        $stmt = $dbh->prepare("SELECT id, password FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $userEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $error = "Account not found. Please login again.";
            clearUserSession();
            header("Location: index.php");
            exit;
        }

        $userId = (int)$row['id'];
        $dbHash = (string)$row['password'];

        // 2) Verify current password (supports password_hash + md5)
        if (!verifyPassword($currentRaw, $dbHash)) {
            $error = "Your current password is not valid.";
        } else {
            // 3) Update to secure password_hash (recommended)
            $newHash = password_hash($newRaw, PASSWORD_DEFAULT);

            $upd = $dbh->prepare("UPDATE users SET password = :pass WHERE id = :id");
            $upd->execute([
                ':pass' => $newHash,
                ':id'   => $userId
            ]);

            $msg = "Your Password Successfully Changed";
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
    <meta name="theme-color" content="#3e454c">

    <title>User Change Password</title>

    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap-social.css">
    <link rel="stylesheet" href="css/bootstrap-select.css">
    <link rel="stylesheet" href="css/fileinput.min.css">
    <link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
    <link rel="stylesheet" href="css/style.css">

    <script type="text/javascript">
    function valid(){
        if(document.chngpwd.newpassword.value != document.chngpwd.confirmpassword.value){
            alert("New Password and Confirm Password Field do not match  !!");
            document.chngpwd.confirmpassword.focus();
            return false;
        }
        return true;
    }
    </script>

    <style>
        .errorWrap {
            padding: 10px;
            margin: 0 0 20px 0;
            background: #dd3d36;
            color:#fff;
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
        .succWrap{
            padding: 10px;
            margin: 0 0 20px 0;
            background: #5cb85c;
            color:#fff;
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
    </style>
</head>

<body>
<?php include(__DIR__ . '/includes/header.php'); ?>
<div class="ts-main-content">
<?php include(__DIR__ . '/includes/leftbar.php'); ?>

<div class="content-wrapper">
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="page-title">Change Password</h2>

            <div class="row">
                <div class="col-md-10">
                    <div class="panel panel-default">
                        <div class="panel-heading">Form fields</div>
                        <div class="panel-body">

                            <form method="post" name="chngpwd" class="form-horizontal" onSubmit="return valid();">

                                <?php if($error){ ?>
                                    <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
                                <?php } else if($msg){ ?>
                                    <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
                                <?php } ?>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">Current Password</label>
                                    <div class="col-sm-8">
                                        <input type="password" class="form-control" name="password" id="password" required>
                                    </div>
                                </div>

                                <div class="hr-dashed"></div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">New Password</label>
                                    <div class="col-sm-8">
                                        <input type="password" class="form-control" name="newpassword" id="newpassword" required>
                                    </div>
                                </div>

                                <div class="hr-dashed"></div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">Confirm Password</label>
                                    <div class="col-sm-8">
                                        <input type="password" class="form-control" name="confirmpassword" id="confirmpassword" required>
                                    </div>
                                </div>

                                <div class="hr-dashed"></div>

                                <div class="form-group">
                                    <div class="col-sm-8 col-sm-offset-4">
                                        <button class="btn btn-primary" name="submit" type="submit">Save changes</button>
                                    </div>
                                </div>

                            </form>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
$(function(){
    setTimeout(function() {
        $('.succWrap').slideUp("slow");
    }, 3000);
});
</script>
</body>
</html>
