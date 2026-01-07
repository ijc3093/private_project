<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$controller = new Controller();
$dbh = $controller->pdo();

$meId   = myAdminId();
$meRole = myRoleId();

$msg = '';
$error = '';

$prefillCode = strtoupper(trim($_GET['code'] ?? ''));

ensureMyAdminFriendCode($dbh);

/**
 * Detect whether a table exists in current DB.
 */
function tableExists(PDO $dbh, string $table): bool {
    $st = $dbh->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = :t
        LIMIT 1
    ");
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
}

/**
 * ✅ IMPORTANT:
 * Always prefer admin_contacts if it exists (to match compose.php and ajax search).
 */
function pickContactsTable(PDO $dbh): string {
    if (tableExists($dbh, 'admin_contacts')) return 'admin_contacts';
    if (tableExists($dbh, 'admin_contacts'))  return 'admin_contacts';
    die("Contacts table not found. Create admin_contacts (recommended) or admin_contacts.");
}

$contactsTable = pickContactsTable($dbh);

/**
 * Normalize friend code:
 * - keep A-Z 0-9
 * - regroup into XXXX-XXXX-XXXX (12 chars) if possible
 */
function normalizeFriendCode(string $code): string {
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9]/', '', $code);

    if ($code === '') return '';

    // If longer than 12, keep first 12 (optional safety)
    if (strlen($code) > 12) $code = substr($code, 0, 12);

    // If exactly 12, format nicely; otherwise just group in 4s
    $parts = str_split($code, 4);
    return implode('-', $parts);
}

/**
 * Same code but without dashes (for robust compare)
 */
function friendCodeKey(string $code): string {
    return strtoupper(str_replace('-', '', $code));
}

/**
 * Check duplicate contact
 */
function contactExists(PDO $dbh, string $table, int $ownerId, int $friendId): bool {
    $st = $dbh->prepare("SELECT id FROM {$table} WHERE owner_admin_id = :o AND friend_admin_id = :f LIMIT 1");
    $st->execute([':o' => $ownerId, ':f' => $friendId]);
    return (bool)$st->fetchColumn();
}

