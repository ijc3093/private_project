<?php
// /Business_only3/register.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/admin/controller.php';

$error = '';
$msg   = '';

$controller = new Controller();
$dbh = $controller->pdo();

/** Make random friend code: USR-XXXX-XXXX */
function makeFriendCode(string $prefix = 'USR'): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $part = function() use ($chars) {
        $s = '';
        for ($i = 0; $i < 4; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
        return $s;
    };
    return strtoupper($prefix . '-' . $part() . '-' . $part());
}

/** Generate UNIQUE friend code in users table */
function generateUniqueFriendCode(PDO $dbh, string $prefix = 'USR', int $maxTries = 60): string {
    for ($i = 0; $i < $maxTries; $i++) {
        $code = makeFriendCode($prefix);
        $st = $dbh->prepare("SELECT 1 FROM users WHERE friend_code = :c LIMIT 1");
        $st->execute([':c' => $code]);
        if (!$st->fetchColumn()) return $code;
    }
    throw new RuntimeException("Unable to generate unique friend code. Try again.");
}

if (isset($_POST['submit'])) {

    $name        = trim($_POST['name'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $passwordRaw = trim($_POST['password'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $mobileno    = trim($_POST['mobileno'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    if ($name === '' || $username === '' || $email === '' || $passwordRaw === '' || $gender === '' || $mobileno === '' || $designation === '') {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {

        $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

        // Image upload optional
        $image  = "default.jpg";
        $folder = __DIR__ . "/images/";

        if (!is_dir($folder)) mkdir($folder, 0755, true);

        if (!empty($_FILES['image']['name']) && !empty($_FILES['image']['tmp_name'])) {
            $file     = $_FILES['image']['name'];
            $file_loc = $_FILES['image']['tmp_name'];

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];

            if (!in_array($ext, $allowed, true)) {
                $error = "Image Extension Not Valid (Use jpg, jpeg, png).";
            } else {
                $base = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($file, PATHINFO_FILENAME));
                $final_file = strtolower($base . '-' . time() . '.' . $ext);

                if (move_uploaded_file($file_loc, $folder . $final_file)) {
                    $image = $final_file;
                } else {
                    $error = "Image upload failed.";
                }
            }
        }

        if ($error === '') {
            try {
                // prevent duplicate email or username
                $check = $dbh->prepare("SELECT 1 FROM users WHERE email = :e OR username = :u LIMIT 1");
                $check->execute([':e' => $email, ':u' => $username]);
                if ($check->fetchColumn()) {
                    $error = "Email or Username already exists. Please login.";
                } else {

                    $friendCode = generateUniqueFriendCode($dbh, 'USR');

                    $dbh->beginTransaction();

                    // ✅ IMPORTANT: include friend_code in INSERT (NOT NULL in your DB)
                    $sql = "INSERT INTO users
                            (name, username, friend_code, email, password, gender, mobile, designation, role, image, status)
                            VALUES
                            (:name, :username, :friend_code, :email, :password, :gender, :mobile, :designation, 4, :image, 1)";
                    $st = $dbh->prepare($sql);
                    $st->execute([
                        ':name'        => $name,
                        ':username'    => $username,
                        ':friend_code' => $friendCode,
                        ':email'       => $email,
                        ':password'    => $password,
                        ':gender'      => $gender,
                        ':mobile'      => $mobileno,
                        ':designation' => $designation,
                        ':image'       => $image,
                    ]);

                    // notification for Admin
                    $noti = $dbh->prepare("
                        INSERT INTO notification (notiuser, notireceiver, notitype, is_read)
                        VALUES (:u, 'Admin', 'Create Account', 0)
                    ");
                    $noti->execute([':u' => $email]);

                    $dbh->commit();

                    echo "<script>alert('Registration Successful! Your Friend Code is: " . addslashes($friendCode) . "');</script>";
                    echo "<script>window.location.href='index.php';</script>";
                    exit;
                }
            } catch (Throwable $e) {
                if ($dbh->inTransaction()) $dbh->rollBack();
                $error = "Server error: " . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register</title>

    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">

    <script>
      function validate(){
        var image_file = document.regform.image.value;
        if (!image_file) return true;
        var allowed = ["jpg","jpeg","png"];
        var ext = image_file.split('.').pop().toLowerCase();
        if (allowed.indexOf(ext) !== -1) return true;
        alert("Image Extension Not Valid (Use jpg, jpeg, png)");
        return false;
      }
    </script>

    <style>
      .errorWrap { padding: 10px; margin: 0 0 15px 0; background: #dd3d36; color: #fff; }
      .succWrap  { padding: 10px; margin: 0 0 15px 0; background: #5cb85c; color: #fff; }
    </style>
</head>
<body>
<div class="login-page bk-img">
  <div class="form-content">
    <div class="container">
      <div class="row">
        <div class="col-md-12">

          <h1 class="text-center text-bold mt-2x">Register</h1>
          <div class="hr-dashed"></div>

          <div class="well row pt-2x pb-3x bk-light text-center">
            <div class="col-md-10 col-md-offset-1">

              <?php if ($error): ?>
                <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
              <?php elseif ($msg): ?>
                <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
              <?php endif; ?>

              <form method="post" class="form-horizontal" enctype="multipart/form-data" name="regform" onsubmit="return validate();">

                <div class="form-group">
                  <label class="col-sm-2 control-label">Full Name *</label>
                  <div class="col-sm-4">
                    <input type="text" name="name" class="form-control" required>
                  </div>

                  <label class="col-sm-2 control-label">Email *</label>
                  <div class="col-sm-4">
                    <input type="email" name="email" class="form-control" required>
                  </div>
                </div>

                <div class="form-group">
                  <label class="col-sm-2 control-label">Username *</label>
                  <div class="col-sm-4">
                    <input type="text" name="username" class="form-control" required>
                  </div>

                  <label class="col-sm-2 control-label">Password *</label>
                  <div class="col-sm-4">
                    <input type="password" name="password" class="form-control" required>
                  </div>
                </div>

                <div class="form-group">
                  <label class="col-sm-2 control-label">Gender *</label>
                  <div class="col-sm-4">
                    <select name="gender" class="form-control" required>
                      <option value="">Select</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                    </select>
                  </div>

                  <label class="col-sm-2 control-label">Phone *</label>
                  <div class="col-sm-4">
                    <input type="text" name="mobileno" class="form-control" required>
                  </div>
                </div>

                <div class="form-group">
                  <label class="col-sm-2 control-label">Avatar</label>
                  <div class="col-sm-4">
                    <input type="file" name="image" class="form-control">
                    <small>Allowed: jpg, jpeg, png</small>
                  </div>

                  <label class="col-sm-2 control-label">Designation *</label>
                  <div class="col-sm-4">
                    <input type="text" name="designation" class="form-control" required>
                  </div>
                </div>

                <br>
                <button class="btn btn-primary" name="submit" type="submit">Register</button>
              </form>

              <br><br>
              <p>Already Have Account? <a href="index.php">Signin</a></p>

            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
