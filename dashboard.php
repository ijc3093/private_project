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

        <!-- <div class="row">
            <div class="col-md-12">
                <h2 class="page-title">Dashboard</h2>
            </div>
        </div> -->
        <div class="panel panel-default">

            <div class="panel-heading">Dashboard</div>

            
        </div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
