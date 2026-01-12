<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/includes/identity.php';
//require_once __DIR__ . '/includes/friend_code_helpers.php'; // ✅ ADD THIS

$controller = new Controller();
$dbh = $controller->pdo(); // ✅ FIX: define $dbh BEFORE using it

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
        SELECT idadmin, username, email, mobile, designation, image, role, image_type, friend_code
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

// ✅ Ensure friend code exists (after we know DB + session are valid)
$myFriendCode = ensureAdminFriendCode($dbh);

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

    // prevent duplicate email if changed
    if ($error === '' && strtolower($email) !== strtolower((string)$result->email)) {
        $dup = $dbh->prepare("SELECT idadmin FROM admin WHERE email = :e AND idadmin <> :id LIMIT 1");
        $dup->execute([':e' => $email, ':id' => $idadmin]);
        if ($dup->fetchColumn()) {
            $error = "This email already exists.";
        }
    }

    // prevent duplicate username if changed
    if ($error === '' && strtolower($name) !== strtolower((string)$result->username)) {
        $dup = $dbh->prepare("SELECT idadmin FROM admin WHERE username = :u AND idadmin <> :id LIMIT 1");
        $dup->execute([':u' => $name, ':id' => $idadmin]);
        if ($dup->fetchColumn()) {
            $error = "This username already exists.";
        }
    }

    // ---------------------------------------
    // ✅ DB AVATAR UPLOAD (BLOB)
    // ---------------------------------------
    if ($error === '' && !empty($_FILES['image']['name'])) {
        $allowedTypes = ['image/jpeg','image/png','image/jpg'];
        $tmp = $_FILES['image']['tmp_name'] ?? '';
        $mime = $tmp ? @mime_content_type($tmp) : '';

        if (!$tmp || !is_uploaded_file($tmp)) {
            $error = "Invalid upload.";
        } elseif (!in_array($mime, $allowedTypes, true)) {
            $error = "Image must be JPG or PNG.";
        } else {
            $blob = file_get_contents($tmp);
            $type = $mime;

            $updImg = $dbh->prepare("
                UPDATE admin
                SET image_blob = :b, image_type = :t
                WHERE idadmin = :id
                LIMIT 1
            ");
            $updImg->execute([
                ':b'  => $blob,
                ':t'  => $type,
                ':id' => $idadmin
            ]);
        }
    }

    // ---------------------------------------
    // ✅ Update admin fields
    // ---------------------------------------
    if ($error === '') {
        try {
            $sql = "UPDATE admin
                    SET username = :u,
                        email = :e,
                        mobile = :m,
                        designation = :d
                    WHERE idadmin = :id
                    LIMIT 1";

            $upd = $dbh->prepare($sql);
            $upd->execute([
                ':u'  => $name,
                ':e'  => $email,
                ':m'  => $mobile,
                ':d'  => $designation,
                ':id' => $idadmin
            ]);

            // ✅ keep login session consistent
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

// ✅ refresh friend code after update as well
$myFriendCode = ensureAdminFriendCode($dbh);
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile</title>

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
  <div class="panel-heading">My Profile - <?php echo htmlentities($_SESSION['admin_login']); ?>
</div>

  <?php if (!empty($error)): ?>
    <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
  <?php elseif (!empty($msg)): ?>
    <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
  <?php endif; ?>

  <div class="panel-body">
    <form method="post" class="form-horizontal" enctype="multipart/form-data">

      <!-- ✅ Friend Code (inside form layout so it aligns) -->
      <div class="form-group">
        <label class="col-sm-2 control-label">Friend Code</label>
        <div class="col-sm-4">
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="text"
                   class="form-control"
                   value="<?php echo htmlentities($myFriendCode); ?>"
                   readonly
                   style="font-weight:700;letter-spacing:1px;background:#f9f9f9;">
            <button type="button"
                    class="btn btn-default"
                    onclick="navigator.clipboard.writeText('<?php echo $myFriendCode; ?>')">
              <i class="fa fa-copy"></i>
            </button>
          </div>
          <small class="text-muted">Share this code to add contacts or start internal chat</small>
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-4"></div>
        <div class="col-sm-4 text-center">

          <img src="avatar_admin.php?ts=<?php echo time(); ?>"
          alt="Admin Avatar"
          width="110"
          height="110"
          style="border-radius:50%;object-fit:cover;">

          <input type="file" name="image" class="form-control">
          <small class="text-muted">Avatar stored in phpMyAdmin (MySQL). Max 2MB.</small>
        </div>
        <div class="col-sm-4"></div>
      </div>

      <div class="form-group">
        <label class="col-sm-2 control-label">Name *</label>
        <div class="col-sm-4">
          <input type="text" name="name" class="form-control" required
                 value="<?php echo htmlentities((string)$result->username); ?>">
        </div>

        <label class="col-sm-2 control-label">Email *</label>
        <div class="col-sm-4">
          <input type="email" name="email" class="form-control" required
                 value="<?php echo htmlentities((string)$result->email); ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-2 control-label">Mobile *</label>
        <div class="col-sm-4">
          <input type="text" name="mobile" class="form-control" required
                 value="<?php echo htmlentities((string)$result->mobile); ?>">
        </div>

        <label class="col-sm-2 control-label">Designation *</label>
        <div class="col-sm-4">
          <input type="text" name="designation" class="form-control" required
                 value="<?php echo htmlentities((string)$result->designation); ?>">
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
