<?php
// /Business_only3/compose.php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

// ✅ Use the correct helper names from session_user.php
$meId    = myUserId();
$meEmail = myUserEmail();

if ($meId <= 0 || $meEmail === '') {
    clearUserSession();
    header("Location: index.php");
    exit;
}

$msg = '';
$error = '';

$prefillTo = trim($_GET['to'] ?? '');

/**
 * ✅ Resolve To value (USER-ONLY CHAT)
 * Allowed input:
 * - friend_code => users.email
 * - username    => users.email
 * - email       => users.email
 *
 * ❌ Admin/support is NOT allowed anymore
 */
function resolveRecipient(PDO $dbh, string $to, int $myRole, string $meEmail): array
{
    $to = trim($to);

    if ($to === '') {
        return ['mode' => 'error', 'error' => 'Recipient is required.'];
    }

    // ❌ Block Admin/support keywords
    $blocked = ['admin', 'support', 'support center'];
    foreach ($blocked as $b) {
        if (strcasecmp($to, $b) === 0) {
            return ['mode' => 'error', 'error' => 'Users cannot message Admin/Support. Please message a user only.'];
        }
    }

    // If looks like email
    if (strpos($to, '@') !== false) {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['mode' => 'error', 'error' => 'Invalid email format.'];
        }

        $st = $dbh->prepare("SELECT id, email, role, status FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e' => $to]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u) return ['mode' => 'error', 'error' => 'User email not found.'];
        if ((int)$u['status'] !== 1) return ['mode' => 'error', 'error' => 'User account is inactive.'];

        // Optional: enforce same-role chat only
        if ((int)$u['role'] !== $myRole) {
            return ['mode' => 'error', 'error' => 'You can only chat with users in your same role.'];
        }

        if (strcasecmp($u['email'], $meEmail) === 0) {
            return ['mode' => 'error', 'error' => 'You cannot message yourself.'];
        }

        return ['mode' => 'user', 'email' => $u['email'], 'label' => $u['email']];
    }

    // Try friend_code
    $st = $dbh->prepare("SELECT id, email, friend_code, role, status FROM users WHERE friend_code = :fc LIMIT 1");
    $st->execute([':fc' => $to]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if ($u) {
        if ((int)$u['status'] !== 1) return ['mode' => 'error', 'error' => 'User account is inactive.'];

        if ((int)$u['role'] !== $myRole) {
            return ['mode' => 'error', 'error' => 'You can only chat with users in your same role.'];
        }

        if (strcasecmp($u['email'], $meEmail) === 0) {
            return ['mode' => 'error', 'error' => 'You cannot message yourself.'];
        }

        return ['mode' => 'user', 'email' => $u['email'], 'label' => $u['friend_code'] ?: $u['email']];
    }

    // Try username (optional)
    $st2 = $dbh->prepare("SELECT id, email, username, role, status FROM users WHERE username = :u LIMIT 1");
    $st2->execute([':u' => $to]);
    $u2 = $st2->fetch(PDO::FETCH_ASSOC);

    if ($u2) {
        if ((int)$u2['status'] !== 1) return ['mode' => 'error', 'error' => 'User account is inactive.'];

        if ((int)$u2['role'] !== $myRole) {
            return ['mode' => 'error', 'error' => 'You can only chat with users in your same role.'];
        }

        if (strcasecmp($u2['email'], $meEmail) === 0) {
            return ['mode' => 'error', 'error' => 'You cannot message yourself.'];
        }

        return ['mode' => 'user', 'email' => $u2['email'], 'label' => $u2['username'] ?: $u2['email']];
    }

    return ['mode' => 'error', 'error' => 'Recipient not found (use friend code, username, or email).'];
}

$myRole = myUserRoleId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');

    $res = resolveRecipient($dbh, $to, $myRole, $meEmail);

    if (($res['mode'] ?? '') === 'error') {
        $error = $res['error'] ?? 'Invalid recipient.';
    } else {
        // ✅ Always redirect with peer email (user-user only)
        $reply = $res['email'];
        header("Location: user_sendreply.php?reply=" . urlencode($reply));
        exit;
    }
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Message</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px;}
    .hint{color:#777;font-size:13px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">New Message</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlentities($error); ?></div>
  <?php endif; ?>

  <div class="box">
    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>To</label>
        <input type="text" name="to" class="form-control"
               value="<?php echo htmlentities($prefillTo); ?>"
               placeholder="Friend code, username, or email" required>

        <div class="hint" style="margin-top:8px;">
          Allowed: <b>Friend code</b>, <b>Username</b>, or <b>Email</b> (user only).<br>
          Admin/Support messaging is disabled.
        </div>
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="fa fa-paper-plane"></i> Start Chat
      </button>

      <a class="btn btn-default" href="contacts.php" style="margin-left:8px;">
        View Contacts
      </a>
    </form>
  </div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
