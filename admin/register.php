<?php
require_once __DIR__ . '/includes/session_admin.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/controller.php';

$db = new Controller();
$msg = '';
$error = '';

// ✅ Load roles from DB (role table)
$roles = [];
try {
    $stmt = $db->pdo()->prepare("SELECT idrole, name FROM role ORDER BY idrole ASC");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Could not load roles. Please check your role table.";
}

if (isset($_POST['submit'])) {

    // Sanitize
    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = trim($_POST['password'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $mobileno    = trim($_POST['mobileno'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $roleId      = (int)($_POST['role'] ?? 0); // ✅ role id now, not role name

    // Validate required fields
    if ($name === '' || $email === '' || $password === '' || $gender === '' || $mobileno === '' || $designation === '' || $roleId === 0) {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        // ✅ check duplicates in ADMIN table
        if ($db->findAdminByEmailOrUsername($email, $name)) {
            $error = "This Email or Username already exists in admin. Please login.";
        }
    }

    // ✅ Validate roleId exists in role table
    if ($error === '') {
        $validRole = false;
        foreach ($roles as $r) {
            if ((int)$r['idrole'] === $roleId) {
                $validRole = true;
                break;
            }
        }
        if (!$validRole) {
            $error = "Invalid role selected.";
        }
    }

    // Upload image
    $image = 'default.jpg';

    if ($error === '') {
        if (!empty($_FILES['image']['name']) && isset($_FILES['image']['tmp_name'])) {

            $allowedExt = ['jpg', 'jpeg', 'png'];
            $original = $_FILES['image']['name'];
            $tmp = $_FILES['image']['tmp_name'];
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $error = "Image must be JPG, JPEG, or PNG.";
            } else {
                $base = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($original, PATHINFO_FILENAME));
                $finalName = strtolower($base . '-' . time() . '.' . $ext);

                // ✅ admin images folder: /admin/images/
                $folder = __DIR__ . '/images/';
                if (!is_dir($folder)) {
                    mkdir($folder, 0755, true);
                }

                if (move_uploaded_file($tmp, $folder . $finalName)) {
                    $image = $finalName;
                } else {
                    $error = "Image upload failed.";
                }
            }
        }
    }

    if ($error === '') {

        // ✅ Insert into ADMIN table with roleId
        $ok = $db->registerAdmin([
            'username'    => $name,
            'email'       => $email,
            'password'    => password_hash($password, PASSWORD_DEFAULT),
            'gender'      => $gender,
            'mobile'      => $mobileno,
            'designation' => $designation,
            'role'        => $roleId,   // ✅ teacher etc works automatically
            'image'       => $image,
            'status'      => 1
        ]);

        if ($ok) {
            // Notification
            $db->addNotification($email, 'Admin', 'create Account');

            echo "<script>alert('Registration Successful!');</script>";
            echo "<script>window.location.href='index.php';</script>";
            exit;
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">

  <title>Admin Register</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-social.css">
  <link rel="stylesheet" href="css/bootstrap-select.css">
  <link rel="stylesheet" href="css/fileinput.min.css">
  <link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
  <link rel="stylesheet" href="css/style.css">

  <script>
    function validate(){
      var image = document.regform.image.value;
      if(!image) return true;

      var allowed = ["jpg","jpeg","png"];
      var ext = image.split('.').pop().toLowerCase();
      if(allowed.indexOf(ext) !== -1) return true;

      alert("Image Extension Not Valid (Use jpg, jpeg, png)");
      return false;
    }
  </script>
</head>

<body>
<div class="login-page bk-img" style="background-image: url(img/background.jpg);">
  <div class="form-content">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <h1 class="text-center text-bold mt-2x">Admin Register</h1>
          <div class="hr-dashed"></div>

          <div class="well row pt-2x pb-3x bk-light text-center">

            <?php if($error): ?>
              <div class="alert alert-danger"><?php echo htmlentities($error); ?></div>
            <?php elseif($msg): ?>
              <div class="alert alert-success"><?php echo htmlentities($msg); ?></div>
            <?php endif; ?>

            <form method="post" class="form-horizontal" enctype="multipart/form-data" name="regform" onsubmit="return validate();">

              <div class="form-group">
                <label class="col-sm-1 control-label">Name<span style="color:red">*</span></label>
                <div class="col-sm-5">
                  <input type="text" name="name" class="form-control" required>
                </div>

                <label class="col-sm-1 control-label">Email<span style="color:red">*</span></label>
                <div class="col-sm-5">
                  <input type="email" name="email" class="form-control" required>
                </div>
              </div>

              <div class="form-group">
                <label class="col-sm-1 control-label">Password<span style="color:red">*</span></label>
                <div class="col-sm-5">
                  <input type="password" name="password" class="form-control" required>
                </div>

                <label class="col-sm-1 control-label">Designation<span style="color:red">*</span></label>
                <div class="col-sm-5">
                  <input type="text" name="designation" class="form-control" required>
                </div>
              </div>

              <div class="form-group">
                <label class="col-sm-1 control-label">Gender<span style="color:red">*</span></label>
                <div class="col-sm-5">
                  <select name="gender" class="form-control" required>
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </div>

                <label class="col-sm-1 control-label">Phone<span style="color:red">*</span></label>
                <div class="col-sm-5">
                  <input type="number" name="mobileno" class="form-control" required>
                </div>
              </div>

              <div class="form-group">
                <label class="col-sm-1 control-label">Avatar</label>
                <div class="col-sm-5">
                  <input type="file" name="image" class="form-control">
                </div>

                <label class="col-sm-1 control-label">Role<span style="color:red">*</span></label>
                <div class="col-sm-5" style="text-align:left;">
                  <?php foreach($roles as $r): ?>
                    <label>
                      <input type="radio" name="role" value="<?php echo (int)$r['idrole']; ?>" required>
                      <?php echo htmlentities($r['name']); ?>
                    </label><br>
                  <?php endforeach; ?>
                </div>
              </div>

              <br>
              <button class="btn btn-primary" name="submit" type="submit">Register</button>
            </form>

            <br><br>
            <p>Already Have an Account? <a href="index.php">Signin</a></p>

          </div>
        </div>
      </div>
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
</body>
</html>
