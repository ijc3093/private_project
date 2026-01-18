<?php
// /Business_only3/register.php
declare(strict_types=1);

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

    $name        = trim((string)($_POST['name'] ?? ''));
    $username    = trim((string)($_POST['username'] ?? ''));
    $email       = trim((string)($_POST['email'] ?? ''));
    $passwordRaw = (string)($_POST['password'] ?? '');
    $gender      = trim((string)($_POST['gender'] ?? ''));
    $mobileno    = trim((string)($_POST['mobileno'] ?? ''));

    if ($name === '' || $username === '' || $email === '' || $passwordRaw === '' || $gender === '' || $mobileno === '') {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        try {
            // prevent duplicate email/username
            $check = $dbh->prepare("SELECT 1 FROM users WHERE email = :e OR username = :u LIMIT 1");
            $check->execute([':e' => $email, ':u' => $username]);
            if ($check->fetchColumn()) {
                $error = "Email or Username already exists. Please login.";
            } else {
                $friendCode = generateUniqueFriendCode($dbh, 'USR');
                $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

                // If DB still requires designation, set safe default
                $designation = ''; // or 'Member'
                $image = 'default.jpg'; // keep a default value if your table requires it

                $dbh->beginTransaction();

                $sql = "INSERT INTO users
                        (name, username, friend_code, email, password, gender, mobile, designation, role, image, status, created_at)
                        VALUES
                        (:name, :username, :friend_code, :email, :password, :gender, :mobile, :designation, 4, :image, 1, NOW())";
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

                // Admin notification (keep if your app uses it)
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
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="assets/images/favicon.png" rel="icon" type="image/png">
    <title>Socialite</title>

    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="sm:flex">
    <div class="relative lg:w-[580px] md:w-96 w-full p-10 min-h-screen bg-white shadow-xl flex items-center pt-10 dark:bg-slate-900 z-10">
        <div class="w-full lg:max-w-sm mx-auto space-y-10" uk-scrollspy="target: > *; cls: uk-animation-scale-up; delay: 100 ;repeat: true">

            <a href="#"><img src="assets/images/logo.png" class="w-28 absolute top-10 left-10 dark:hidden" alt=""></a>
            <a href="#"><img src="assets/images/logo-light.png" class="w-28 absolute top-10 left-10 hidden dark:!block" alt=""></a>

            <div>
                <h2 class="text-2xl font-semibold mb-1.5"> Sign up to get started </h2>
                <p class="text-sm text-gray-700 font-normal">If you already have an account, <a href="index.php" class="text-blue-700">Login here!</a></p>
            </div>

            <?php if ($error): ?>
                <div class="errorWrap"><strong>ERROR</strong>: <?php echo htmlentities($error); ?></div>
            <?php elseif ($msg): ?>
                <div class="succWrap"><strong>SUCCESS</strong>: <?php echo htmlentities($msg); ?></div>
            <?php endif; ?>

            <form method="post" action="#" class="space-y-7 text-sm text-black font-medium dark:text-white"
                  uk-scrollspy="target: > *; cls: uk-animation-scale-up; delay: 100 ;repeat: true">

                <div class="grid grid-cols-2 gap-4 gap-y-7">

                    <div>
                        <label>Full name *</label>
                        <div class="mt-2.5">
                            <input name="name" type="text" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>
                        </div>
                    </div>

                    <div>
                        <label>Email Address *</label>
                        <div class="mt-2.5">
                            <input name="email" type="email" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>
                        </div>
                    </div>

                    <div>
                        <label>Username *</label>
                        <div class="mt-2.5">
                            <input name="username" type="text" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>
                        </div>
                    </div>

                    <div>
                        <label>Password *</label>
                        <div class="mt-2.5">
                            <input name="password" type="password" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>
                        </div>
                    </div>

                    <div>
                        <label>Gender *</label>
                        <div class="mt-2.5">
                            <select name="gender" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label>Phone *</label>
                        <div class="mt-2.5">
                            <input name="mobileno" type="text" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>
                        </div>
                    </div>

                    <div class="col-span-2">
                        <label class="inline-flex items-center" id="rememberme">
                            <input type="checkbox" id="accept-terms" class="!rounded-md accent-red-800" required>
                            <span class="ml-2">you agree to our <a href="#" class="text-blue-700 hover:underline">terms of use</a></span>
                        </label>
                    </div>

                    <div class="col-span-2">
                        <button name="submit" type="submit" class="button bg-primary text-white w-full">Create</button>
                    </div>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="assets/js/uikit.min.js"></script>
<script src="assets/js/script.js"></script>
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<script>
if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark')
} else {
    document.documentElement.classList.remove('dark')
}
</script>

</body>
</html>
