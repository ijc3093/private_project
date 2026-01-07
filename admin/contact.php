<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$meId = myAdminId();
$msg = '';
$error = '';

ensureMyAdminFriendCode($dbh);

// delete
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $st = $dbh->prepare("DELETE FROM admin_contacts WHERE id = :id AND owner_admin_id = :me LIMIT 1");
    $st->execute([':id' => $id, ':me' => $meId]);
    $msg = "Contact deleted.";
}

// load contacts
$st = $dbh->prepare("
    SELECT
      ac.id,
      ac.display_name,
      a.username,
      a.friend_code,
      a.role
    FROM admin_contacts ac
    JOIN admin a ON a.idadmin = ac.friend_admin_id
    WHERE ac.owner_admin_id = :me
    ORDER BY COALESCE(ac.display_name, a.friend_code, a.username) ASC, ac.id DESC
");
$st->execute([':me' => $meId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function roleLabel(int $r): string {
    if ($r === 1) return 'Admin';
    if ($r === 2) return 'Manager';
    if ($r === 3) return 'Gospel';
    if ($r === 4) return 'Staff';
    return 'Role?';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contacts</title>

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .succWrap{padding:10px;background:#5cb85c;color:#fff;margin:0 0 15px;}
    .errorWrap{padding:10px;background:#dd3d36;color:#fff;margin:0 0 15px;}
    .box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px;}
    .rowline{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eee;}
    .rowline:last-child{border-bottom:none;}
    .meta{color:#777;font-size:12px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">My Contacts</h2>

  <?php if ($msg): ?><div class="succWrap"><?php echo htmlentities($msg); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="errorWrap"><?php echo htmlentities($error); ?></div><?php endif; ?>

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
          $display = trim((string)($c['display_name'] ?? ''));
          $fc = trim((string)($c['friend_code'] ?? ''));
          $uname = trim((string)($c['username'] ?? ''));
          $title = ($display !== '') ? $display : (($fc !== '') ? $fc : $uname);
        ?>
        <div class="rowline">
          <div>
            <div style="font-weight:700;"><?php echo htmlentities($title); ?></div>
            <div class="meta">
              Friend Code: <b><?php echo htmlentities($fc); ?></b> • <?php echo htmlentities(roleLabel((int)$c['role'])); ?>
            </div>
          </div>

          <div style="display:flex;gap:8px;">
            <a class="btn btn-success btn-xs"
               href="sendreply.php?reply=<?php echo urlencode($fc); ?>">
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
setTimeout(() => $('.succWrap,.errorWrap').slideUp('slow'), 2500);
</script>
</body>
</html>
