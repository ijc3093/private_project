<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

/**
 * Small helpers
 */
if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fmt_time_short')) {
    function fmt_time_short(string $dt): string {
        if ($dt === '') return '';
        $ts = strtotime($dt);
        if (!$ts) return '';
        return date('h:i A', $ts);
    }
}
if (!function_exists('fmt_day_label')) {
    function fmt_day_label(string $dt): string {
        if ($dt === '') return '';
        $ts = strtotime($dt);
        if (!$ts) return '';
        $today = date('Y-m-d');
        $day   = date('Y-m-d', $ts);
        if ($day === $today) return 'Today';
        if ($day === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
        return date('M j, Y', $ts);
    }
}

/**
 * ✅ Session identity (username + email)
 * You said: everyone signs in with username and it must be unique.
 * session_user.php stores:
 *  - $_SESSION['user_login'] = username
 *  - $_SESSION['user_email'] = email
 */
$sessionUsername = function_exists('userUsername') ? userUsername() : trim((string)($_SESSION['user_login'] ?? ''));
$sessionEmail    = function_exists('userEmail') ? userEmail() : trim((string)($_SESSION['user_email'] ?? ''));

$sessionUsername = trim($sessionUsername);
$sessionEmail    = trim($sessionEmail);

if ($sessionUsername === '' && $sessionEmail === '') {
    header("Location: index.php?session=reset");
    exit;
}

/**
 * Resolve logged-in user from DB by username (preferred) then email fallback
 */
function getMe(PDO $dbh, string $username, string $email): array
{
    $me = false;

    if ($username !== '') {
        $st = $dbh->prepare("SELECT id, name, username, email, friend_code, status FROM users WHERE username = ? LIMIT 1");
        $st->execute([$username]);
        $me = $st->fetch(PDO::FETCH_ASSOC);
    }

    if (!$me && $email !== '') {
        $st = $dbh->prepare("SELECT id, name, username, email, friend_code, status FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $me = $st->fetch(PDO::FETCH_ASSOC);
    }

    if (!$me) return ['ok' => false, 'error' => 'not_found'];

    if ((int)($me['status'] ?? 1) !== 1) return ['ok' => false, 'error' => 'inactive'];

    $meId    = (int)($me['id'] ?? 0);
    $meEmail = trim((string)($me['email'] ?? ''));
    $meCode  = trim((string)($me['friend_code'] ?? ''));

    if ($meId <= 0 || $meCode === '') return ['ok' => false, 'error' => 'missing_id_or_code'];

    return [
        'ok'        => true,
        'me'        => $me,
        'meId'      => $meId,
        'meEmail'   => $meEmail,
        'meCode'    => $meCode,
        'meDisplay' => (string)($me['name'] ?? $me['username'] ?? $meCode),
    ];
}

$meRes = getMe($dbh, $sessionUsername, $sessionEmail);
if (!$meRes['ok']) {
    // if your session points to a user that doesn't exist, treat as invalid session
    header("Location: index.php?session=reset");
    exit;
}

$meId    = (int)$meRes['meId'];
$meEmail = (string)$meRes['meEmail'];
$meCode  = (string)$meRes['meCode'];

/**
 * Resolve peer by:
 * - friend_code (preferred)
 * - email
 * - username
 */
function resolvePeer(PDO $dbh, string $peerRaw): array
{
    $peerRaw = trim($peerRaw);
    if ($peerRaw === '') return ['ok' => false];

    // email
    if (strpos($peerRaw, '@') !== false) {
        $st = $dbh->prepare("SELECT id, name, username, email, friend_code, status FROM users WHERE email = ? LIMIT 1");
        $st->execute([$peerRaw]);
    }
    // friend_code (USR-xxxx-xxxx usually)
    elseif (preg_match('/^[A-Z]{3}-/i', $peerRaw)) {
        $st = $dbh->prepare("SELECT id, name, username, email, friend_code, status FROM users WHERE friend_code = ? LIMIT 1");
        $st->execute([$peerRaw]);
    }
    // username
    else {
        $st = $dbh->prepare("SELECT id, name, username, email, friend_code, status FROM users WHERE username = ? LIMIT 1");
        $st->execute([$peerRaw]);
    }

    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) return ['ok' => false];

    if ((int)($u['status'] ?? 1) !== 1) return ['ok' => false]; // inactive

    $display = trim((string)($u['name'] ?? ''));
    if ($display === '') $display = trim((string)($u['username'] ?? ''));
    if ($display === '') $display = trim((string)($u['friend_code'] ?? ''));

    return [
        'ok'          => true,
        'peer'        => $u,
        'peerId'      => (int)($u['id'] ?? 0),
        'peerEmail'   => (string)($u['email'] ?? ''),
        'peerCode'    => (string)($u['friend_code'] ?? ''),
        'peerDisplay' => $display,
    ];
}

/**
 * ✅ THREAD LIST (NO DUPLICATES)
 * One user = one row, guaranteed by selecting FROM users and using EXISTS.
 * Works with old feedback rows (email) and new rows (friend_code).
 */
function listThreads(PDO $dbh, int $meId, string $meCode, string $meEmail): array
{
    $sql = "
        SELECT
            u.id AS peer_id,
            u.friend_code AS peer_key,
            COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), u.friend_code) AS peer_display,

            /* last message text */
            (
                SELECT f2.feedbackdata
                FROM feedback f2
                WHERE f2.channel = 'user_user'
                  AND (
                        (f2.sender IN (?, ?) AND f2.receiver IN (u.friend_code, u.email))
                     OR (f2.receiver IN (?, ?) AND f2.sender IN (u.friend_code, u.email))
                  )
                ORDER BY f2.created_at DESC, f2.id DESC
                LIMIT 1
            ) AS last_message,

            /* last time */
            (
                SELECT f2.created_at
                FROM feedback f2
                WHERE f2.channel = 'user_user'
                  AND (
                        (f2.sender IN (?, ?) AND f2.receiver IN (u.friend_code, u.email))
                     OR (f2.receiver IN (?, ?) AND f2.sender IN (u.friend_code, u.email))
                  )
                ORDER BY f2.created_at DESC, f2.id DESC
                LIMIT 1
            ) AS last_time,

            /* unread count from that peer to me */
            (
                SELECT COUNT(*)
                FROM feedback f3
                WHERE f3.channel = 'user_user'
                  AND f3.is_read = 0
                  AND f3.receiver IN (?, ?)
                  AND f3.sender IN (u.friend_code, u.email)
            ) AS unread_count

        FROM users u
        WHERE u.status = 1
          AND u.id <> ?
          AND EXISTS (
                SELECT 1
                FROM feedback f
                WHERE f.channel = 'user_user'
                  AND (
                        (f.sender IN (?, ?) AND f.receiver IN (u.friend_code, u.email))
                     OR (f.receiver IN (?, ?) AND f.sender IN (u.friend_code, u.email))
                  )
          )
        ORDER BY last_time DESC
    ";

    // placeholders: keep same pairs repeated (meCode, meEmail)
    $params = [
        $meCode, $meEmail,  $meCode, $meEmail,   // last_message
        $meCode, $meEmail,  $meCode, $meEmail,   // last_time
        $meCode, $meEmail,                    // unread (receiver in me)
        $meId,                                // u.id <> meId
        $meCode, $meEmail,  $meCode, $meEmail,   // EXISTS conversation
    ];

    try {
        $st = $dbh->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Optional safety: ensure unique by peer_id even if DB has weird duplicates
        $unique = [];
        foreach ($rows as $r) {
            $k = (string)($r['peer_id'] ?? '');
            if ($k === '') continue;
            if (!isset($unique[$k]) || (string)$r['last_time'] > (string)$unique[$k]['last_time']) {
                $unique[$k] = $r;
            }
        }
        return array_values($unique);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Load history conversation:
 * Pulls both old email-based rows + new friend_code rows.
 */
function loadHistory(PDO $dbh, string $meCode, string $meEmail, string $peerCode, string $peerEmail): array
{
    $sql = "
        SELECT id, sender, receiver, feedbackdata, attachment, created_at
        FROM feedback
        WHERE channel = 'user_user'
          AND (
                (sender IN (?, ?) AND receiver IN (?, ?))
             OR (sender IN (?, ?) AND receiver IN (?, ?))
          )
        ORDER BY created_at ASC, id ASC
    ";

    $params = [
        $meCode, $meEmail, $peerCode, $peerEmail,
        $peerCode, $peerEmail, $meCode, $meEmail
    ];

    try {
        $st = $dbh->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Mark messages as read when opening chat
 */
function markRead(PDO $dbh, string $meCode, string $meEmail, string $peerCode, string $peerEmail): void
{
    $sql = "
        UPDATE feedback
        SET is_read = 1, read_at = NOW()
        WHERE channel = 'user_user'
          AND receiver IN (?, ?)
          AND sender IN (?, ?)
          AND is_read = 0
    ";
    try {
        $st = $dbh->prepare($sql);
        $st->execute([$meCode, $meEmail, $peerCode, $peerEmail]);
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Insert message:
 * ✅ ALWAYS store friend_code (your requirement)
 */
function insertMessage(PDO $dbh, string $meCode, string $peerCode, string $text, ?string $attachmentName): void
{
    $sql = "
        INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read, created_at)
        VALUES (?, ?, 'user_user', ?, ?, ?, 0, NOW())
    ";
    $title = ''; // required column (NOT NULL)
    $st = $dbh->prepare($sql);
    $st->execute([$meCode, $peerCode, $title, $text, $attachmentName]);
}

/**
 * Selected peer from URL
 */
$peerRaw     = trim((string)($_GET['peer'] ?? ''));
$peerRow     = null;
$peerCode    = '';
$peerEmail   = '';
$peerDisplay = '';

if ($peerRaw !== '') {
    $pr = resolvePeer($dbh, $peerRaw);
    if ($pr['ok']) {
        $peerRow     = $pr['peer'];
        $peerCode    = (string)$pr['peerCode'];
        $peerEmail   = (string)$pr['peerEmail'];
        $peerDisplay = (string)$pr['peerDisplay'];

        // prevent chatting with yourself
        if ($peerCode === $meCode || ($peerEmail !== '' && $peerEmail === $meEmail)) {
            $peerRow = null;
            $peerCode = '';
            $peerEmail = '';
            $peerDisplay = '';
        }
    }
}

/**
 * POST send
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peerRow && $peerCode !== '') {
    $text = trim((string)($_POST['message'] ?? ''));
    $attachmentName = null;

    // optional attachment (kept simple)
    if (!empty($_FILES['attachment']['name'] ?? '')) {
        $tmp  = (string)($_FILES['attachment']['tmp_name'] ?? '');
        $name = basename((string)($_FILES['attachment']['name'] ?? ''));
        if ($tmp && is_uploaded_file($tmp)) {
            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            $dir = __DIR__ . '/attachment';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $path = $dir . '/' . $safe;
            if (move_uploaded_file($tmp, $path)) {
                $attachmentName = $safe;
            }
        }
    }

    if ($text !== '' || $attachmentName) {
        insertMessage($dbh, $meCode, $peerCode, $text, $attachmentName);
    }

    // keep same filename casing you use in your project
    header("Location: messages.php?peer=" . urlencode($peerCode));
    exit;
}

/**
 * Threads + history
 */
$threads = listThreads($dbh, $meId, $meCode, $meEmail);

$messages = [];
if ($peerRow && $peerCode !== '') {
    markRead($dbh, $meCode, $meEmail, $peerCode, $peerEmail);
    $messages = loadHistory($dbh, $meCode, $meEmail, $peerCode, $peerEmail);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/images/favicon.png" rel="icon" type="image/png">
    <title>Messages</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<style>
    @media (min-width: 1280px) {
  .xl\:ml-\[--w-side-m\] {
    /* margin-left: var(--w-side-sm); */
  }

  .xl\:hidden {
    display: none;
  }

  .xl\:w-\[680px\] {
    width: 680px;
  }

  .xl\:w-\[694px\] {
    width: 694px;
  }

  .xl\:space-y-6 > :not([hidden]) ~ :not([hidden]) {
    --tw-space-y-reverse: 0;
    margin-top: calc(1.5rem * calc(1 - var(--tw-space-y-reverse)));
    margin-bottom: calc(1.5rem * var(--tw-space-y-reverse));
  }

  .xl\:px-6 {
    padding-left: 1.5rem;
    padding-right: 1.5rem;
  }

  .xl\:duration-500 {
    transition-duration: 500ms;
  }
}
</style>

<body class="bg-white darkd">
<div id="wrapper">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="site__main" class="2xl:ml-[--w-side]  xl:ml-[--w-side-m] p-2.5 h-[calc(100vh-var(--m-top))] mt-[--m-top]">

        <div class="relative overflow-hidden border -m-2.5 dark:border-slate-700">
            <div class="flex bg-white dark:bg-dark2">

                <!-- sidebar -->
                <div class="md:w-[360px] relative border-r dark:border-slate-700">
                    <div id="side-chat" class="top-0 left-0 max-md:fixed max-md:w-5/6 max-md:h-screen bg-white z-50 max-md:shadow max-md:-translate-x-full dark:bg-dark2">

                    <!-- heading title -->
                            <div class="p-4 border-b dark:border-slate-700">
                                
                                <div class="flex mt-2 items-center justify-between">
    
                                    <h2 class="text-2xl font-bold text-black ml-1 dark:text-white"> Chats </h1>
                                          
                                    <!-- right action buttons -->
                                    <div class="flex items-center gap-2.5">
                                        <button class="group">
                                            <ion-icon name="settings-outline" class="text-2xl flex group-aria-expanded:rotate-180"></ion-icon>
                                        </button>
                                        <div  class="md:w-[270px] w-full" uk-dropdown="pos: bottom-left; offset:10; animation: uk-animation-slide-bottom-small"> 
                                            <nav>
                                                <a href="#"> <ion-icon class="text-2xl shrink-0 -ml-1" name="checkmark-outline"></ion-icon> Mark all as read </a> 
                                                <a href="#"> <ion-icon class="text-2xl shrink-0 -ml-1" name="checkmark-outline"></ion-icon> Delete all </a>  
                                                <a href="#"> <ion-icon class="text-2xl shrink-0 -ml-1" name="notifications-outline"></ion-icon> notifications setting </a>  
                                                <a href="#"> <ion-icon class="text-xl shrink-0 -ml-1" name="volume-mute-outline"></ion-icon> Mute notifications </a>     
                                            </nav>
                                        </div>
                                        
                                        <button class="">
                                            <ion-icon name="checkmark-circle-outline" class="text-2xl flex"></ion-icon>
                                        </button>
    
                                        <!-- mobile toggle menu -->
                                        <button type="button" class="md:hidden" uk-toggle="target: #side-chat ; cls: max-md:-translate-x-full">
                                            <ion-icon name="chevron-down-outline"></ion-icon>
                                        </button>
    
                                    </div>
    
                                </div>

                            <div class="relative mt-4">
                                <div class="absolute left-3 bottom-1/2 translate-y-1/2 flex">
                                    <ion-icon name="search" class="text-xl"></ion-icon>
                                </div>
                                <input type="text" placeholder="Search" class="w-full !pl-10 !py-2 !rounded-lg">
                            </div>
                        </div>

                        <!-- users list -->
                        <div class="space-y-2 p-2 overflow-y-auto md:h-[calc(100vh-204px)] h-[calc(100vh-130px)]">
                                <?php foreach ($threads as $t): ?>
                                <?php
                                    $peerKey     = (string)($t['peer_key'] ?? '');
                                    $peerDisplay = (string)($t['peer_display'] ?? $peerKey);
                                    $lastMsg     = (string)($t['last_message'] ?? '');
                                    $lastTime    = (string)($t['last_time'] ?? '');
                                    $unread      = (int)($t['unread_count'] ?? 0);
                                    $href = "messages.php?peer=" . urlencode($peerKey);
                                ?>
                                <a href="<?php echo h($href); ?>" class="relative flex items-center gap-4 p-2 duration-200 rounded-xl hover:bg-secondery">
                                    <div class="relative w-14 h-14 shrink-0">
                                        <img src="assets/images/avatars/avatar-5.jpg" alt="" class="object-cover w-full h-full rounded-full">
                                        <div class="w-4 h-4 absolute bottom-0 right-0 bg-green-500 rounded-full border border-white dark:border-slate-800"></div>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <div class="mr-auto text-sm text-black dark:text-white font-medium">
                                                <?php echo h($peerDisplay); ?>
                                                <div class="text-xs text-gray-500"><?php echo h($peerKey); ?></div>
                                            </div>

                                            <div class="text-xs font-light text-gray-500 dark:text-white/70">
                                                <?php echo h(fmt_time_short($lastTime)); ?>
                                            </div>

                                            <?php if ($unread > 0): ?>
                                                <div class="w-2.5 h-2.5 bg-blue-600 rounded-full dark:bg-slate-700"></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="font-medium overflow-hidden text-ellipsis text-sm whitespace-nowrap">
                                            <?php echo h($lastMsg); ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>

                            <?php if (empty($threads)): ?>
                                <div class="p-4 text-sm text-gray-500">No chats yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- message center -->
                <div class="flex-1">

                    <!-- chat heading -->
                    <div class="flex items-center justify-between gap-2 px-6 py-3.5 z-10 border-b dark:border-slate-700 uk-animation-slide-top-medium">
                        <div class="flex items-center sm:gap-4 gap-2">
                            <button type="button" class="md:hidden" uk-toggle="target: #side-chat ; cls: max-md:-translate-x-full">
                                <ion-icon name="chevron-back-outline" class="text-2xl -ml-4"></ion-icon>
                            </button>

                            <div class="cursor-pointer">
                                <div class="text-base font-bold">
                                    <?php echo h($peerRow ? $peerDisplay : 'Select a chat'); ?>
                                </div>
                                <div class="text-xs text-green-500 font-semibold">
                                    <?php echo $peerRow ? h($peerCode) : ''; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- chats bubble box -->
                    <div class="w-full p-5 py-10 overflow-y-auto md:h-[calc(100vh-204px)] h-[calc(100vh-195px)]">

                        <?php if (!$peerRow): ?>
                            <div class="py-10 text-center text-sm lg:pt-8">
                                <div class="mt-8">
                                    <div class="md:text-xl text-base font-medium text-black dark:text-white">Select a chat</div>
                                    <div class="text-gray-500 text-sm dark:text-white/80">Choose a user from the left to start messaging.</div>
                                </div>
                            </div>
                        <?php else: ?>

                            <div class="text-sm font-medium space-y-6">
                                <?php
                                    $lastDay = '';
                                    foreach ($messages as $m):
                                        $sender = (string)($m['sender'] ?? '');
                                        $dt     = (string)($m['created_at'] ?? '');
                                        $isMe   = (strcasecmp($sender, $meCode) === 0) || (strcasecmp($sender, $meEmail) === 0);

                                        $day  = $dt ? date('Y-m-d', strtotime($dt)) : '';
                                        if ($day !== '' && $day !== $lastDay):
                                            $lastDay = $day;
                                ?>
                                    <div class="flex justify-center">
                                        <div class="font-medium text-gray-500 text-sm dark:text-white/70">
                                            <?php echo h(fmt_day_label($dt)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$isMe): ?>
                                    <!-- received -->
                                    <div class="flex gap-3">
                                        <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-9 h-9 rounded-full shadow">
                                        <div class="px-4 py-2 rounded-[20px] max-w-sm bg-secondery">
                                            <?php echo nl2br(h((string)($m['feedbackdata'] ?? ''))); ?>
                                            <?php if (!empty($m['attachment'])): ?>
                                                <div class="mt-2 text-xs">
                                                    <a class="underline" target="_blank" href="attachment/<?php echo urlencode((string)$m['attachment']); ?>">
                                                        <?php echo h((string)$m['attachment']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- sent -->
                                    <div class="flex gap-2 flex-row-reverse items-end">
                                        <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-5 h-5 rounded-full shadow">
                                        <div class="px-4 py-2 rounded-[20px] max-w-sm bg-gradient-to-tr from-sky-500 to-blue-500 text-white shadow">
                                            <?php echo nl2br(h((string)($m['feedbackdata'] ?? ''))); ?>
                                            <?php if (!empty($m['attachment'])): ?>
                                                <div class="mt-2 text-xs">
                                                    <a class="underline" target="_blank" href="attachment/<?php echo urlencode((string)$m['attachment']); ?>">
                                                        <?php echo h((string)$m['attachment']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php endforeach; ?>

                                <?php if (empty($messages)): ?>
                                    <div class="p-4 text-sm text-gray-500">No messages yet.</div>
                                <?php endif; ?>
                            </div>

                        <?php endif; ?>
                    </div>

                    <!-- sending message area -->
                    <div class="flex items-center md:gap-4 gap-2 md:p-3 p-2 overflow-hidden">
<div id="message__wrap" class="flex items-center gap-2 h-full dark:text-white -mt-1.5">
                            <button type="button"  class="shrink-0">
                                <ion-icon class="text-3xl flex" name="add-circle-outline"></ion-icon>
                            </button>
                            <div class="dropbar pt-36 h-60 bg-gradient-to-t via-white from-white via-30% from-30% dark:from-slate-900 dark:via-900" uk-drop="stretch: x; target: #message__wrap ;animation:  slide-bottom ;animate-out: true; pos: top-left; offset:10 ; mode: click ; duration: 200">
                                <div class="sm:w-full p-3 flex justify-center gap-5" uk-scrollspy="target: > button; cls: uk-animation-slide-bottom-small; delay: 100;repeat:true">
                                    <button type="button" class="bg-sky-50 text-sky-600 border border-sky-100 shadow-sm p-2.5 rounded-full shrink-0 duration-100 hover:scale-[1.15] dark:bg-dark3 dark:border-0">
                                        <ion-icon class="text-3xl flex" name="image"></ion-icon>
                                    </button>
                                    <button type="button" class="bg-green-50 text-green-600 border border-green-100 shadow-sm p-2.5 rounded-full shrink-0 duration-100 hover:scale-[1.15] dark:bg-dark3 dark:border-0">
                                        <ion-icon class="text-3xl flex" name="images"></ion-icon>
                                    </button>
                                    <button type="button" class="bg-pink-50 text-pink-600 border border-pink-100 shadow-sm p-2.5 rounded-full shrink-0 duration-100 hover:scale-[1.15] dark:bg-dark3 dark:border-0">
                                        <ion-icon class="text-3xl flex" name="document-text"></ion-icon>
                                    </button>
                                    <button type="button" class="bg-orange-50 text-orange-600 border border-orange-100 shadow-sm p-2.5 rounded-full shrink-0 duration-100 hover:scale-[1.15] dark:bg-dark3 dark:border-0">
                                        <ion-icon class="text-3xl flex" name="gift"></ion-icon>
                                    </button>
                                </div>
                            </div>

                            <button type="button"  class="shrink-0">
                                <ion-icon class="text-3xl flex" name="happy-outline"></ion-icon>
                            </button>
                            <div class="dropbar p-2" uk-drop="stretch: x; target: #message__wrap ;animation: uk-animation-scale-up uk-transform-origin-bottom-left ;animate-out: true; pos: top-left ; offset:2; mode: click ; duration: 200 ">
                                <div class="sm:w-60 bg-white shadow-lg border rounded-xl  pr-0 dark:border-slate-700 dark:bg-dark3">
                                    <h4 class="text-sm font-semibold p-3 pb-0">Send Imogi</h4>
                                    <div class="grid grid-cols-5 overflow-y-auto max-h-44 p-3 text-center text-xl">
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😊 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🤩 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😎</div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🥳 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😂 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🥰 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😡 </div> 
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😊 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🤩 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😎</div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🥳 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😂 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🥰 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😡 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🤔 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😊 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🤩 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😎</div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🥳 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😂 </div>
                                    </div>
                                </div>
                            </div>
    
                            </div>
                        <div class="relative flex-1">
                            <?php if ($peerRow): ?>
                                <form method="POST" enctype="multipart/form-data" class="flex items-center md:gap-4 gap-2 md:p-3 p-2 overflow-hidden">
                                    <div class="relative flex-1">
                                        <textarea name="message" placeholder="Write your message" rows="1"
                                                  class="w-full resize-none bg-secondery rounded-full px-4 p-2"></textarea>

                                        <button type="submit" class="text-white shrink-0 p-2 absolute right-0.5 top-0">
                                            <ion-icon class="text-xl flex" name="send-outline"></ion-icon>
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="flex h-full dark:text-white">
                            <ion-icon class="text-3xl flex -mt-3" name="heart-outline"></ion-icon>
                        </button>

                    </div>

                </div>

            </div>
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
