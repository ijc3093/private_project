<?php
// /Business_only/profile.php

require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

requireUserLogin();

$controller = new Controller();
$dbh = $controller->pdo();

// session values set by setUserSession()
$userEmail = $_SESSION['user_login'] ?? '';
$userName  = $_SESSION['user_name'] ?? '';

$displayName = ($userName !== '') ? $userName : $userEmail;

$msg = '';
$error = '';

// ------------------------------------
// Helper: generate friend code XXXX-XXXX-XXXX
// ------------------------------------
function generateFriendCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // avoid confusing chars (I,O,1,0)
    $make = function(int $n) use ($chars) {
        $out = '';
        for ($i=0; $i<$n; $i++) {
            $out .= $chars[random_int(0, strlen($chars)-1)];
        }
        return $out;
    };
    return $make(4) . '-' . $make(4) . '-' . $make(4);
}

// ------------------------------------
// LOAD USER DATA (by session email)
// ------------------------------------
$sessionEmail = $_SESSION['user_login'] ?? '';

$sql = "SELECT id, name, username, email, mobile, designation, image_type, friend_code
        FROM users
        WHERE email = :email
        LIMIT 1";
$query = $dbh->prepare($sql);
$query->execute([':email' => $sessionEmail]);
$result = $query->fetch(PDO::FETCH_OBJ);

if (!$result) {
    session_destroy();
    header('location:index.php');
    exit;
}

// ------------------------------------
// ✅ Ensure Friend Code exists
// ------------------------------------
if (empty($result->friend_code)) {
    try {
        // Try a few times in case of UNIQUE collision
        for ($try=0; $try<5; $try++) {
            $code = generateFriendCode();

            // Ensure not already taken
            $chk = $dbh->prepare("SELECT id FROM users WHERE friend_code = :c LIMIT 1");
            $chk->execute([':c' => $code]);
            if ($chk->fetchColumn()) continue;

            // Save it
            $updCode = $dbh->prepare("UPDATE users SET friend_code = :c WHERE id = :id LIMIT 1");
            $updCode->execute([
                ':c'  => $code,
                ':id' => (int)$result->id
            ]);

            $result->friend_code = $code;
            break;
        }
    } catch (Throwable $e) {
        // If this fails, profile still loads (just no code)
    }
}

