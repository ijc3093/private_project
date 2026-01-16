<?php
// /Business_only3/contacts.php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/user_identity.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meId = (int)userId();
$msg = '';
$error = '';

if ($meId <= 0) {
    clearUserSession();
    header("Location: index.php?session=reset");
    exit;
}

// Delete contact
if (isset($_GET['del'])) {
    $id = (int)($_GET['del'] ?? 0);
    if ($id > 0) {
        try {
            $st = $dbh->prepare("DELETE FROM user_contacts WHERE id = :id AND owner_user_id = :me");
            $st->execute([':id' => $id, ':me' => $meId]);
            $msg = "Contact deleted.";
        } catch (Throwable $e) {
            $error = "Delete failed.";
        }
    }
}

// Load contacts
$st = $dbh->prepare("
  SELECT
    uc.id,
    uc.display_name AS nickname,
    u.id AS friend_user_id,
    u.name,
    u.username,
    u.friend_code,
    u.email AS friend_email
  FROM user_contacts uc
  LEFT JOIN users u ON u.id = uc.friend_user_id
  WHERE uc.owner_user_id = :me
  ORDER BY
    COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), u.friend_code, u.email, uc.display_name) ASC,
    uc.id DESC
");
$st->execute([':me' => $meId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/images/favicon.png" rel="icon" type="image/png">
    <title>My Contacts</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .box{background:#4b3d3d;border:1px solid #756060;border-radius:8px;padding:18px;box-shadow:0 4px 8px rgba(0,0,0,0.2);transition:.3s;margin-right:3%}
        .hint{color:#777;font-size:13px}
        .card{background-color:#3f3434}
        .bgtransparent{background-color:#3f3434}
        .page-title{margin-top:5%;margin-bottom:15px}
        .btn-btn-primary,.btn{display:inline-block;margin-bottom:0;font-weight:normal;text-align:center;vertical-align:middle;cursor:pointer;border:1px solid transparent;white-space:nowrap;padding:12px 16px;font-size:14px;line-height:1.42857143;border-radius:4px;user-select:none;background:#806449;margin-top:15px}
        .rowline{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #eee;padding:10px 0}
        .sub{color:#777;font-size:12px}
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
            <div class="p-3 mb-3 text-sm text-red-600 bg-red-50 rounded"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="p-3 mb-3 text-sm text-green-700 bg-green-50 rounded"><?php echo h($msg); ?></div>
        <?php endif; ?>

        <h2 class="page-title">My Contacts</h2>

        <div class="box">
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                <a class="btn-btn-primary" href="compose.php">New Message</a>
                <a class="btn-btn-primary" href="add_contact.php">Add Contact</a>
            </div>

            <?php if (empty($rows)): ?>
                <div class="alert alert-info"><b>No contacts yet.</b></div>
            <?php else: ?>
                <?php foreach ($rows as $c): ?>
                    <?php
                        $nickname = trim((string)($c['nickname'] ?? ''));
                        $name     = trim((string)($c['name'] ?? ''));
                        $username = trim((string)($c['username'] ?? ''));
                        $code     = trim((string)($c['friend_code'] ?? ''));
                        $email    = trim((string)($c['friend_email'] ?? ''));

                        // ✅ PRIMARY: real user name -> username -> friend_code -> email
                        $realLabel = $name !== '' ? $name : ($username !== '' ? $username : ($code !== '' ? $code : $email));

                        // ✅ If you want nicknames, show nickname but keep real identity below:
                        $label = $nickname !== '' ? $nickname : $realLabel;

                        // Show friend_code under it (always)
                        $sub = $code !== '' ? $code : $email;

                        // ✅ Compose must use friend_code
                        $toParam = $code !== '' ? $code : $email;
                    ?>
                    <div class="rowline">
                        <div>
                            <div style="font-weight:700;"><?php echo h($label); ?></div>
                            <div class="sub"><?php echo h($sub); ?></div>
                        </div>

                        <div style="display:flex;gap:8px;">
                          
                            <a class="btn btn-success btn-xs" href="user_sendreply.php?to=<?php echo urlencode($toParam); ?>">
                                <i class="fa fa-comment"></i> Message
                            </a>


                            <a class="btn btn-danger btn-xs"
                               href="contacts.php?del=<?php echo (int)$c['id']; ?>"
                               onclick="return confirm('Delete this contact?');">
                               <i class="fa fa-trash"></i>Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

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
