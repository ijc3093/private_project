<?php
/**
 * admin/publicusers.php
 * Admin-only: show PUBLIC users (photo + name + email + status + created time)
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

/**
 * Your new SQL uses:
 * users.created_at (timestamp)
 * Your old code used:
 * users.time / users.date
 * We'll detect which exists and use it.
 */
$colStmt = $dbh->prepare("SHOW COLUMNS FROM users");
$colStmt->execute();
$cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_map(fn($c) => $c['Field'], $cols);

$hasCreatedAt = in_array('created_at', $colNames, true);
$hasTime      = in_array('time', $colNames, true);
$hasDate      = in_array('date', $colNames, true);
$hasRole      = in_array('role', $colNames, true); // some older schemas had role in users

$createdExpr = "NULL AS created_display";
if ($hasCreatedAt) {
    $createdExpr = "created_at AS created_display";
} elseif ($hasDate && $hasTime) {
    $createdExpr = "CONCAT(date, ' ', time) AS created_display";
} elseif ($hasTime) {
    $createdExpr = "`time` AS created_display";
}

$roleJoinSql = "";
$roleSelect  = "'Public' AS role_name";
if ($hasRole) {
    // If your users table has role int, map using role table
    $roleSelect = "COALESCE(r.name,'Staff') AS role_name";
    $roleJoinSql = "LEFT JOIN role r ON r.idrole = u.role";
}

$sql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.gender,
        u.mobile,
        u.designation,
        u.image,
        u.status,
        $createdExpr,
        $roleSelect
    FROM users u
    $roleJoinSql
    ORDER BY u.id DESC
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
    <title>Public Users</title>

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
                <h2 class="page-title">Public Users</h2>

                <div class="panel panel-default">
                    <div class="panel-heading">All Public Users (users table)</div>
                    <div class="panel-body">

                        <table id="publicUsersTable" class="display table table-striped table-bordered table-hover" width="100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <?php if ($hasRole): ?>
                                        <th>Role</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                    <th>Gender</th>
                                    <th>Phone</th>
                                    <th>Designation</th>
                                    <th>Created</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php
                            $cnt = 1;
                            foreach ($rows as $row):
                                // Public users usually upload to /Business_only/images/
                                // But you are in /admin/, so path is ../images/
                                $img = !empty($row->image) ? $row->image : 'default.jpg';

                                $statusText = ((int)$row->status === 1) ? 'Active' : 'Inactive';
                                $created = $row->created_display ?? '';

                                $roleName = $row->role_name ?? 'Public';
                                $badgeClass = 'label-default';
                                if (strcasecmp($roleName,'Admin')===0) $badgeClass = 'label-primary';
                                if (strcasecmp($roleName,'Manager')===0) $badgeClass = 'label-success';
                                if (strcasecmp($roleName,'Gospel')===0) $badgeClass = 'label-info';
                                if (strcasecmp($roleName,'Staff')===0) $badgeClass = 'label-warning';
                            ?>
                                <tr>
                                    <td class="nowrap"><?php echo $cnt; ?></td>
                                    <td class="nowrap">
                                        <img class="avatar" src="../images/<?php echo htmlentities($img); ?>" alt="avatar">
                                    </td>
                                    <td><?php echo htmlentities($row->name); ?></td>
                                    <td><?php echo htmlentities($row->email); ?></td>

                                    <?php if ($hasRole): ?>
                                        <td class="nowrap">
                                            <span class="label <?php echo $badgeClass; ?> badge-role">
                                                <?php echo htmlentities($roleName); ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>

                                    <td class="nowrap"><?php echo htmlentities($statusText); ?></td>
                                    <td class="nowrap"><?php echo htmlentities($row->gender); ?></td>
                                    <td class="nowrap"><?php echo htmlentities($row->mobile); ?></td>
                                    <td><?php echo htmlentities($row->designation); ?></td>
                                    <td class="nowrap"><?php echo htmlentities($created); ?></td>
                                </tr>
                            <?php
                                $cnt++;
                            endforeach;
                            ?>
                            </tbody>
                        </table>

                        <p style="margin-top:10px;color:#666;">
                            Images load from <b>../images/</b> because this file is inside <b>/admin/</b>.
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
    $('#publicUsersTable').DataTable({
        responsive: false,
        autoWidth: false,
        scrollX: true,
        pageLength: 10
    });
});
</script>

</body>
</html>
