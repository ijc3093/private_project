<?php
/**
 * roleslist.php
 * Updates requested:
 * ✅ icon-only edit/delete with tooltips
 * ✅ Bootstrap confirm MODAL (no JS confirm())
 * ✅ delete locked to Admin role only
 * ✅ mobile-friendly Action dropdown
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
    // Only Admin can access roles management
    header('Location: dashboard.php');
    exit;
}

$msg = '';
$error = '';

// -----------------------------
// Delete role (POST from modal)
// -----------------------------
if (isset($_POST['delete_role'])) {
    if (!$isAdmin) {
        $error = "You are not allowed to delete roles.";
    } else {
        $roleId = (int)($_POST['delete_idrole'] ?? 0);

        try {
            if ($roleId <= 0) {
                $error = "Invalid role id.";
            } elseif (in_array($roleId, [1,2,3,4], true)) {
                $error = "You cannot delete default roles.";
            } else {
                // Block deletion if any admin uses this role
                $stmt = $dbh->prepare("SELECT COUNT(*) AS cnt FROM admin WHERE role = :rid");
                $stmt->execute([':rid' => $roleId]);
                $cnt = (int)($stmt->fetch()['cnt'] ?? 0);

                if ($cnt > 0) {
                    $error = "Cannot delete role: it is assigned to {$cnt} admin user(s).";
                } else {
                    $stmt = $dbh->prepare("DELETE FROM role WHERE idrole = :rid");
                    $stmt->execute([':rid' => $roleId]);
                    $msg = "Role deleted successfully.";
                }
            }
        } catch (PDOException $e) {
            $error = "Delete failed: " . $e->getMessage();
        }
    }
}

// -----------------------------
// Create role
// -----------------------------
if (isset($_POST['create_role'])) {
    $roleName = trim($_POST['role_name'] ?? '');

    if ($roleName === '') {
        $error = "Role name is required.";
    } else {
        try {
            $stmt = $dbh->prepare("INSERT INTO role (name) VALUES (:name)");
            $stmt->execute([':name' => $roleName]);
            $msg = "Role created successfully.";
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                $error = "Role already exists.";
            } else {
                $error = "Create failed: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------
// Update role name
// -----------------------------
if (isset($_POST['update_role'])) {
    $roleId   = (int)($_POST['idrole'] ?? 0);
    $roleName = trim($_POST['role_name'] ?? '');

    if ($roleId <= 0 || $roleName === '') {
        $error = "Role ID and name are required.";
    } else {
        try {
            if (in_array($roleId, [1,2,3,4], true)) {
                $error = "You cannot rename default roles.";
            } else {
                $stmt = $dbh->prepare("UPDATE role SET name = :name WHERE idrole = :id");
                $stmt->execute([':name' => $roleName, ':id' => $roleId]);
                $msg = "Role updated successfully.";
            }
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                $error = "Role name already exists.";
            } else {
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch roles
$stmt = $dbh->prepare("SELECT idrole, name FROM role ORDER BY idrole ASC");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_OBJ);
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
    <meta name="theme-color" content="#3e454c">
    <title>Roles List</title>

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
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
        .succWrap{
            padding: 10px;
            margin: 0 0 20px 0;
            background: #5cb85c;
            color:#fff;
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
        .actions { white-space: nowrap; }
        .icon-btn {
            display:inline-block;
            padding:6px 10px;
            border-radius:6px;
            border:1px solid #ddd;
            background:#fff;
        }
        .icon-btn:hover { background:#f7f7f7; }
        .icon-btn.danger { border-color:#f1b0b7; }
        .icon-btn.danger:hover { background:#fff1f2; }

        /* Mobile action dropdown: show on small screens, hide desktop icons */
        @media (max-width: 768px){
            .desktop-actions { display:none !important; }
            .mobile-actions { display:inline-block !important; }
        }
        @media (min-width: 769px){
            .desktop-actions { display:inline-block !important; }
            .mobile-actions { display:none !important; }
        }
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
                
                <!-- <h2 class="page-title">List & Add New Role</h2> -->
                <?php if ($error): ?>
                    <div class="errorWrap" id="msgshow"><?php echo htmlentities($error); ?></div>
                <?php elseif ($msg): ?>
                    <div class="succWrap" id="msgshow"><?php echo htmlentities($msg); ?></div>
                <?php endif; ?>

                <!-- Create Role -->
                <div class="panel panel-default">
                    
                    <div class="panel-heading">List & Add New Role - Create New Role</div>
                    <div class="panel-body">
                        <form method="post" class="form-inline">
                            <div class="form-group">
                                <label class="sr-only">Role Name</label>
                                <input type="text" name="role_name" class="form-control" placeholder="Role name" required>
                            </div>
                            <button type="submit" name="create_role" class="btn btn-primary">
                                <i class="fa fa-plus"></i> Add Role
                            </button>
                        </form>
                        <p style="margin-top:10px;color:#666;">
                            Default roles are locked: Admin, Manager, Gospel, Staff.
                        </p>
                    </div>
                </div>

                <!-- List Roles -->
                <div class="panel panel-default">
                    <div class="panel-heading">Roles List</div>
                    <div class="panel-body">

                        <table id="rolesTable" class="display table table-striped table-bordered table-hover" cellspacing="0" width="100%">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Role</th>
                                <th style="width:260px;">Edit</th>
                                <th style="width:160px;">Action</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php
                            $cnt = 1;
                            foreach ($roles as $r):
                                $rid = (int)$r->idrole;
                                $isDefault = in_array($rid, [1,2,3,4], true);
                            ?>
                                <tr>
                                    <td><?php echo (int)$cnt; ?></td>
                                    <td><?php echo htmlentities($r->name); ?></td>

                                    <td>
                                        <?php if ($isDefault): ?>
                                            <span class="text-muted">Default role (locked)</span>
                                        <?php else: ?>
                                            <!-- Edit form (icon-only save button) -->
                                            <form method="post" class="form-inline" style="margin:0;">
                                                <input type="hidden" name="idrole" value="<?php echo $rid; ?>">
                                                <input type="text" name="role_name" class="form-control" value="<?php echo htmlentities($r->name); ?>" required>
                                                <button type="submit" name="update_role"
                                                        class="icon-btn"
                                                        data-toggle="tooltip"
                                                        data-placement="top"
                                                        title="Save role name">
                                                    <i class="fa fa-save"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>

                                    <td class="actions">
                                        <?php if ($isDefault): ?>
                                            <span class="text-muted">Locked</span>
                                        <?php else: ?>

                                            <!-- Desktop: icon-only buttons -->
                                            <span class="desktop-actions">
                                                <!-- Delete icon opens modal -->
                                                <button type="button"
                                                        class="icon-btn danger"
                                                        data-toggle="tooltip"
                                                        data-placement="top"
                                                        title="Delete role"
                                                        data-roleid="<?php echo $rid; ?>"
                                                        data-rolename="<?php echo htmlentities($r->name); ?>"
                                                        onclick="openDeleteModal(this);">
                                                    <i class="fa fa-trash" style="color:red;"></i>
                                                </button>
                                            </span>

                                            <!-- Mobile: dropdown -->
                                            <div class="dropdown mobile-actions">
                                                <button class="btn btn-default dropdown-toggle" type="button" id="dropdown<?php echo $rid; ?>"
                                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    Actions <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="dropdown<?php echo $rid; ?>">
                                                    <li>
                                                        <a href="javascript:void(0)"
                                                           data-roleid="<?php echo $rid; ?>"
                                                           data-rolename="<?php echo htmlentities($r->name); ?>"
                                                           onclick="openDeleteModal(this);">
                                                            <i class="fa fa-trash text-danger"></i> Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>

                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                                $cnt++;
                            endforeach;
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

<!-- ✅ Confirm Delete Modal -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" role="dialog" aria-labelledby="deleteRoleModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <form method="post">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="deleteRoleModalLabel">Confirm Delete</h4>
        </div>

        <div class="modal-body">
          <p>Are you sure you want to delete this role?</p>
          <p><b id="deleteRoleName"></b></p>

          <input type="hidden" name="delete_idrole" id="deleteRoleId" value="">
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" name="delete_role" class="btn btn-danger">
            <i class="fa fa-trash"></i> Delete
          </button>
        </div>
      </form>

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

<script>
function openDeleteModal(el){
    var roleId = el.getAttribute('data-roleid');
    var roleName = el.getAttribute('data-rolename');

    document.getElementById('deleteRoleId').value = roleId;
    document.getElementById('deleteRoleName').textContent = roleName;

    $('#deleteRoleModal').modal('show');
}

$(document).ready(function () {
    // Tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Keep action column visible
    $('#rolesTable').DataTable({
        responsive: false,
        autoWidth: false,
        scrollX: true
    });

    setTimeout(function() {
        $('#msgshow').slideUp("slow");
    }, 3000);
});
</script>

</body>
</html>
