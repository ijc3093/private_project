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
$msg = '';
$error = '';

// Delete contact
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $st = $dbh->prepare("DELETE FROM user_contacts WHERE id = :id AND owner_user_id = :me");
    $st->execute([':id' => $id, ':me' => $meId]);
    $msg = "Contact deleted.";
}

// Load contacts
$st = $dbh->prepare("
    SELECT id, contact_name, contact_email, contact_user_id
    FROM user_contacts
    WHERE owner_user_id = :me
    ORDER BY contact_name ASC, id DESC
");
$st->execute([':me' => $meId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Contacts</title>

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
        <div class="rowline">
          <div>
            <div style="font-weight:700;"><?php echo htmlentities($c['contact_name'] ?: $c['contact_email']); ?></div>
            <small class="text-muted"><?php echo htmlentities($c['contact_email']); ?></small>
          </div>

          <div style="display:flex;gap:8px;">
            <a class="btn btn-success btn-xs"
               href="compose.php?to=<?php echo urlencode($c['contact_email']); ?>">
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
