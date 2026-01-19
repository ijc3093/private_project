<?php
// /Business_only3/index.php
require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$error = '';

// already logged in -> feed
if (!empty($_SESSION['user_login']) && !empty($_SESSION['user_id'])) {
    header("Location: feed.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $username    = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Please enter username/email and password.";
    } else {
        try {
            $controller = new Controller();

            // ✅ login supports username OR email
            $user = $controller->userLogin($username, $password);
            if ($user) {
                // ✅ this function must exist in includes/session_user.php
                setUserSession($user);

                // ✅ Online/Offline: mark user as online immediately on successful login
                try {
                    $uid = (int)($_SESSION['user_id'] ?? 0);
                    if ($uid > 0) {
                        $stSeen = $controller->pdo()->prepare("UPDATE users SET last_seen = NOW() WHERE id = :id LIMIT 1");
                        $stSeen->execute([':id' => $uid]);
                    }
                } catch (Throwable $e) {
                    // ignore presence update failures
                }

                header("Location: feed.php");
                exit;
            } else {
                $error = "Invalid login credentials or account inactive.";
            }
        } catch (Throwable $e) {
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

  <div class="flex items-center justify-center min-h-screen bg-gray-50 dark:bg-slate-950">
    
    <div class="relative w-full max-w-[450px] p-10 bg-white shadow-xl rounded-2xl dark:bg-slate-900 z-10 mx-4">

      <div class="w-full space-y-10" uk-scrollspy="target: > *; cls: uk-animation-scale-up; delay: 100; repeat: true">

        <div>
          <h2 class="text-2xl font-semibold mb-1.5"> Sign in to your account </h2>
        </div>
 
        <?php if ($error !== ''): ?>
            <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400" role="alert">
                <?php echo htmlentities($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off" class="space-y-7 text-sm text-black font-medium dark:text-white">
            
          <div>
              <label class="">Username</label>
              <div class="mt-2.5">
                  <input name="username" type="text" autofocus="" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required> 
              </div>
          </div>

          <div>
            <label class="">Password</label>
            <div class="mt-2.5">
                <input name="password" type="password" class="!w-full !rounded-lg !bg-transparent !shadow-sm !border-slate-200 dark:!border-slate-800 dark:!bg-white/5" required>  
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <input id="rememberme" name="rememberme" type="checkbox" class="rounded border-gray-300">
              <label for="rememberme" class="font-normal">Remember me</label>
            </div>
            
          </div>
          <a href="forget.php" class="text-blue-700">Forgot password?</a>
          <div>
            <button name="login" type="submit" value="1" class="button bg-blue-600 hover:bg-blue-700 text-white w-full py-2 rounded-lg transition-colors">
                Sign in
            </button>
          </div>
          <p class="text-sm text-gray-700 font-normal dark:text-gray-400">
            If you haven’t signed up yet. <a href="register.php" class="text-blue-700">Register here!</a> 
          </p>
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