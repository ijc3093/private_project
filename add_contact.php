<?php
// /Business_only3/add_contact.php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/admin/controller.php';
require_once __DIR__ . '/includes/user_identity.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meId    = (int)userId();
$meEmail = trim((string)userEmail());

$msg = '';
$error = '';

// Prefill friend code when coming from messages sidebar
$prefillFriend = trim((string)($_GET['friend'] ?? ''));
$returnTo = trim((string)($_GET['return'] ?? ''));

// ===============================
// MODE DETECTION (EDIT VS ADD)
// ===============================
$isEdit    = isset($_GET['edit'], $_GET['id']) && (string)$_GET['edit'] === '1';
$contactId = $isEdit ? (int)$_GET['id'] : 0;

// Prefill display name for edit
$prefillDisplay = '';

if ($meId <= 0) {
    clearUserSession();
    header("Location: index.php?session=reset");
    exit;
}

/**
 * Find user by friend_code OR email OR username (optional)
 * Returns: id, name, username, email, friend_code, status
 */
function findUserByAny(PDO $dbh, string $value): ?array {
    $value = trim($value);
    if ($value === '') return null;

    // email
    if (strpos($value, '@') !== false) {
        $st = $dbh->prepare("SELECT id, name, username, email, friend_code, status FROM users WHERE email = ? LIMIT 1");
        $st->execute([$value]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    // friend_code
    if (preg_match('/^[A-Z]{3}-/i', $value)) {
        $st = $dbh->prepare("SELECT id, name, username, email, friend_code, status FROM users WHERE friend_code = ? LIMIT 1");
        $st->execute([$value]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    // username
    $st = $dbh->prepare("SELECT id, name, username, email, friend_code, status FROM users WHERE username = ? LIMIT 1");
    $st->execute([$value]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function contactExists(PDO $dbh, int $meId, int $friendId): bool {
    $st = $dbh->prepare("SELECT id FROM user_contacts WHERE owner_user_id = ? AND friend_user_id = ? LIMIT 1");
    $st->execute([$meId, $friendId]);
    return (bool)$st->fetchColumn();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ===============================
// LOAD CONTACT WHEN EDITING
// ===============================
if ($isEdit) {
    if ($contactId <= 0) {
        $error = "Invalid contact id.";
        $isEdit = false;
    } else {
        // Join to users so we can show friend_code in the input (readonly)
        $st = $dbh->prepare("
            SELECT uc.id,
                   uc.friend_user_id,
                   uc.display_name,
                   u.friend_code
            FROM user_contacts uc
            JOIN users u ON u.id = uc.friend_user_id
            WHERE uc.id = ? AND uc.owner_user_id = ?
            LIMIT 1
        ");
        $st->execute([$contactId, $meId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $error = "Contact not found.";
            $isEdit = false;
        } else {
            $prefillFriend   = trim((string)($row['friend_code'] ?? $prefillFriend));
            $prefillDisplay  = trim((string)($row['display_name'] ?? ''));
        }
    }
}

// ===============================
// SAVE: EDIT MODE (UPDATE NAME)
// ===============================
if ($isEdit && isset($_POST['save_contact'])) {
    $display  = trim((string)($_POST['display_name'] ?? ''));
    $returnTo = trim((string)($_POST['return_to'] ?? $returnTo));
    $contactIdPost = (int)($_POST['contact_id'] ?? 0);

    if ($contactIdPost <= 0) {
        $error = "Invalid contact id.";
    } elseif ($display === '') {
        $error = "Please enter a name before saving.";
    } else {
        $up = $dbh->prepare("
            UPDATE user_contacts
            SET display_name = ?
            WHERE id = ? AND owner_user_id = ?
            LIMIT 1
        ");
        $up->execute([$display, $contactIdPost, $meId]);

        // After editing, go back to contacts or messages
        if ($returnTo === 'messages') {
            // keep friend_code from prefill
            header('Location: messages.php?peer=' . urlencode($prefillFriend));
            exit;
        }

        header('Location: contacts.php?updated=1');
        exit;
    }

    // keep prefill if error
    $prefillDisplay = $display;
}

// ===============================
// SAVE: ADD MODE (INSERT NEW CONTACT)
// ===============================
if (!$isEdit && isset($_POST['add_contact'])) {
    $friendInput = trim((string)($_POST['friend'] ?? ''));
    $display     = trim((string)($_POST['display_name'] ?? ''));
    $returnTo    = trim((string)($_POST['return_to'] ?? $returnTo));

    if ($friendInput === '') {
        $error = "Enter a friend code (preferred) or username/email.";
    } else {
        $friend = findUserByAny($dbh, $friendInput);

        if (!$friend) {
            $error = "User not found. Check friend code / username / email.";
        } elseif ((int)($friend['status'] ?? 0) !== 1) {
            $error = "This user account is inactive.";
        } elseif ((int)($friend['id'] ?? 0) === $meId) {
            $error = "You cannot add yourself.";
        } elseif (contactExists($dbh, $meId, (int)$friend['id'])) {
            $error = "This contact is already in your list.";
        } else {
            // ✅ IMPORTANT: Do NOT auto-save "unknown" or real names.
            // User must enter a display_name manually.
            if ($display === '') {
                $error = "Please enter a name for this friend code before saving to Contacts.";
            } else {

                $ins = $dbh->prepare("
                    INSERT INTO user_contacts (owner_user_id, friend_user_id, display_name, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $ins->execute([$meId, (int)$friend['id'], $display]);

                // Redirect back to messages peer if requested
                if ($returnTo === 'messages') {
                    $peerCode = trim((string)($friend['friend_code'] ?? ''));
                    header('Location: messages.php?peer=' . urlencode($peerCode));
                    exit;
                }

                $msg = "Contact added successfully.";
                // clear fields after success
                $prefillFriend = '';
                $prefillDisplay = '';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/images/favicon.png" rel="icon" type="image/png">
    <title><?php echo $isEdit ? 'Edit Contact' : 'Add Contact'; ?></title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .box{background:#4b3d3d;border:1px solid #756060;border-radius:8px;padding:18px;box-shadow:0 4px 8px rgba(0,0,0,0.2);transition:.3s;margin-right:3%}
        .hint{color:#d5c2b0;;font-size:13px}
        .card{background-color:#3f3434}
        .bgtransparent{background-color:#3f3434}
        .page-title{margin-top:10%;margin-bottom:15px}
        .btn-btn-primary,.btn{display:inline-block;margin-bottom:0;font-weight:normal;text-align:center;vertical-align:middle;cursor:pointer;border:1px solid transparent;white-space:nowrap;padding:12px 16px;font-size:14px;line-height:1.42857143;border-radius:4px;user-select:none;background:#d5c2b0;;margin-top:15px}
        .readonly { opacity: .9; }
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

        <h2 class="page-title"><?php echo $isEdit ? 'Edit Contact' : 'Add Contact'; ?></h2>

        <div class="box">
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label class="hint">Friend Code (recommended)</label>

                    <input type="text" name="friend"
                           class="w-full !pl-10 !font-normal bgtransparent h-12 !text-sm card <?php echo $isEdit ? 'readonly' : ''; ?>"
                           placeholder="e.g. USR-XXXX-YYYY"
                           value="<?php echo h($prefillFriend); ?>"
                           <?php echo $isEdit ? 'readonly' : 'required'; ?>>

                    <input type="hidden" name="return_to" value="<?php echo h($returnTo); ?>">

                    <?php if ($isEdit): ?>
                        <input type="hidden" name="contact_id" value="<?php echo (int)$contactId; ?>">
                    <?php endif; ?>

                    <div class="hint" style="margin-top:8px;">
                        <?php if ($isEdit): ?>
                            You can only change the <b>Display Name</b>. Friend code cannot be changed here.
                        <?php else: ?>
                            Use their <b>friend code</b> (or username/email if needed).
                        <?php endif; ?>
                    </div>

                    <label style="margin-top:12px;display:block; color:#d5c2b0;">Display Name (required)</label>
                    <input type="text" name="display_name"
                           class="w-full !pl-10 !font-normal bgtransparent h-12 !text-sm card"
                           placeholder="e.g. John (Church friend)"
                           value="<?php echo h($isEdit ? $prefillDisplay : ''); ?>">

                    <div class="hint" style="margin-top:8px;">
                        We will NOT save anything until you type a name here. This keeps new friend codes "unknown" until you decide.
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <button class="btn-btn-primary" type="submit" name="save_contact">Save Changes</button>
                    <a class="btn btn-default" href="contacts.php" style="margin-left:8px;">Cancel</a>
                <?php else: ?>
                    <button class="btn-btn-primary" type="submit" name="add_contact">Add Contact</button>
                    <a class="btn btn-default" href="contacts.php" style="margin-left:8px;">View Contacts</a>
                <?php endif; ?>
            </form>
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
