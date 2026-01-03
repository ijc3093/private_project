<?php
// /Business_only3/compose.php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/identity_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meId    = userId();
$meEmail = userEmail();

$msg = '';
$error = '';

$prefillTo = trim($_GET['to'] ?? '');

/**
 * Resolve To value:
 * - "Admin" or "support" => Admin chat
 * - friend_code => get users.email
 * - email => use directly (only if user exists and active)
 */
function resolveRecipient(PDO $dbh, string $to): array {
    $to = trim($to);

    // Support Center shortcuts
    if (strcasecmp($to, 'admin') === 0 || strcasecmp($to, 'support') === 0 || strcasecmp($to, 'support center') === 0) {
        return ['mode' => 'admin', 'email' => 'Admin', 'label' => 'Support Center'];
    }

    // If looks like email
    if (strpos($to, '@') !== false) {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['mode' => 'error', 'error' => 'Invalid email format.'];
        }

        // must be a real active user OR active admin email
        $stA = $dbh->prepare("SELECT idadmin FROM admin WHERE email = :e AND status=1 LIMIT 1");
        $stA->execute([':e' => $to]);
        if ($stA->fetchColumn()) {
            return ['mode' => 'adminEmail', 'email' => $to, 'label' => 'Support Center'];
        }

        $stU = $dbh->prepare("SELECT id, email, status FROM users WHERE email = :e LIMIT 1");
        $stU->execute([':e' => $to]);
        $u = $stU->fetch(PDO::FETCH_ASSOC);

        if (!$u) return ['mode' => 'error', 'error' => 'User email not found.'];
        if ((int)$u['status'] !== 1) return ['mode' => 'error', 'error' => 'User account is inactive.'];

        return ['mode' => 'user', 'email' => $u['email'], 'label' => $u['email']];
    }

    // Friend code
    $st = $dbh->prepare("SELECT id, email, friend_code, status FROM users WHERE friend_code = :fc LIMIT 1");
    $st->execute([':fc' => $to]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) return ['mode' => 'error', 'error' => 'Friend code not found.'];
    if ((int)$u['status'] !== 1) return ['mode' => 'error', 'error' => 'User account is inactive.'];

    return ['mode' => 'user', 'email' => $u['email'], 'label' => $u['friend_code'] ?: $u['email']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    if ($to === '') {
        $error = "Please enter To: friend code or email.";
    } else {
        $res = resolveRecipient($dbh, $to);
        if (($res['mode'] ?? '') === 'error') {
            $error = $res['error'] ?? 'Invalid recipient.';
        } else {
            // For chat page we pass reply as:
            // - 'Admin' (support)
            // - or peer email (user or admin email)
            $reply = ($res['mode'] === 'admin' || $res['mode'] === 'adminEmail') ? 'Admin' : $res['email'];
            header("Location: sendreply_user.php?reply=" . urlencode($reply));
            exit;
        }
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
               placeholder="Friend code (ABCD-EFGH-IJKL)" required>
        <div class="hint" style="margin-top:8px;">
          Tip: Add friends using <a href="add_contact.php">Add Contact</a>.  
          For support, type <b>Admin</b> or <b>Support</b>.
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
