<?php
// /Business_only3/compose.php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meId    = myUserId();
$meCode  = function_exists('userFriendCode') ? trim((string)userFriendCode()) : trim((string)($_SESSION['user_friend_code'] ?? ''));
$myRole  = function_exists('userRoleId') ? (int)userRoleId() : (int)($_SESSION['user_role'] ?? 0);

if ($meId <= 0 || $meCode === '') {
    clearUserSession();
    header("Location: index.php?session=reset");
    exit;
}

$error = '';
$prefillTo = trim((string)($_GET['to'] ?? ''));

/**
 * Friend-code ONLY recipient resolver
 * Returns: ok, peerCode
 */
function resolveRecipientFriendCode(PDO $dbh, string $peerCode, string $meCode, int $myRole): array
{
    $peerCode = trim($peerCode);

    if ($peerCode === '') {
        return ['ok' => false, 'error' => 'Friend code is required.'];
    }

    // Basic format guard
    if (!preg_match('/^[A-Z]{3}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $peerCode)) {
        return ['ok' => false, 'error' => 'Invalid friend code format (example: USR-AB12-CD34).'];
    }

    // Prevent self
    if (strcasecmp($peerCode, $meCode) === 0) {
        return ['ok' => false, 'error' => 'You cannot message yourself.'];
    }

    $st = $dbh->prepare("SELECT id, friend_code, role, status FROM users WHERE friend_code = ? LIMIT 1");
    $st->execute([$peerCode]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) return ['ok' => false, 'error' => 'Friend code not found.'];
    if ((int)($u['status'] ?? 0) !== 1) return ['ok' => false, 'error' => 'User account is inactive.'];

    // OPTIONAL rule: same role only (keep if you want)
    if ((int)($u['role'] ?? 0) !== $myRole) {
        return ['ok' => false, 'error' => 'You can only chat with users in your same role.'];
    }

    return ['ok' => true, 'peerCode' => (string)$u['friend_code']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim((string)($_POST['to'] ?? ''));

    $res = resolveRecipientFriendCode($dbh, $to, $meCode, $myRole);

    if (!$res['ok']) {
        $error = (string)($res['error'] ?? 'Invalid recipient.');
    } else {
        // ✅ IMPORTANT: send friend_code to sendreply
        header("Location: user_sendreply.php?to=" . urlencode($res['peerCode']));

        exit;
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
    <title>New Message</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .box{background:#4b3d3d;border:1px solid #756060;border-radius:8px;padding:18px;box-shadow:0 4px 8px rgba(0,0,0,0.2);transition:.3s;margin-right:3%}
        .hint{color:#d5c2b0;;font-size:13px}
        .card{background-color:#3f3434}
        .bgtransparent{background-color:#3f3434}
        .page-title{margin-top:15%;margin-bottom:15px}
        .btn-btn-primary,.btn{display:inline-block;margin-bottom:0;font-weight:normal;text-align:center;vertical-align:middle;cursor:pointer;border:1px solid transparent;white-space:nowrap;padding:12px 16px;font-size:14px;line-height:1.42857143;border-radius:4px;user-select:none;background:#d5c2b0;;margin-top:15px}
    </style>
</head>
<body>
<div id="wrapper">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div id="site__sidebar" class="fixed top-0 left-0 z-[99] pt-[--m-top] overflow-hidden transition-transform xl:duration-500 max-xl:w-full max-xl:-translate-x-full">
        <div class="p-2 max-xl:bg-white shadow-sm 2xl:w-72 sm:w-64 w-[80%] h-[calc(100vh-64px)] relative z-30 max-lg:border-r dark:max-xl:!bg-slate-700 dark:border-slate-700">
            <div class="pr-4" data-simplebar>
                <?php include __DIR__ . '/includes/leftbar.php'; ?>
            </div>
        </div>
        <div id="site__sidebar__overly" class="absolute top-0 left-0 z-20 w-screen h-screen xl:hidden backdrop-blur-sm" uk-toggle="target: #site__sidebar ; cls :!-translate-x-0"></div>
    </div>

    <main id="site__main" class="2xl:ml-[--w-side] xl:ml-[--w-side-sm] p-2.5 h-[calc(100vh-var(--m-top))] mt-[--m-top]">
        <?php if ($error): ?>
            <div class="p-3 mb-3 text-sm text-red-600 bg-red-50 rounded"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <h2 class="page-title">New Message</h2>

        <div class="box">
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label class="hint">To</label>
                    <input type="text" name="to"
                           class="w-full !pl-10 !font-normal bgtransparent h-12 !text-sm card"
                           value="<?php echo htmlspecialchars($prefillTo, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="Friend code only (ex: USR-AB12-CD34)" required>

                    <div class="hint" style="margin-top:8px;">
                        Allowed: <b>Friend code</b> only.
                    </div>
                </div>

                <button class="btn-btn-primary" type="submit">Start Chat</button>
                <a class="btn btn-default" href="contacts.php" style="margin-left:8px;">View Contacts</a>
            </form>
        </div>
    </main>
</div>

<script src="assets/js/uikit.min.js"></script>
<script src="assets/js/simplebar.js"></script>
<script src="assets/js/script.js"></script>
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>