// ------------------------------------
// UPDATE PROFILE
// ------------------------------------
if (isset($_POST['submit'])) {

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $mobileno    = trim($_POST['mobile'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    if ($name === '' || $email === '' || $mobileno === '' || $designation === '') {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    }

    // prevent duplicate email (only if changed)
    if ($error === '' && strtolower($email) !== strtolower($result->email)) {
        $dup = $dbh->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $dup->execute([':email' => $email]);
        if ($dup->fetchColumn()) {
            $error = "This email already exists.";
        }
    }

    // ---------------------------------------
    // ✅ DB AVATAR UPLOAD (BLOB)
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
                UPDATE users
                SET image_blob = :b, image_type = :t
                WHERE id = :id
                LIMIT 1
            ");
            $updImg->execute([
                ':b'  => $blob,
                ':t'  => $type,
                ':id' => (int)$result->id
            ]);
        }
    }

    // ---------------------------------------
    // ✅ Update user fields
    // ---------------------------------------
    if ($error === '') {

        $sql = "UPDATE users
                SET name = :name,
                    email = :email,
                    mobile = :mobile,
                    designation = :designation
                WHERE id = :id
                LIMIT 1";

        $upd = $dbh->prepare($sql);
        $ok = $upd->execute([
            ':name'        => $name,
            ':email'       => $email,
            ':mobile'      => $mobileno,
            ':designation' => $designation,
            ':id'          => (int)$result->id
        ]);

        if ($ok) {

            // reload user record
            $query = $dbh->prepare("SELECT id, name, email, mobile, designation, image_type, friend_code FROM users WHERE id = :id LIMIT 1");
            $query->execute([':id' => (int)$result->id]);
            $result = $query->fetch(PDO::FETCH_OBJ);

            // update session
            setUserSession([
                'id'    => (int)$result->id,
                'email' => (string)$result->email,
                'name'  => (string)$result->name,
                'image' => 'db'
            ]);

            header("Location: profile.php?updated=1");
            exit;

        } else {
            $error = "Update failed. Please try again.";
        }
    }
}

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $msg = "Information Updated Successfully";
}
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
.errorWrap { padding:10px; background:#dd3d36; color:#fff; margin-bottom:15px; }
.succWrap  { padding:10px; background:#5cb85c; color:#fff; margin-bottom:15px; }

.friend-code-box{
  background:#f7f7f7;
  border:1px solid #ddd;
  padding:12px;
  border-radius:8px;
  margin:10px 0 18px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
}
.friend-code{
  font-size:18px;
  font-weight:800;
  letter-spacing:1px;
}
</style>
</head>

<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">
<div class="row">
<div class="col-md-12">

<div class="panel panel-default">
<div class="panel-heading">My Profile - <?php echo htmlentities($displayName); ?></div>

<?php if($error): ?>
  <div class="errorWrap"><strong>ERROR:</strong> <?php echo htmlentities($error); ?></div>
<?php elseif($msg): ?>
  <div class="succWrap"><strong>SUCCESS:</strong> <?php echo htmlentities($msg); ?></div>
<?php endif; ?>

<div class="panel-body">

  <!-- ✅ Friend Code -->
  <div class="friend-code-box">
    <div>
      <div style="font-weight:700;margin-bottom:4px;">Your Friend Code</div>
      <div class="friend-code"><?php echo htmlentities($result->friend_code ?? ''); ?></div>
      <small class="text-muted">Share this code with someone you meet in person.</small>
    </div>

    <button type="button" class="btn btn-default" id="copyFriendCode">
      <i class="fa fa-copy"></i> Copy
    </button>
  </div>

<form method="post" class="form-horizontal" enctype="multipart/form-data">

<div class="form-group text-center">
    <img
        src="avatar.php?ts=<?php echo time(); ?>"
        style="width:200px;height:200px;border-radius:50%;margin:10px;object-fit:cover;border:1px solid #ddd;"
        alt="Profile"
    >
    <input type="file" name="image" class="form-control">
    <small class="text-muted">Avatar stored in phpMyAdmin (MySQL). Max 2MB.</small>
</div>

<div class="form-group">
    <label class="col-sm-2 control-label">Name *</label>
    <div class="col-sm-4">
        <input type="text" name="name" class="form-control" required
               value="<?php echo htmlentities($result->name ?? ''); ?>">
    </div>

    <label class="col-sm-2 control-label">Email *</label>
    <div class="col-sm-4">
        <input type="email" name="email" class="form-control" required
               value="<?php echo htmlentities($result->email ?? ''); ?>">
    </div>
</div>

<div class="form-group">
    <label class="col-sm-2 control-label">Mobile *</label>
    <div class="col-sm-4">
        <input type="text" name="mobile" class="form-control" required
               value="<?php echo htmlentities($result->mobile ?? ''); ?>">
    </div>

    <label class="col-sm-2 control-label">Designation *</label>
    <div class="col-sm-4">
        <input type="text" name="designation" class="form-control" required
               value="<?php echo htmlentities($result->designation ?? ''); ?>">
    </div>
</div>

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
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
setTimeout(() => $('.succWrap').slideUp('slow'), 3000);

document.getElementById('copyFriendCode')?.addEventListener('click', function(){
  const code = "<?php echo addslashes((string)($result->friend_code ?? '')); ?>";
  if (!code) return alert('No friend code available.');
  navigator.clipboard.writeText(code).then(function(){
    alert('Friend code copied!');
  }).catch(function(){
    // fallback
    const t = document.createElement('textarea');
    t.value = code;
    document.body.appendChild(t);
    t.select();
    document.execCommand('copy');
    document.body.removeChild(t);
    alert('Friend code copied!');
  });
});
</script>
</body>
</html>