if (isset($_POST['add'])) {
    $codeRaw = (string)($_POST['friend_code'] ?? '');
    $code    = normalizeFriendCode($codeRaw);
    $display = trim((string)($_POST['display_name'] ?? ''));

    if ($code === '') {
        $error = "Please enter Friend Code.";
    } else {
        // Find peer admin by friend_code (match with or without dashes)
        $st = $dbh->prepare("
            SELECT idadmin, username, role, friend_code, status
            FROM admin
            WHERE REPLACE(UPPER(friend_code), '-', '') = :ckey
            LIMIT 1
        ");
        $st->execute([':ckey' => friendCodeKey($code)]);
        $peer = $st->fetch(PDO::FETCH_ASSOC);

        if (!$peer) {
            $error = "Friend Code not found.";
        } elseif ((int)$peer['status'] !== 1) {
            $error = "That account is inactive.";
        } elseif ((int)$peer['idadmin'] === $meId) {
            $error = "You cannot add yourself.";
        } else {
            // Ensure these roles can chat
            $peerRole = (int)$peer['role'];
            $ch = channelForAdminRoles($meRole, $peerRole);
            if ($ch === '') {
                $error = "You cannot chat with this role.";
            } else {
                // Prevent duplicates
                if (contactExists($dbh, $contactsTable, $meId, (int)$peer['idadmin'])) {
                    $msg = "This contact is already in your list.";
                } else {
                    try {
                        $ins = $dbh->prepare("
                            INSERT INTO {$contactsTable} (owner_admin_id, friend_admin_id, display_name)
                            VALUES (:o, :f, :d)
                        ");
                        $ins->execute([
                            ':o' => $meId,
                            ':f' => (int)$peer['idadmin'],
                            ':d' => ($display !== '' ? $display : null),
                        ]);

                        $msg = "Contact added.";
                    } catch (PDOException $e) {
                        $error = "DB error: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
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
    .box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px;max-width:720px;}
    .warnWrap{padding:10px;background:#f0ad4e;color:#fff;margin:0 0 15px;border-radius:6px;}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">Add Contact</h2>

  <?php if ($contactsTable === 'admin_contacts'): ?>
    <div class="warnWrap">
      Warning: You are using legacy table <b>admin_contacts</b>. Recommended table is <b>admin_contacts</b>.
    </div>
  <?php endif; ?>

  <?php if ($msg): ?><div class="succWrap"><?php echo htmlentities($msg); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="errorWrap"><?php echo htmlentities($error); ?></div><?php endif; ?>

  <div class="box">
    <p class="text-muted">
      Ask your friend for their <b>Friend Code</b> (example: <b>AB12-CD34-EF56</b>).
    </p>

    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>Directory Search (name / friend code)</label>
        <div style="position:relative;">
          <input type="text" id="dirSearch" class="form-control" placeholder="Type name or friend code..." autocomplete="off">
          <div id="dirResults"
              style="display:none;position:absolute;left:0;right:0;top:100%;background:#fff;border:1px solid #ddd;z-index:9999;max-height:260px;overflow:auto;">
          </div>
        </div>
        <small class="text-muted">Click a person to fill the Friend Code.</small>
      </div>

      <div class="form-group">
        <label>Friend Code *</label>
        <input type="text" name="friend_code" id="friend_code" class="form-control"
              placeholder="e.g., ADM-2-3670" required
              value="<?php echo htmlentities($prefillCode); ?>">
      </div>


      <button class="btn btn-primary" type="submit" name="add">
        <i class="fa fa-user-plus"></i> Add Contact
      </button>

      <a class="btn btn-default" href="contacts.php" style="margin-left:8px;">
        Back to Contacts
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
<script>
(function(){
  const input = document.getElementById('dirSearch');
  const results = document.getElementById('dirResults');
  const codeInput = document.getElementById('friend_code');
  let timer = null;

  function clearResults(){
    results.innerHTML = '';
    results.style.display = 'none';
  }
  function esc(s){
    return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  async function search(term){
    if (!term || term.trim().length < 1) { clearResults(); return; }
    try{
      const r = await fetch('ajax/admin_directory_search.php?term=' + encodeURIComponent(term), { cache: 'no-store' });
      const data = await r.json();
      if (!data || !data.ok) { clearResults(); return; }

      const items = data.items || [];
      if (items.length === 0){
        results.innerHTML = '<div style="padding:10px 12px;"><b>No match</b><div style="font-size:12px;color:#777;">Try another name/code</div></div>';
        results.style.display = 'block';
        return;
      }

      results.innerHTML = items.map(x => `
        <div class="dirItem"
             data-code="${encodeURIComponent(x.friend_code)}"
             style="padding:10px 12px;cursor:pointer;border-bottom:1px solid #f1f1f1;">
          <div><b>${esc(x.full_name)}</b> <span style="font-size:12px;color:#777;">(${esc(x.designation || '')})</span></div>
          <div style="font-size:12px;color:#777;">Friend Code: ${esc(x.friend_code)}</div>
        </div>
      `).join('');
      results.style.display = 'block';
    } catch(e){
      clearResults();
    }
  }

  input.addEventListener('input', function(){
    clearTimeout(timer);
    timer = setTimeout(() => search(input.value), 200);
  });

  results.addEventListener('click', function(e){
    const row = e.target.closest('.dirItem');
    if (!row) return;
    const code = decodeURIComponent(row.getAttribute('data-code') || '');
    if (code) codeInput.value = code;
    clearResults();
  });

  document.addEventListener('click', function(e){
    if (!e.target.closest('#dirSearch') && !e.target.closest('#dirResults')) clearResults();
  });
})();
</script>
</body>
</html>
