<?php
require_once __DIR__ . '/includes/session_admin.php';
error_reporting(0);

include('./controller.php');

// ✅ FIX: get PDO connection ($dbh) from controller.php
$controller = new Controller();
$dbh = $controller->__construct();

if(strlen($_SESSION['admin_login'])==0){
    header('location:index.php');
}else{

    if(isset($_GET['del'])){
        $id=$_GET['del'];
        $sql = "delete from users WHERE id=:id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $id, PDO::PARAM_STR);
        $query->execute();
        $msg="Data Deleted successfully";
    }

    if(isset($_REQUEST['unconfirm'])){
        $aeid=intval($_GET['unconfirm']);
        $memstatus=1;

        // ✅ FIX: SET not SETS
        $sql = "UPDATE users SET status=:status WHERE id=:aeid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':status', $memstatus, PDO::PARAM_STR);
        $query->bindParam(':aeid', $aeid, PDO::PARAM_STR);
        $query->execute();
        $msg="Changes successfully";
    }

    if(isset($_REQUEST['confirm'])){
        $aeid=intval($_GET['confirm']);
        $memstatus=0;

        // ✅ FIX: SET not SETS
        $sql = "UPDATE users SET status=:status WHERE id=:aeid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':status', $memstatus, PDO::PARAM_STR);
        $query->bindParam(':aeid', $aeid, PDO::PARAM_STR);
        $query->execute();
        $msg="Changes successfully";
    }

?>
<!doctype html>
<html lang="en" class="no-js">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
	<meta name="description" content="">
	<meta name="author" content="">
	<meta name="theme-color" content="#3e454c">

	<title>New Compose</title>

	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
	<link rel="stylesheet" href="css/bootstrap-social.css">
	<link rel="stylesheet" href="css/bootstrap-select.css">
	<link rel="stylesheet" href="css/fileinput.min.css">
	<link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
	<link rel="stylesheet" href="css/style.css">

    <style>
        .errorWrap {
            padding: 10px;
            margin: 0 0 20px 0;
            background: #dd3d36;
            color:#fff;
            -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
        .succWrap{
            padding: 10px;
            margin: 0 0 20px 0;
            background: #5cb85c;
            color:#fff;
            -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
	</style>
</head>

<body>
<?php include('includes/header.php');?>
<div class="ts-main-content">
<?php include('includes/leftbar.php');?>
<div class="content-wrapper">
<div class="container-fluid">

<div class="row">
<div class="col-md-12">

<h2 class="page-title">New Compose</h2>

<div class="panel panel-default">
	<div class="panel-body">

        <?php if($error){?>
            <div class="errorWrap" id="msgshow"><?php echo htmlentities($error); ?></div>
        <?php } else if($msg){?>
            <div class="succWrap" id="msgshow"><?php echo htmlentities($msg); ?></div>
        <?php } ?>

		<table id="zctb" class="display table table-striped table-bordered table-hover" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>From: User Email</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php
                $receiver = 'Admin';
                $sql = "SELECT * FROM feedback WHERE receiver = (:receiver)";
                $query = $dbh->prepare($sql);
                $query->bindParam(':receiver', $receiver, PDO::PARAM_STR);
                $query->execute();
                $results = $query->fetchAll(PDO::FETCH_OBJ);
                $cnt = 1;

                if($query->rowCount() > 0){
                    foreach($results as $result){
            ?>
                <tr>
                    <td><?php echo htmlentities($cnt);?></td>
                    <td><?php echo htmlentities($result->sender);?></td>
                    <td>
                        <a href="sendreply.php?reply=<?php echo urlencode($result->sender);?>">Send a new message</a>
                    </td>
                </tr>
            <?php
                        $cnt++;
                    }
                }
            ?>
            </tbody>
        </table>

	</div>
</div>

</div>
</div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap-select.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap.min.js"></script>
<script src="js/Chart.min.js"></script>
<script src="js/fileinput.js"></script>
<script src="js/chartData.js"></script>
<script src="js/main.js"></script>
<script type="text/javascript">
$(document).ready(function () {
    setTimeout(function() {
        $('.succWrap').slideUp("slow");
    }, 3000);
});
</script>

</body>
</html>
</body>
<?php } ?>
