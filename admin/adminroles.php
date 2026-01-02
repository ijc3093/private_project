<?php
/**
 * admin/roleslist.php
 * Admin-only: show accounts (photo + username + email + role)
 */

require_once __DIR__ . '/includes/session_admin.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/controller.php';

// ✅ Admin identity (from session_admin.php login)
$adminLogin = $_SESSION['admin_login'];          // username/email
$adminRole  = (int)($_SESSION['userRole'] ?? 0); // 1 Admin, 2 Manager, 3 Gospel, 4 Staff

// Optional: if you want Admin-only blocks
$isAdmin = ($adminRole === 1);


$controller = new Controller();
$dbh = $controller->pdo();

$userRole = (int)($_SESSION['userRole'] ?? 0);
$isAdmin = ($userRole === 1);
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

// Pull all accounts from admin table and join role name
$sql = "
    SELECT 
        a.idadmin,
        a.username,
        a.email,
        a.image,
        a.status,
        a.created_at,
        r.name AS role_name
    FROM admin a
    LEFT JOIN role r ON r.idrole = a.role
    ORDER BY a.idadmin DESC
";
$stmt = $dbh->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
    <meta name="theme-color" content="#3e454c">
    <title>Role List & Accounts</title>

    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap-select.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #ddd;
        }
        .nowrap { white-space: nowrap; }
        .badge-role { font-size: 12px; padding: 6px 10px; }
    </style>
</head>

<body>
<?php include('includes/header.php'); ?>

<div class="ts-main-content">
<?php include('includes/leftbar.php'); ?>

<div class="content-wrapper">
    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <!-- <h2 class="page-title">List Roles & Accounts</h2> -->

                <div class="panel panel-default">
                    <div class="panel-heading">List Roles & Accounts - All Admin-side Accounts</div>
                    <div class="panel-body">

                        <table id="accountsTable" class="display table table-striped table-bordered table-hover" width="100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php
                            $cnt = 1;
                            foreach ($rows as $row):
                                // Image is stored in admin/images/ (your profile upload uses admin/images/)
                                $img = !empty($row->image) ? $row->image : 'default.jpg';
                                $roleName = $row->role_name ?: 'Unknown';
                                $statusText = ((int)$row->status === 1) ? 'Active' : 'Inactive';

                                // nice badge color
                                $badgeClass = 'label-default';
                                if (strcasecmp($roleName,'Admin')===0) $badgeClass = 'label-primary';
                                if (strcasecmp($roleName,'Manager')===0) $badgeClass = 'label-success';
                                if (strcasecmp($roleName,'Gospel')===0) $badgeClass = 'label-info';
                                if (strcasecmp($roleName,'Staff')===0) $badgeClass = 'label-warning';
                            ?>
                                <tr>
                                    <td class="nowrap"><?php echo $cnt; ?></td>
                                    <td class="nowrap">
                                        <img class="avatar" src="images/<?php echo htmlentities($img); ?>" alt="avatar">
                                    </td>
                                    <td><?php echo htmlentities($row->username); ?></td>
                                    <td><?php echo htmlentities($row->email); ?></td>
                                    <td class="nowrap">
                                        <span class="label <?php echo $badgeClass; ?> badge-role">
                                            <?php echo htmlentities($roleName); ?>
                                        </span>
                                    </td>
                                    <td class="nowrap"><?php echo htmlentities($statusText); ?></td>
                                    <td class="nowrap"><?php echo htmlentities($row->created_at); ?></td>
                                </tr>
                            <?php
                                $cnt++;
                            endforeach;
                            ?>
                            </tbody>
                        </table>

                        <p style="margin-top:10px;color:#666;">
                            This list comes from the <b>admin</b> table (Admin/Manager/Gospel/Staff).
                        </p>

                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap.min.js"></script>
<script src="js/main.js"></script>

<script>
$(document).ready(function(){
    $('#accountsTable').DataTable({
        responsive: false,
        autoWidth: false,
        scrollX: true,
        pageLength: 10
    });
});
</script>

</body>
</html>
