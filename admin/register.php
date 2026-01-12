<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!isAdmin()) {
    header("Location: dashboard.php");
    exit;
}

$controller = new Controller();

$error = '';
$msg = '';
$createdTempPassword = '';
$createdFriendCode = '';

if (isset($_POST['submit'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = (int)($_POST['role'] ?? 0);
    $status   = (int)($_POST['status'] ?? 1);

    $result = $controller->createInternalAccountWithInvite([
        'fullname'=>$fullname,
        'username'=>$username,
        'email'=>$email,
        'role'=>$role,
        'status'=>$status
    ]);

    if (!$result['ok']) {
        $error = $result['error'] ?? 'Failed';
    } else {
        $msg = "Account created successfully and invite email sent.";
        $createdFriendCode = $result['friend_code'];
        $createdTempPassword = $result['temp_password'];
    }
}

$dbh = $controller->pdo();
$roles = $dbh->query("SELECT idrole, name FROM role WHERE status = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// $roles = $dbh->query("
//   SELECT idrole, name
//   FROM role
//   WHERE status = 1
//   ORDER BY name ASC
// ")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Internal Account</title>

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .box{background:#fff;border:1px solid #ddd;border-radius:10px;padding:18px;max-width:820px;margin:auto;}
    .errorWrap{padding:10px;background:#dd3d36;color:#fff;margin:0 0 15px;}
    .succWrap{padding:10px;background:#5cb85c;color:#fff;margin:0 0 15px;}
    .codebox{background:#f7f7f7;border:1px dashed #ccc;border-radius:8px;padding:12px;margin-top:12px;}
    .codebox b{display:inline-block;min-width:160px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">Create Internal Account (Admin/Manager/Staff)</h2>

  <?php if ($error): ?><div class="errorWrap"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="succWrap"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <?php if ($msg && $createdTempPassword && $createdFriendCode): ?>
    <div class="box" style="margin-bottom:15px;">
      <h4 style="margin-top:0;">Share these credentials with the user</h4>
      <div class="codebox">
        <div><b>Friend Code:</b> <?php echo htmlentities($createdFriendCode); ?></div>
        <div><b>Temporary Password:</b> <?php echo htmlentities($createdTempPassword); ?></div>
        <div style="margin-top:8px;color:#666;">
          They can login and will be forced to change password.
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="box">
    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="fullname" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Username *</label>
        <input type="text" name="username" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Role *</label>
        <select name="role" class="form-control" required>
          <?php foreach($roles as $r): ?>
            <option value="<?php echo (int)$r['idrole']; ?>">
              <?php echo htmlentities($r['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <option value="1" selected>Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>

      <button class="btn btn-primary" type="submit" name="submit">
        <i class="fa fa-user-plus"></i> Create Account
      </button>

      <a class="btn btn-default" href="adminroles.php" style="margin-left:8px;">Back to Roles List</a>
    </form>
  </div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
