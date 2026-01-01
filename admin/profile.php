<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$msg = '';
$error = '';

// ✅ session key from your admin system
$loginValue = $_SESSION['admin_login'] ?? '';
if ($loginValue === '') {
    header("Location: index.php");
    exit;
}

// -----------------------------
// Fetch current admin (username OR email)
// -----------------------------
function fetchAdmin(PDO $dbh, string $loginValue)
{
    $stmt = $dbh->prepare("
        SELECT idadmin, username, email, mobile, designation, image, role, image_type
        FROM admin
        WHERE username = :u1 OR email = :u2
        LIMIT 1
    ");
    $stmt->execute([
        ':u1' => $loginValue,
        ':u2' => $loginValue
    ]);
    return $stmt->fetch(PDO::FETCH_OBJ);
}

$result = fetchAdmin($dbh, $loginValue);

if (!$result) {
    die("Admin user not found.");
}

// -----------------------------
// UPDATE PROFILE
// -----------------------------
if (isset($_POST['submit'])) {

    $idadmin     = (int)($_POST['idadmin'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $mobile      = trim($_POST['mobile'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    if ($idadmin <= 0 || $idadmin !== (int)$result->idadmin) {
        $error = "Invalid admin id.";
    } elseif ($name === '' || $email === '' || $mobile === '' || $designation === '') {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    }

    // ---------------------------------------
    // ✅ DB AVATAR UPLOAD (BLOB in phpMyAdmin)
    // ---------------------------------------
    if ($error === '' && !empty($_FILES['image']['name'])) {
    $allowedTypes = ['image/jpeg','image/png','image/jpg'];
    $mime = mime_content_type($_FILES['image']['tmp_name']);

        if (!in_array($mime, $allowedTypes, true)) {
            $error = "Image must be JPG or PNG.";
        } else {
            $blob = file_get_contents($_FILES['image']['tmp_name']);
            $type = $mime;

            $updImg = $dbh->prepare("
                UPDATE admin
                SET image_blob = :b, image_type = :t
                WHERE idadmin = :id
            ");
            $updImg->execute([
                ':b'  => $blob,
                ':t'  => $type,
                ':id' => (int)$idadmin
            ]);
        }
    }


    // ---------------------------------------
    // ✅ Update admin fields
    // ---------------------------------------
    if ($error === '') {
        try {
            $sql = "UPDATE admin
                    SET username = :name,
                        email = :email,
                        mobile = :mobile,
                        designation = :designation
                    WHERE idadmin = :idadmin
                    LIMIT 1";

            $upd = $dbh->prepare($sql);
            $upd->execute([
                ':name' => $name,
                ':email' => $email,
                ':mobile' => $mobile,
                ':designation' => $designation,
                ':idadmin' => $idadmin
            ]);

            // ✅ keep login session consistent
            // choose username as login (matches your admin system)
            $_SESSION['admin_login'] = $name;

            header("Location: profile.php?updated=1");
            exit;

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// success message after redirect
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $msg = "Profile updated successfully.";
}

// Re-fetch updated record
$result = fetchAdmin($dbh, $_SESSION['admin_login']);
if (!$result) {
    die("Admin user not found after update.");
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Profile</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-social.css">
  <link rel="stylesheet" href="css/bootstrap-select.css">
  <link rel="stylesheet" href="css/fileinput.min.css">
  <link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .errorWrap { padding:10px; margin:0 0 20px 0; background:#dd3d36; color:#fff; }
    .succWrap  { padding:10px; margin:0 0 20px 0; background:#5cb85c; color:#fff; }
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

<div class="panel panel-default">
  <div class="panel-heading"><?php echo htmlentities($_SESSION['admin_login']); ?></div>

  <?php if (!empty($error)): ?>
    <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
  <?php elseif (!empty($msg)): ?>
    <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
  <?php endif; ?>

  <div class="panel-body">
    <form method="post" class="form-horizontal" enctype="multipart/form-data">

      <div class="form-group">
        <div class="col-sm-4"></div>
        <div class="col-sm-4 text-center">

          <!-- ✅ SHOW AVATAR FROM DB -->
          <img src="avatar.php?ts=<?php echo time(); ?>"
     style="width:200px;height:200px;border-radius:50%;margin:10px;object-fit:cover;"
     alt="Profile">


          <input type="file" name="image" class="form-control">
          <small class="text-muted">Avatar stored in phpMyAdmin (MySQL). Max 2MB.</small>
        </div>
        <div class="col-sm-4"></div>
      </div>

      <div class="form-group">
        <label class="col-sm-2 control-label">Name *</label>
        <div class="col-sm-4">
          <input type="text" name="name" class="form-control" required
                 value="<?php echo htmlentities($result->username); ?>">
        </div>

        <label class="col-sm-2 control-label">Email *</label>
        <div class="col-sm-4">
          <input type="email" name="email" class="form-control" required
                 value="<?php echo htmlentities($result->email); ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-2 control-label">Mobile *</label>
        <div class="col-sm-4">
          <input type="text" name="mobile" class="form-control" required
                 value="<?php echo htmlentities($result->mobile); ?>">
        </div>

        <label class="col-sm-2 control-label">Designation *</label>
        <div class="col-sm-4">
          <input type="text" name="designation" class="form-control" required
                 value="<?php echo htmlentities($result->designation); ?>">
        </div>
      </div>

      <input type="hidden" name="idadmin" value="<?php echo (int)$result->idadmin; ?>">

      <div class="form-group">
        <div class="col-sm-8 col-sm-offset-2">
          <button class="btn btn-primary" name="submit" type="submit">Save Changes</button>
        </div>
      </div>

    </form>
  </div>
</div>

</div>
</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
setTimeout(function(){ $('.succWrap').slideUp('slow'); }, 3000);
</script>
</body>
</html>
