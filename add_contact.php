<?php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/identity_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meId = userId();
$meEmail = userEmail();

$msg = '';
$error = '';

function clean_code(string $code): string {
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9\-]/', '', $code);
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $friendCode = clean_code($_POST['friend_code'] ?? '');
    $nickname   = trim($_POST['nickname'] ?? '');

    if ($friendCode === '') {
        $error = "Please enter a Friend Code.";
    } else {
        // Find user by friend_code
        $st = $dbh->prepare("SELECT id, name, email, friend_code FROM users WHERE friend_code = :c LIMIT 1");
        $st->execute([':c' => $friendCode]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            $error = "Friend Code not found.";
        } elseif ((int)$u['id'] === $meId) {
            $error = "You cannot add yourself.";
        } else {
            // Insert contact (by user_id)
            try {
                $ins = $dbh->prepare("
                    INSERT INTO user_contacts (owner_user_id, contact_user_id, contact_email, contact_name)
                    VALUES (:me, :cid, :email, :name)
                ");

                $ins->execute([
                    ':me'    => $meId,
                    ':cid'   => (int)$u['id'],
                    ':email' => $u['email'],
                    ':name'  => $nickname !== '' ? $nickname : ($u['name'] ?? $u['email'])
                ]);

                $msg = "Contact added: " . htmlspecialchars($u['name'] ?? $u['email']);

            } catch (PDOException $e) {
                // Duplicate contact
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = "This contact is already in your list.";
                } else {
                    $error = "Database error: " . $e->getMessage();
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

  <?php if ($error): ?><div class="errorWrap"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="succWrap"><?php echo htmlentities($msg); ?></div><?php endif; ?>

  <div class="box">
    <p>Enter your friend’s <b>Friend Code</b> (they give it to you in person).</p>

    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>Friend Code</label>
        <input type="text" name="friend_code" class="form-control"
               placeholder="XXXX-XXXX-XXXX" required>
      </div>

      <div class="form-group">
        <label>Nickname (optional)</label>
        <input type="text" name="nickname" class="form-control"
               placeholder="e.g., John from church">
      </div>

      <button class="btn btn-primary" type="submit">
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
