<?php
// /Business_only3/add_contact.php
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

/**
 * Find user by friend_code OR email
 */
function findUserByFriendCodeOrEmail(PDO $dbh, string $value): ?array {
    $value = trim($value);

    if (strpos($value, '@') !== false) {
        $st = $dbh->prepare("SELECT id, name, email, friend_code, status FROM users WHERE email = :v LIMIT 1");
        $st->execute([':v' => $value]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    $st = $dbh->prepare("SELECT id, name, email, friend_code, status FROM users WHERE friend_code = :v LIMIT 1");
    $st->execute([':v' => $value]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/**
 * Check if contact already exists
 */
function contactExists(PDO $dbh, int $meId, int $friendId): bool {
    $st = $dbh->prepare("
        SELECT id
        FROM user_contacts
        WHERE owner_user_id = :me AND friend_user_id = :fid
        LIMIT 1
    ");
    $st->execute([':me' => $meId, ':fid' => $friendId]);
    return (bool)$st->fetchColumn();
}

if (isset($_POST['add_contact'])) {
    $to = trim($_POST['friend'] ?? '');
    $display = trim($_POST['display_name'] ?? '');

    if ($to === '') {
        $error = "Enter a friend code or email.";
    } else {
        $friend = findUserByFriendCodeOrEmail($dbh, $to);

        if (!$friend) {
            $error = "User not found. Check the friend code/email.";
        } elseif ((int)$friend['status'] !== 1) {
            $error = "This user account is inactive.";
        } elseif ((int)$friend['id'] === $meId) {
            $error = "You cannot add yourself.";
        } elseif (contactExists($dbh, $meId, (int)$friend['id'])) {
            $error = "This contact is already in your list.";
        } else {
            // Default display name (if user doesn't enter one)
            if ($display === '') {
                $display = $friend['friend_code'] ?: ($friend['name'] ?: $friend['email']);
            }

            $ins = $dbh->prepare("
                INSERT INTO user_contacts (owner_user_id, friend_user_id, display_name, created_at)
                VALUES (:me, :fid, :dn, NOW())
            ");
            $ins->execute([
                ':me'  => $meId,
                ':fid' => (int)$friend['id'],
                ':dn'  => $display
            ]);

            $msg = "Contact added successfully.";
        }
    }
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Contact</title>

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .succWrap{padding:10px;background:#5cb85c;color:#fff;margin:0 0 15px;}
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

  <h2 class="page-title">Add Contact</h2>

  <?php if ($msg): ?><div class="succWrap"><?php echo htmlentities($msg); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="errorWrap"><?php echo htmlentities($error); ?></div><?php endif; ?>

  <div class="box">
    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>Friend Code or Email</label>
        <input type="text" name="friend" class="form-control"
               placeholder="e.g. ABCD-EFGH-IJKL or friend@gmail.com" required>
        <small class="text-muted">
          Your friend must share their friend code with you in person (or you can use email if you already know it).
        </small>
      </div>

      <div class="form-group">
        <label>Display Name (optional)</label>
        <input type="text" name="display_name" class="form-control"
               placeholder="e.g. John (Church friend)">
        <small class="text-muted">This name shows in your inbox (instead of email).</small>
      </div>

      <button class="btn btn-primary" type="submit" name="add_contact">
        <i class="fa fa-user-plus"></i> Add Contact
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
<script>
setTimeout(() => $('.succWrap,.errorWrap').slideUp('slow'), 2500);
</script>
</body>
</html>
