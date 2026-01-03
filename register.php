<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/admin/controller.php';

$error = '';
$msg   = '';

// ✅ Get PDO from Controller
$controller = new Controller();
$dbh = $controller->pdo();

if (isset($_POST['submit'])) {

    // -----------------------------
    // 1) Sanitize input
    // -----------------------------
    $name        = trim($_POST['name'] ?? '');
    $username        = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $passwordRaw = trim($_POST['password'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $mobileno    = trim($_POST['mobileno'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    // Basic validation
    if ($name === '' || $username === '' || $email === '' || $passwordRaw === '' || $gender === '' || $mobileno === '' || $designation === '') {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {

        // ✅ IMPORTANT: store secure password hash (NOT md5)
        $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

        // -----------------------------
        // 2) Image upload (optional)
        // -----------------------------
        $image  = "default.jpg"; // make sure /Business_only/images/default.jpg exists
        $folder = __DIR__ . "/images/";

        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

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

        // -----------------------------
        // 3) Database insert
        // -----------------------------
        if ($error === '') {
            try {
                // ✅ Prevent duplicate emails
                $checkSql = "SELECT id FROM users WHERE email = :email LIMIT 1";
                $check = $dbh->prepare($checkSql);
                $check->execute([':email' => $email]);

                if ($check->rowCount() > 0) {
                    $error = "This email already exists. Please login.";
                } else {

                    // ✅ Use transaction (safe)
                    $dbh->beginTransaction();

                    // ✅ Insert public user (status=1 confirmed)
                    $sql = "INSERT INTO users (name, username, email, password, gender, mobile, designation, image, status)
                            VALUES (:name, :username, :email, :password, :gender, :mobile, :designation, :image, 1)";
                    $query = $dbh->prepare($sql);
                    $query->execute([
                        ':name'        => $name,
                        ':username'    => $username,
                        ':email'       => $email,
                        ':password'    => $password,
                        ':gender'      => $gender,
                        ':mobile'      => $mobileno,
                        ':designation' => $designation,
                        ':image'       => $image
                    ]);

                    // ✅ Create notification for Admin
                    $sqlnoti = "INSERT INTO notification (notiuser, notireceiver, notitype)
                                VALUES (:notiuser, :notireceiver, :notitype)";
                    $querynoti = $dbh->prepare($sqlnoti);
                    $querynoti->execute([
                        ':notiuser'     => $email,
                        ':notireceiver' => 'Admin',
                        ':notitype'     => 'Create Account'
                    ]);

                    $dbh->commit();

                    echo "<script>alert('Registration Successful!');</script>";
                    echo "<script>window.location.href='index.php';</script>";
                    exit;
                }

            } catch (PDOException $e) {
                if ($dbh->inTransaction()) {
                    $dbh->rollBack();
                }
                $error = "Database error: " . $e->getMessage();
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

    <script type="text/javascript">
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
        .errorWrap {
            padding: 10px;
            margin: 0 0 15px 0;
            background: #dd3d36;
            color: #fff;
        }
        .succWrap {
            padding: 10px;
            margin: 0 0 15px 0;
            background: #5cb85c;
            color: #fff;
        }
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

                            <?php if($error){ ?>
                                <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
                            <?php } else if($msg){ ?>
                                <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
                            <?php } ?>

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
                                        <input type="username" name="username" class="form-control" required>
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
                                        <input type="number" name="mobileno" class="form-control" required>
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
