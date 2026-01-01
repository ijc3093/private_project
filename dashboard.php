<?php
require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

requireUserLogin();

$controller = new Controller();
$dbh = $controller->pdo();

$loggedEmail = $_SESSION['user_login'];
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
    <meta name="theme-color" content="#3e454c">

    <title>Dashboard</title>

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

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <h2 class="page-title">Dashboard</h2>
            </div>
        </div>

        <div class="row">

            <!-- Feedback -->
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body bk-success text-light">
                        <div class="stat-panel text-center">
                            <?php
                            $sql1 = "SELECT id FROM feedback WHERE receiver = :receiver";
                            $query1 = $dbh->prepare($sql1);
                            $query1->execute([':receiver' => $loggedEmail]);
                            $feedbackCount = $query1->rowCount();
                            ?>
                            <div class="stat-panel-number h1">
                                <?php echo (int)$feedbackCount; ?>
                            </div>
                            <div class="stat-panel-title text-uppercase">Messages</div>
                        </div>
                    </div>
                    <a href="messages.php" class="block-anchor panel-footer text-center">
                        Full Detail <i class="fa fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Notification -->
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body bk-danger text-light">
                        <div class="stat-panel text-center">
                            <?php
                            $sql2 = "SELECT id FROM notification WHERE notireceiver = :receiver";
                            $query2 = $dbh->prepare($sql2);
                            $query2->execute([':receiver' => $loggedEmail]);
                            $notiCount = $query2->rowCount();
                            ?>
                            <div class="stat-panel-number h1">
                                <?php echo (int)$notiCount; ?>
                            </div>
                            <div class="stat-panel-title text-uppercase">Notifications</div>
                        </div>
                    </div>
                    <a href="notification.php" class="block-anchor panel-footer text-center">
                        Full Detail <i class="fa fa-arrow-right"></i>
                    </a>
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
