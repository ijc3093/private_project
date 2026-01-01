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

// -------------------------
// Validate edit id
// -------------------------
$editid = 0;
if (isset($_GET['edit'])) {
    $editid = (int)$_GET['edit'];
}
if ($editid <= 0) {
    header("Location: userlist.php");
    exit;
}

// -------------------------
// Fetch user
// -------------------------
$stmt = $dbh->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $editid]);
$result = $stmt->fetch(PDO::FETCH_OBJ);

if (!$result) {
    header("Location: userlist.php");
    exit;
}

// -------------------------
// Update user
// -------------------------
if (isset($_POST['submit'])) {

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $mobileno    = trim($_POST['mobileno'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    // hidden existing image
    $image = trim($_POST['current_image'] ?? '');

    if ($name === '' || $email === '' || $gender === '' || $mobileno === '' || $designation === '') {
        $error = "Please fill all required fields.";
    } else {

        // -------------------------
        // Image upload (optional)
        // -------------------------
        if (!empty($_FILES['image']['name'])) {

            $allowed = ['jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed, true)) {
                $error = "Image must be JPG, JPEG, or PNG.";
            } else {

                $uploadDir = __DIR__ . '/../images/'; // /Business_only/images/
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $safeBase = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($_FILES['image']['name'], PATHINFO_FILENAME));
                $newFile  = strtolower($safeBase . '-' . time() . '.' . $ext);

                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newFile)) {
                    $image = $newFile;
                } else {
                    $error = "Image upload failed.";
                }
            }
        }
    }

    // -------------------------
    // Save to DB
    // -------------------------
    if ($error === '') {
        $sql = "UPDATE users
                SET name = :name,
                    email = :email,
                    gender = :gender,
                    mobile = :mobile,
                    designation = :designation,
                    image = :image
                WHERE id = :id";

        $upd = $dbh->prepare($sql);
        $upd->execute([
            ':name' => $name,
            ':email' => $email,
            ':gender' => $gender,
            ':mobile' => $mobileno,
            ':designation' => $designation,
            ':image' => $image,
            ':id' => $editid
        ]);

        header("Location: edit-user.php?edit=" . $editid . "&updated=1");
        exit;
    }
}

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $msg = "Information Updated Successfully";
}

// Re-fetch updated record (so page shows latest)
$stmt = $dbh->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $editid]);
$result = $stmt->fetch(PDO::FETCH_OBJ);
?>

<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User</title>

    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap-social.css">
    <link rel="stylesheet" href="css/bootstrap-select.css">
    <link rel="stylesheet" href="css/fileinput.min.css">
    <link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
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
        <div class="row">
            <div class="col-md-12">
                <h3 class="page-title">Edit User : <?php echo htmlentities($result->name); ?></h3>

                <div class="panel panel-default">
                    <div class="panel-heading">Edit Info</div>

                    <?php if (!empty($error)) { ?>
                        <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
                    <?php } elseif (!empty($msg)) { ?>
                        <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
                    <?php } ?>

                    <div class="panel-body">
                        <form method="post" class="form-horizontal" enctype="multipart/form-data">

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Name *</label>
                                <div class="col-sm-4">
                                    <input type="text" name="name" class="form-control" required value="<?php echo htmlentities($result->name); ?>">
                                </div>

                                <label class="col-sm-2 control-label">Email *</label>
                                <div class="col-sm-4">
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlentities($result->email); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Gender *</label>
                                <div class="col-sm-4">
                                    <select name="gender" class="form-control" required>
                                        <option value="">Select</option>
                                        <option value="Male" <?php echo ($result->gender === "Male") ? "selected" : ""; ?>>Male</option>
                                        <option value="Female" <?php echo ($result->gender === "Female") ? "selected" : ""; ?>>Female</option>
                                    </select>
                                </div>

                                <label class="col-sm-2 control-label">Designation *</label>
                                <div class="col-sm-4">
                                    <input type="text" name="designation" class="form-control" required value="<?php echo htmlentities($result->designation); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Image</label>
                                <div class="col-sm-4">
                                    <input type="file" name="image" class="form-control">
                                </div>

                                <label class="col-sm-2 control-label">Mobile No. *</label>
                                <div class="col-sm-4">
                                    <input type="text" name="mobileno" class="form-control" required value="<?php echo htmlentities($result->mobile); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-sm-8 col-sm-offset-2">
                                    <img src="../images/<?php echo htmlentities($result->image); ?>" width="150" style="border-radius:10px;">
                                    <input type="hidden" name="current_image" value="<?php echo htmlentities($result->image); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-sm-8 col-sm-offset-2">
                                    <button class="btn btn-primary" name="submit" type="submit">Save Changes</button>
                                    <a href="userlist.php" class="btn btn-default">Back</a>
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

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
$(function(){
    setTimeout(function(){ $('.succWrap').slideUp("slow"); }, 3000);
});
</script>
</body>
</html>
