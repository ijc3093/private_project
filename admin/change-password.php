<?php
// /Business_only3/admin/change-password.php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$error = '';
$msg   = '';

$username = $_SESSION['admin_login'] ?? '';
$adminId  = (int)($_SESSION['admin_id'] ?? 0);

// forced mode from URL OR from DB flag (stronger)
$forceUrl = isset($_GET['force']) && $_GET['force'] == '1';

if ($username === '' || $adminId <= 0) {
    clearAdminSession();
    header("Location: index.php");
    exit;
}

// Pull current hash + force flag from DB (so user cannot bypass ?force=1)
$stAcc = $dbh->prepare("
    SELECT idadmin, username, password, force_password_change, status
    FROM admin
    WHERE idadmin = :id
    LIMIT 1
");
$stAcc->execute([':id' => $adminId]);
$acc = $stAcc->fetch(PDO::FETCH_ASSOC);

if (!$acc) {
    clearAdminSession();
    header("Location: index.php");
    exit;
}

if ((int)$acc['status'] !== 1) {
    clearAdminSession();
    header("Location: index.php");
    exit;
}

$forceDb = ((int)($acc['force_password_change'] ?? 0) === 1);
$force   = $forceUrl || $forceDb;

// If forced, we still show the page, but we will require current password OR allow skipping it.
// Recommendation: allow skipping current password ONLY when forced, because admin used a temp password anyway.
// If you want to ALWAYS require current password, set this to true.
$requireCurrentWhenForced = false;

if (isset($_POST['submit'])) {
    $current = (string)($_POST['password'] ?? '');
    $new     = trim((string)($_POST['newpassword'] ?? ''));
    $confirm = trim((string)($_POST['confirmpassword'] ?? ''));

    // Basic validations
    if ($new === '' || $confirm === '') {
        $error = "New password fields are required.";
    } elseif ($new !== $confirm) {
        $error = "New Password and Confirm Password do not match.";
    } elseif (strlen($new) < 8) {
        $error = "New password must be at least 8 characters.";
    } else {

        $dbHash = (string)$acc['password'];

        // Decide if we must verify current password
        $mustCheckCurrent = (!$force) || $requireCurrentWhenForced;

        if ($mustCheckCurrent) {
            if ($current === '') {
                $error = "Current password is required.";
            } elseif (!password_verify($current, $dbHash)) {
                $error = "Your current password is not valid.";
            }
        }

        // Also prevent setting the same password again (best effort)
        if ($error === '' && $current !== '' && hash_equals($current, $new)) {
            $error = "New password must be different from current password.";
        }

        // If forced and we did NOT verify current, still prevent same password by checking verify()
        if ($error === '' && $force && !$mustCheckCurrent) {
            if (password_verify($new, $dbHash)) {
                $error = "New password must be different from current password.";
            }
        }

        if ($error === '') {
            $newHash = password_hash($new, PASSWORD_DEFAULT);

            // Optional column: last_password_change_at
            // If you don't have it, this query will fail.
            // So we detect if the column exists and update accordingly.
            $hasCol = false;
            try {
                $chk = $dbh->query("SHOW COLUMNS FROM admin LIKE 'last_password_change_at'");
                $hasCol = (bool)$chk->fetch();
            } catch (Throwable $e) {
                $hasCol = false;
            }

            if ($hasCol) {
                $up = $dbh->prepare("
                    UPDATE admin
                    SET password = :p,
                        force_password_change = 0,
                        last_password_change_at = NOW()
                    WHERE idadmin = :id
                    LIMIT 1
                ");
            } else {
                $up = $dbh->prepare("
                    UPDATE admin
                    SET password = :p,
                        force_password_change = 0
                    WHERE idadmin = :id
                    LIMIT 1
                ");
            }

            $up->execute([
                ':p'  => $newHash,
                ':id' => (int)$acc['idadmin']
            ]);

            $msg = "Your Password Successfully Changed";

            // After forced change, go dashboard
            if ($force) {
                header("Location: dashboard.php");
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Change Password</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .errorWrap{padding:10px;margin:0 0 20px 0;background:#dd3d36;color:#fff;}
    .succWrap{padding:10px;margin:0 0 20px 0;background:#5cb85c;color:#fff;}
  </style>
</head>
<body>

<?php include('includes/header.php'); ?>
<div class="ts-main-content">
<?php include('includes/leftbar.php'); ?>
  <div class="content-wrapper">
    <div class="container-fluid">
      <h2 class="page-title">Change Password</h2>

      <?php if ($force): ?>
        <div class="alert alert-warning">
          You must change your password before continuing.
        </div>
      <?php endif; ?>

      <?php if ($error): ?><div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div><?php endif; ?>
      <?php if ($msg): ?><div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div><?php endif; ?>

      <div class="panel panel-default">
        <div class="panel-heading">Form fields</div>
        <div class="panel-body">
          <form method="post" class="form-horizontal" autocomplete="off">

            <?php if (!$force || $requireCurrentWhenForced): ?>
              <div class="form-group">
                <label class="col-sm-4 control-label">Current Password</label>
                <div class="col-sm-8">
                  <input type="password" class="form-control" name="password" required>
                </div>
              </div>
              <div class="hr-dashed"></div>
            <?php endif; ?>

            <div class="form-group">
              <label class="col-sm-4 control-label">New Password</label>
              <div class="col-sm-8">
                <input type="password" class="form-control" name="newpassword" required>
                <small class="text-muted">Minimum 8 characters.</small>
              </div>
            </div>

            <div class="hr-dashed"></div>

            <div class="form-group">
              <label class="col-sm-4 control-label">Confirm Password</label>
              <div class="col-sm-8">
                <input type="password" class="form-control" name="confirmpassword" required>
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

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
