<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

// ✅ Admin identity (from session_admin.php login)
$adminLogin = $_SESSION['admin_login'];          // username/email
$adminRole  = (int)($_SESSION['userRole'] ?? 0); // 1 Admin, 2 Manager, 3 Gospel, 4 Staff

// Optional: if you want Admin-only blocks
$isAdmin = ($adminRole === 1);

// Counts
$userCount = 0;
$feedbackCount = 0;
$notiCount = 0;
$deletedCount = 0;

try {
    if ($isAdmin) {
        $stmt = $dbh->query("SELECT COUNT(*) FROM users");
        $userCount = (int)$stmt->fetchColumn();

        $stmt = $dbh->query("SELECT COUNT(*) FROM deleteduser");
        $deletedCount = (int)$stmt->fetchColumn();
    }

    $stmt = $dbh->prepare("SELECT COUNT(*) FROM feedback WHERE receiver = :r");
    $stmt->execute([':r' => 'Admin']);
    $feedbackCount = (int)$stmt->fetchColumn();

    $stmt = $dbh->prepare("SELECT COUNT(*) FROM notification WHERE notireceiver = :r");
    $stmt->execute([':r' => 'Admin']);
    $notiCount = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    $error = "DB Error: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#3e454c">
    <title>Admin Dashboard</title>

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

<?php include('includes/header.php'); ?>
<div class="ts-main-content">
<?php include('includes/leftbar.php'); ?>

<div class="content-wrapper">
<div class="container-fluid">

    <h2 class="page-title">Dashboard</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlentities($error); ?></div>
    <?php endif; ?>

    <div class="row">

        <?php if ($isAdmin): ?>
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-body bk-primary text-light text-center">
                    <div class="stat-panel-number h1"><?php echo $userCount; ?></div>
                    <div class="stat-panel-title text-uppercase">Total Users</div>
                </div>
                <a href="userlist.php" class="block-anchor panel-footer text-center">
                    Full Detail <i class="fa fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-body bk-success text-light text-center">
                    <div class="stat-panel-number h1"><?php echo $feedbackCount; ?></div>
                    <div class="stat-panel-title text-uppercase">Feedback</div>
                </div>
                <a href="feedback.php" class="block-anchor panel-footer text-center">
                    Full Detail <i class="fa fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-body bk-danger text-light text-center">
                    <div class="stat-panel-number h1"><?php echo $notiCount; ?></div>
                    <div class="stat-panel-title text-uppercase">Notifications</div>
                </div>
                <a href="notification.php" class="block-anchor panel-footer text-center">
                    Full Detail <i class="fa fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($isAdmin): ?>
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-body bk-info text-light text-center">
                    <div class="stat-panel-number h1"><?php echo $deletedCount; ?></div>
                    <div class="stat-panel-title text-uppercase">Deleted Users</div>
                </div>
                <a href="deleteduser.php" class="block-anchor panel-footer text-center">
                    Full Detail <i class="fa fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
