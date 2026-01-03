<?php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/identity_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meId    = (int)($_SESSION['user_id'] ?? 0);
$meEmail = trim($_SESSION['user_login'] ?? '');

$error = '';
$prefill = trim($_GET['to'] ?? '');

function clean_code(string $code): string {
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9\-]/', '', $code);
    return $code;
}

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    $to = preg_replace('/\s+/', '', $to); // remove spaces

    if ($to === '') {
        $error = "Please enter a recipient (friend code or email).";
    } else {

        // 1) If it looks like FRIEND CODE (contains -) => resolve to user by friend_code
        if (strpos($to, '-') !== false) {
            $code = clean_code($to);

            $st = $dbh->prepare("SELECT id, email FROM users WHERE friend_code = :c LIMIT 1");
            $st->execute([':c' => $code]);
            $u = $st->fetch(PDO::FETCH_ASSOC);

            if (!$u) {
                $error = "Friend Code not found.";
            } elseif ((int)$u['id'] === $meId) {
                $error = "You cannot message yourself.";
            } else {
                // Auto-add to contacts (optional)
                $ins = $dbh->prepare("
                    INSERT IGNORE INTO user_contacts (owner_user_id, contact_user_id, contact_email, contact_name)
                    VALUES (:me, :cid, :email, :name)
                ");
                $ins->execute([
                    ':me' => $meId,
                    ':cid' => (int)$u['id'],
                    ':email' => $u['email'],
                    ':name' => $u['email']
                ]);

                header("Location: user_chat.php?to=" . urlencode($u['email']));
                exit;
            }

        } else {
            // 2) Otherwise treat as EMAIL
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $error = "Enter a valid email or Friend Code (XXXX-XXXX-XXXX).";
            } else {
                // Must exist in users table (optional but recommended)
                $st = $dbh->prepare("SELECT id, email FROM users WHERE email = :e LIMIT 1");
                $st->execute([':e' => $to]);
                $u = $st->fetch(PDO::FETCH_ASSOC);

                if (!$u) {
                    $error = "This user email does not exist.";
                } elseif (strcasecmp($to, $meEmail) === 0) {
                    $error = "You cannot message yourself.";
                } else {
                    // Auto-add contact (optional)
                    $ins = $dbh->prepare("
                        INSERT IGNORE INTO user_contacts (owner_user_id, contact_user_id, contact_email, contact_name)
                        VALUES (:me, :cid, :email, :name)
                    ");
                    $ins->execute([
                        ':me' => $meId,
                        ':cid' => (int)$u['id'],
                        ':email' => $u['email'],
                        ':name' => $u['email']
                    ]);

                    header("Location: user_chat.php?to=" . urlencode($u['email']));
                    exit;
                }
            }
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

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .errorWrap{padding:10px;background:#dd3d36;color:#fff;margin:0 0 15px;}
    .box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">New Message</h2>

  <?php if ($error): ?><div class="errorWrap"><?php echo htmlentities($error); ?></div><?php endif; ?>

  <div class="box">
    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>To:</label>
        <input type="text" name="to" class="form-control"
               placeholder="Friend Code (XXXX-XXXX-XXXX) or Email"
               value="<?php echo htmlentities($prefill); ?>"
               required>
        <small class="text-muted">
          Tip: meet in person, exchange Friend Code, then message.
        </small>
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="fa fa-paper-plane"></i> Start Chat
      </button>

      <a class="btn btn-default" href="contacts.php" style="margin-left:8px;">
        My Contacts
      </a>
      <a class="btn btn-default" href="add_contact.php" style="margin-left:8px;">
        Add Contact
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
