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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link href="assets/images/favicon.png" rel="icon" type="image/png">

    <!-- title and description-->
    <title>Socialite</title>
    <meta name="description" content="Socialite - Social sharing network HTML Template">
   
    <!-- css files -->
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">  
    
    <!-- google font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
 
</head>
<body>

  <div class="sm:flex">
    
    <div class="relative lg:w-[580px] md:w-96 w-full p-10 min-h-screen bg-white shadow-xl flex items-center pt-10 dark:bg-slate-900 z-10">

      <div class="w-full lg:max-w-sm mx-auto space-y-10" uk-scrollspy="target: > *; cls: uk-animation-scale-up; delay: 100 ;repeat: true">

        <!-- logo image-->
        <a href="#"> <img src="assets/images/logo.png" class="w-28 absolute top-10 left-10 dark:hidden" alt=""></a>
        <a href="#"> <img src="assets/images/logo-light.png" class="w-28 absolute top-10 left-10 hidden dark:!block" alt=""></a>

        <!-- logo icon optional -->
        <div class="hidden">
          <img class="w-12" src="assets/images/logo-icon.png" alt="Socialite html template">
        </div>

        <!-- title -->
        <div>
          <h2 class="text-2xl font-semibold mb-1.5"> Sign up to get started </h2>
          <p class="text-sm text-gray-700 font-normal">If you already have an account, <a href="index.php" class="text-blue-700">Login here!</a></p>
        </div>
 
        <?php if ($error): ?>
          <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
        <?php elseif ($msg): ?>
          <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
        <?php endif; ?>

            <!-- form -->
            <form method="post" action="#" class="space-y-7 text-sm text-black font-medium dark:text-white"  uk-scrollspy="target: > *; cls: uk-animation-scale-up; delay: 100 ;repeat: true" enctype="multipart/form-data" name="regform" onsubmit="return validate();">
                
            <div class="grid grid-cols-2 gap-4 gap-y-7">
        
                <!-- Full name -->
                <div>
                    <label class="">Full name *</label>
                    <div class="mt-2.5">
                        <input name="name" type="text"  autofocus="" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required> 
                    </div>
                </div>

                <!-- Email Address -->
                <div>
                    <label for="email" class="">Email Address *</label>
                    <div class="mt-2.5">
                        <input name="email" type="email" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required> 
                    </div>
                </div>

                <!-- Username -->
                <div>
                <label class="">Username *</label>
                <div class="mt-2.5">
                    <input name="username" type="username"  class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>  
                </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="">Password *</label>
                    <div class="mt-2.5">
                        <input name="password" type="password"  class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>  
                    </div>
                </div>

                <!-- Gender -->
                <div>
                <label class="">Gender *</label>
                <div class="mt-2.5">
                    <select name="gender" type="text"  class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" class="form-control" required>
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        </select>
                </div>
                </div>

                <!-- Phone -->
                <div>
                    <label class="">Phone *</label>
                    <div class="mt-2.5">
                        <input name="mobileno" type="text"  class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>  
                    </div>
                </div>


                <!-- Upload Image -->
                <div>
                    <label class="">Avatar *</label>
                    <div class="mt-2.5">
                        <input type="file" name="image"  class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>  
                        <small>Allowed: jpg, jpeg, png</small>
                    </div>
                </div>
                
                <!-- Designation -->
                <div>
                    <label class="">Designation *</label>
                    <div class="mt-2.5">
                        <input name="designation" type="text"  class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>  
                    </div>
                </div>

                <div class="col-span-2">
                <label class="inline-flex items-center" id="rememberme">
                    <input type="checkbox" id="accept-terms" class="!rounded-md accent-red-800" />
                    <span class="ml-2">you agree to our <a href="#" class="text-blue-700 hover:underline">terms of use </a> </span>
                </label>
                </div>


                <!-- submit button -->
                <div class="col-span-2">
                <button name="submit" type="submit" class="button bg-primary text-white w-full">Create</button>
                </div>

            </div>
            </form>
        </div>
        </div>
    </div>
  
   
    <!-- Uikit js you can use cdn  https://getuikit.com/docs/installation  or fine the latest  https://getuikit.com/docs/installation -->
    <script src="assets/js/uikit.min.js"></script>
    <script src="assets/js/script.js"></script>

    <!-- Ion icon -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

      <!-- Dark mode -->
      <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark')
        } else {
        document.documentElement.classList.remove('dark')
        }

        // Whenever the user explicitly chooses light mode
        localStorage.theme = 'light'

        // Whenever the user explicitly chooses dark mode
        localStorage.theme = 'dark'

        // Whenever the user explicitly chooses to respect the OS preference
        localStorage.removeItem('theme')
    </script>

</body>
</html>