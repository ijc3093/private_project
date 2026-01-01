<?php
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

$msg = '';
$error = '';

// ✅ format created_at
function fmt_dt($dt) {
    if (!$dt) return 'N/A';
    return date('Y-m-d h:i A', strtotime($dt));
}

// ✅ helper: is delete allowed (you can tighten this later)
$canDelete = true;

// -----------------------------
// DELETE ONE USER
// -----------------------------
if (isset($_GET['del']) && isset($_GET['name'])) {
    $id   = (int)$_GET['del'];
    $name = trim($_GET['name']); // email

    if (!$canDelete) {
        $error = "Delete disabled.";
    } else {
        try {
            $dbh->beginTransaction();

            // delete user
            $del = $dbh->prepare("DELETE FROM users WHERE id = :id");
            $del->execute([':id' => $id]);

            if ($del->rowCount() > 0) {
                // log deleted user
                $ins = $dbh->prepare("INSERT INTO deleteduser (email) VALUES (:email)");
                $ins->execute([':email' => $name]);

                $dbh->commit();
                $msg = "User deleted successfully.";
            } else {
                $dbh->rollBack();
                $error = "Delete failed (user not found).";
            }
        } catch (PDOException $e) {
            if ($dbh->inTransaction()) $dbh->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// -----------------------------
// DELETE ALL USERS (Danger)
// -----------------------------
if (isset($_POST['delete_all'])) {

    if (!$canDelete) {
        $error = "Delete disabled.";
    } else {
        try {
            $dbh->beginTransaction();

            // fetch all users emails before delete (so we can log)
            $all = $dbh->query("SELECT email FROM users")->fetchAll(PDO::FETCH_COLUMN);

            // delete all
            $delAll = $dbh->prepare("DELETE FROM users");
            $delAll->execute();

            // log all deleted emails
            if (!empty($all)) {
                $ins = $dbh->prepare("INSERT INTO deleteduser (email) VALUES (:email)");
                foreach ($all as $em) {
                    $ins->execute([':email' => $em]);
                }
            }

            $dbh->commit();
            $msg = "All users deleted successfully.";
        } catch (PDOException $e) {
            if ($dbh->inTransaction()) $dbh->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// -----------------------------
// CONFIRM / UNCONFIRM
// -----------------------------
if (isset($_GET['unconfirm'])) {
    $aeid = (int)$_GET['unconfirm'];
    try {
        $sql = "UPDATE users SET status = :status WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->execute([':status' => 1, ':id' => $aeid]);
        $msg = "Account confirmed.";
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

if (isset($_GET['confirm'])) {
    $aeid = (int)$_GET['confirm'];
    try {
        $sql = "UPDATE users SET status = :status WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->execute([':status' => 0, ':id' => $aeid]);
        $msg = "Account un-confirmed.";
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// -----------------------------
// FETCH USERS
// (uses created_at instead of date/time columns)
// -----------------------------
try {
    $sql = "SELECT id, name, email, gender, mobile, designation, image, status, created_at
            FROM users
            ORDER BY created_at DESC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $results = [];
    $error = "Database error: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">

    <title>Users List</title>

    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap-social.css">
    <link rel="stylesheet" href="css/bootstrap-select.css">
    <link rel="stylesheet" href="css/fileinput.min.css">
    <link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        .errorWrap { padding:10px;margin:0 0 20px 0;background:#dd3d36;color:#fff;box-shadow:0 1px 1px 0 rgba(0,0,0,.1); }
        .succWrap  { padding:10px;margin:0 0 20px 0;background:#5cb85c;color:#fff;box-shadow:0 1px 1px 0 rgba(0,0,0,.1); }
        .actions-bar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;}
        .icon-action a{font-size:18px;margin-right:10px;}
        .icon-action a:hover{text-decoration:none;opacity:.8;}
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
                <h2 class="page-title">List Users</h2>

                <?php if ($error): ?>
                    <div class="errorWrap" id="msgshow"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
                <?php elseif ($msg): ?>
                    <div class="succWrap" id="msgshow"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
                <?php endif; ?>

                <div class="panel panel-default">
                    <div class="panel-heading">Users</div>

                    <div class="panel-body">

                        <div class="actions-bar">
                            <div>
                                <strong>Total Users:</strong> <?php echo count($results); ?>
                            </div>

                            <form method="post" style="margin:0;">
                                <button
                                    type="submit"
                                    name="delete_all"
                                    class="btn btn-danger btn-sm"
                                    <?php echo (count($results) === 0 || !$canDelete) ? 'disabled' : ''; ?>
                                    onclick="return confirm('WARNING: Delete ALL users? This cannot be undone!');"
                                    title="<?php echo (!$canDelete) ? 'Delete disabled' : 'Delete all users'; ?>"
                                >
                                    <i class="fa fa-trash"></i> Delete All
                                </button>
                            </form>
                        </div>

                        <table id="usersTable" class="display table table-striped table-bordered table-hover" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Gender</th>
                                    <th>Phone</th>
                                    <th>Designation</th>
                                    <th>Created At</th>
                                    <th>Account</th>
                                    <th style="width:120px;">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php
                            $cnt = 1;
                            foreach ($results as $r):
                            ?>
                                <tr>
                                    <td><?php echo $cnt++; ?></td>

                                    <td>
                                        <img
                                            src="../images/<?php echo htmlentities($r->image);?>"
                                            style="width:50px; height:50px; object-fit:cover; border-radius:50%;"
                                            onerror="this.src='../images/default.jpg';"
                                        />
                                    </td>

                                    <td><?php echo htmlentities($r->name); ?></td>
                                    <td><?php echo htmlentities($r->email); ?></td>
                                    <td><?php echo htmlentities($r->gender); ?></td>
                                    <td><?php echo htmlentities($r->mobile); ?></td>
                                    <td><?php echo htmlentities($r->designation); ?></td>

                                    <td><?php echo htmlentities(fmt_dt($r->created_at)); ?></td>

                                    <td>
                                        <?php if ((int)$r->status === 1): ?>
                                            <a href="userlist.php?confirm=<?php echo (int)$r->id; ?>"
                                               onclick="return confirm('Un-confirm this account?');">
                                                Confirmed <i class="fa fa-check-circle"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="userlist.php?unconfirm=<?php echo (int)$r->id; ?>"
                                               onclick="return confirm('Confirm this account?');">
                                                Un-Confirmed <i class="fa fa-times-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>

                                    <td class="icon-action">
                                        <a href="edit-user.php?edit=<?php echo (int)$r->id; ?>"
                                           title="Edit"
                                           onclick="return confirm('Edit this user?');">
                                            <i class="fa fa-pencil"></i>
                                        </a>

                                        <a href="userlist.php?del=<?php echo (int)$r->id; ?>&name=<?php echo urlencode($r->email); ?>"
                                           title="<?php echo $canDelete ? 'Delete' : 'Delete disabled'; ?>"
                                           <?php echo $canDelete ? '' : 'onclick="return false;" style="pointer-events:none;opacity:.4;"'; ?>
                                           onclick="return confirm('Delete this user?');">
                                            <i class="fa fa-trash" style="color:red;"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if (count($results) === 0): ?>
                            <div class="alert alert-info" style="margin-top:12px;">
                                No users found.
                            </div>
                        <?php endif; ?>

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

<script>
$(document).ready(function () {

    // ✅ DataTables gives:
    // - Showing X to Y of Z entries
    // - Prev / Next pagination
    // - Search box
    $('#usersTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
    });

    setTimeout(function() {
        $('#msgshow').slideUp("slow");
    }, 3000);
});
</script>
</body>
</html>
