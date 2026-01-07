<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meId = myAdminId();
$meRole = myRoleId();

if ($meId <= 0) die("Missing admin id in session. Please set \$_SESSION['admin_id'] at login.");

$msg = '';
$error = '';

if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $st = $dbh->prepare("DELETE FROM admin_contacts WHERE id = :id AND owner_admin_id = :me");
    $st->execute([':id' => $id, ':me' => $meId]);
    $msg = "Contact deleted.";
}

$st = $dbh->prepare("
    SELECT
      ac.id,
      ac.display_name,
      a.idadmin AS friend_id,
      a.username AS friend_username,
      a.email AS friend_email,
      a.friend_code AS friend_code,
      a.role AS friend_role
    FROM admin_contacts ac
    JOIN admin a ON a.idadmin = ac.friend_admin_id
    WHERE ac.owner_admin_id = :me
    ORDER BY ac.display_name ASC, a.username ASC
");
$st->execute([':me' => $meId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function h($s): string {
    return htmlentities((string)$s);
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Contacts</title>

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .succWrap{padding:10px;background:#5cb85c;color:#fff;margin:0 0 15px;}
    .errorWrap{padding:10px;background:#dd3d36;color:#fff;margin:0 0 15px;}
    .box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px;}
    .rowline{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eee;}
    .rowline:last-child{border-bottom:none;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">My Admin Contacts</h2>

  <?php if ($msg): ?><div class="succWrap"><?php echo h($msg); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="errorWrap"><?php echo h($error); ?></div><?php endif; ?>

  <div class="box">
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
      <a class="btn btn-primary" href="compose.php"><i class="fa fa-pencil"></i> New Message</a>
      <a class="btn btn-default" href="add_contact.php"><i class="fa fa-user-plus"></i> Add Contact</a>
    </div>

    <?php if (empty($rows)): ?>
      <div class="alert alert-info">No contacts yet.</div>
    <?php else: ?>
      <?php foreach ($rows as $c): ?>
        <?php
          $name = $c['display_name'] ?: ($c['friend_code'] ?: $c['friend_username']);
          $sub  = $c['friend_code'] ?: $c['friend_email'];
        ?>
        <div class="rowline">
          <div>
            <div style="font-weight:700;"><?php echo h($name); ?></div>
            <small class="text-muted"><?php echo h($sub); ?></small>
          </div>

          <div style="display:flex;gap:8px;">
            <a class="btn btn-success btn-xs"
               href="compose.php?to=<?php echo urlencode((string)$c['friend_username']); ?>">
              <i class="fa fa-comment"></i> Message
            </a>

            <a class="btn btn-danger btn-xs"
               href="contacts.php?del=<?php echo (int)$c['id']; ?>"
               onclick="return confirm('Delete this contact?');">
              <i class="fa fa-trash"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
setTimeout(() => $('.succWrap,.errorWrap').slideUp('slow'), 2200);
</script>
</body>
</html>
