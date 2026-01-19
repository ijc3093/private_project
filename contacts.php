<?php
// /Business_only3/contacts.php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/admin/controller.php';
require_once __DIR__ . '/includes/user_identity.php';

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
    uc.display_name,
    u.id AS friend_user_id,
    u.friend_code,
    u.email AS friend_email
  FROM user_contacts uc
  LEFT JOIN users u ON u.id = uc.friend_user_id
  WHERE uc.owner_user_id = :me
    AND NULLIF(TRIM(uc.display_name), '') IS NOT NULL
  ORDER BY uc.display_name ASC, uc.id DESC
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
        .hint{color:#d5c2b0;;font-size:13px}
        .card{background-color:#3f3434}
        .bgtransparent{background-color:#3f3434}
        .page-title{margin-top:5%;margin-bottom:15px}
        .btn-btn-primary,.btn{display:inline-block;margin-bottom:0;font-weight:normal;text-align:center;vertical-align:middle;cursor:pointer;border:1px solid transparent;white-space:nowrap;padding:12px 16px;font-size:14px;line-height:1.42857143;border-radius:4px;user-select:none;background:#d5c2b0;;margin-top:15px}
        .rowline{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;border-bottom:1px solid #eee;padding:10px 0}
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

            <!-- 🔎 Contact search (client-side, no SQL needed) -->
            <div style="margin-bottom:12px;">
                <input id="contactSearch" type="text"
                       class="w-full !pl-4 !font-normal bgtransparent h-12 !text-sm card"
                       placeholder="Search contacts by name or friend code...">
            </div>

            <?php if (empty($rows)): ?>
                <div class="alert alert-info"><b>No contacts yet.</b></div>
            <?php else: ?>
                <?php foreach ($rows as $c): ?>
                    <?php
                        // Contacts list should ONLY show what the user saved as a name.
                        // Unknown friend codes should NOT be stored here.
                        $label = trim((string)($c['display_name'] ?? ''));
                        $code  = trim((string)($c['friend_code'] ?? ''));
                        $email = trim((string)($c['friend_email'] ?? ''));
                        $sub   = $code !== '' ? $code : $email;
                        $toParam = $code !== '' ? $code : $email;
                    ?>
                    <div class="rowline" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo h(strtolower($label)); ?>" data-code="<?php echo h(strtolower($sub)); ?>">
                        <div>
                            <div style="font-weight:700; color:#d5c2b0;"><?php echo h($label); ?></div>
                            <div class="sub"><?php echo h($sub); ?></div>
                        </div>

                        <div style="display:flex;gap:8px;">
                            <button type="button" class="btn btn-info btn-xs" onclick="openInlineEdit(<?php echo (int)$c['id']; ?>)">
                                <i class="fa fa-pencil"></i> Rename
                            </button>

                            <button type="button" class="btn btn-secondary btn-xs" onclick="undoRename(<?php echo (int)$c['id']; ?>)">
                                <i class="fa fa-undo"></i> Undo
                            </button>

                          
                            <a class="btn btn-warning btn-xs" href="add_contact.php?edit=1&id=<?php echo (int)$c['id']; ?>">
                                <i class="fa fa-edit"></i> Edit
                            </a>

                            <a class="btn btn-success btn-xs" href="user_sendreply.php?to=<?php echo urlencode($toParam); ?>">
                                <i class="fa fa-comment"></i> Message
                            </a>


                            <a class="btn btn-danger btn-xs"
                               href="contacts.php?del=<?php echo (int)$c['id']; ?>"
                               onclick="return confirm('Delete this contact?');">
                               <i class="fa fa-trash"></i>Delete
                            </a>
                        </div>

                        <!-- ✏️ Inline rename box (does not change your layout; only appears when you click Rename) -->
                        <div id="editBox-<?php echo (int)$c['id']; ?>" style="display:none; margin-top:10px; width:100%;">
                            <input id="editInput-<?php echo (int)$c['id']; ?>" type="text"
                                   class="w-full !pl-4 !font-normal bgtransparent h-12 !text-sm card"
                                   value="<?php echo h($label); ?>"
                                   placeholder="Enter corrected name...">

                            <div style="margin-top:8px; display:flex; gap:8px;">
                                <button type="button" class="btn-btn-primary" onclick="saveInlineEdit(<?php echo (int)$c['id']; ?>)">Save</button>
                                <button type="button" class="btn btn-default" onclick="closeInlineEdit(<?php echo (int)$c['id']; ?>)">Cancel</button>
                            </div>
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

<script>
  // 🔎 Search contacts
  (function(){
    const searchEl = document.getElementById('contactSearch');
    if (!searchEl) return;
    searchEl.addEventListener('input', function() {
      const q = (this.value || '').trim().toLowerCase();
      document.querySelectorAll('.rowline[data-id]').forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const code = row.getAttribute('data-code') || '';
        row.style.display = (q === '' || name.includes(q) || code.includes(q)) ? '' : 'none';
      });
    });
  })();

  function openInlineEdit(id) {
    const box = document.getElementById('editBox-' + id);
    if (box) box.style.display = 'block';
  }
  function closeInlineEdit(id) {
    const box = document.getElementById('editBox-' + id);
    if (box) box.style.display = 'none';
  }

  async function saveInlineEdit(id) {
    const input = document.getElementById('editInput-' + id);
    const newName = (input ? input.value : '').trim();
    if (!newName) {
      alert('Name is required.');
      return;
    }

    const res = await fetch('ajax/contact_rename.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams({ contact_id: String(id), display_name: newName })
    });

    const data = await res.json().catch(() => ({}));
    if (!data.ok) {
      alert(data.error || 'Rename failed.');
      return;
    }

    // Update UI instantly
    const row = document.querySelector('.rowline[data-id="' + id + '"]');
    if (row) {
      const titleDiv = row.querySelector('div[style*="font-weight:700"]');
      if (titleDiv) titleDiv.textContent = newName;
      row.setAttribute('data-name', newName.toLowerCase());
    }

    closeInlineEdit(id);
  }

  async function undoRename(id) {
    const res = await fetch('ajax/contact_undo_rename.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams({ contact_id: String(id) })
    });

    const data = await res.json().catch(() => ({}));
    if (!data.ok) {
      alert(data.error || 'Nothing to undo.');
      return;
    }

    const newLabel = data.display_name || '';
    const row = document.querySelector('.rowline[data-id="' + id + '"]');
    if (row) {
      const titleDiv = row.querySelector('div[style*="font-weight:700"]');
      if (titleDiv) titleDiv.textContent = newLabel;
      row.setAttribute('data-name', newLabel.toLowerCase());
      const input = document.getElementById('editInput-' + id);
      if (input) input.value = newLabel;
    }
  }
</script>

</body>
</html>
