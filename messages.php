<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

function fmt_time_short(string $dt): string {
    if ($dt === '') return '';
    $ts = strtotime($dt);
    return $ts ? date('h:i A', $ts) : '';
}

function fmt_time_full(string $dt): string {
    if ($dt === '') return '';
    $ts = strtotime($dt);
    return $ts ? date('M d, Y h:i A', $ts) : '';
}

function day_key(string $dt): string {
    $ts = strtotime($dt);
    return $ts ? date('Y-m-d', $ts) : '';
}

function day_label(string $dt): string {
    $ts = strtotime($dt);
    if (!$ts) return '';
    $today = date('Y-m-d');
    $d = date('Y-m-d', $ts);
    if ($d === $today) return 'Today';
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
    return date('M j, Y', $ts);
}

/** session identity */
$meCode  = strtoupper(trim((string)userFriendCode()));
$meEmail = trim((string)userEmail());
$meName  = trim((string)myUserName());

if ($meCode === '' && $meEmail === '') {
    header("Location: index.php?session=reset");
    exit;
}

$meDisplay = $meName !== '' ? $meName : 'You';

/** Resolve peer */
function resolvePeerByCode(PDO $dbh, string $peerCode): array {
    $peerCode = strtoupper(trim($peerCode));
    if ($peerCode === '') return ['ok'=>false];

    $st = $dbh->prepare("
        SELECT id, name, username, email, friend_code, status,
               COALESCE(NULLIF(name,''), NULLIF(username,''), friend_code) AS display
        FROM users
        WHERE UPPER(friend_code)=:c
        LIMIT 1
    ");
    $st->execute([':c'=>$peerCode]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) return ['ok'=>false];
    if ((int)($u['status'] ?? 1) !== 1) return ['ok'=>false];

    return [
        'ok'=>true,
        'peer'=>$u,
        'peerEmail'=>(string)$u['email'],
        'peerCode'=>(string)$u['friend_code'],
        'peerDisplay'=>(string)$u['display'],
    ];
}

/** Thread list */
function listThreads(PDO $dbh, string $meCode, string $meEmail): array {
    $sql = "
        SELECT
            u.id AS peer_id,
            u.friend_code AS peer_key,
            COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), u.friend_code) AS peer_display,

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
          AND u.friend_code <> ?
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

    $params = [
        $meCode, $meEmail,  $meCode, $meEmail,
        $meCode, $meEmail,  $meCode, $meEmail,
        $meCode, $meEmail,
        $meCode,
        $meCode, $meEmail,  $meCode, $meEmail,
    ];

    try {
        $st = $dbh->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Load history (include is_read for ticks) */
function loadHistory(PDO $dbh, string $meCode, string $meEmail, string $peerCode, string $peerEmail): array {
    $sql = "
        SELECT id, sender, receiver, feedbackdata, attachment, created_at, is_read
        FROM feedback
        WHERE channel = 'user_user'
          AND (
                (sender IN (?, ?) AND receiver IN (?, ?))
             OR (sender IN (?, ?) AND receiver IN (?, ?))
          )
        ORDER BY created_at ASC, id ASC
        LIMIT 1000
    ";
    $params = [$meCode,$meEmail,$peerCode,$peerEmail, $peerCode,$peerEmail,$meCode,$meEmail];
    try {
        $st = $dbh->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Mark read */
function markRead(PDO $dbh, string $meCode, string $meEmail, string $peerCode, string $peerEmail): void {
    try {
        $st = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE channel='user_user'
              AND receiver IN (?, ?)
              AND sender IN (?, ?)
              AND is_read = 0
        ");
        $st->execute([$meCode,$meEmail,$peerCode,$peerEmail]);
    } catch (Throwable $e) {}
}

/** Insert message */
function insertMessage(PDO $dbh, string $meCode, string $peerCode, string $text, ?string $attachmentName): void {
    $st = $dbh->prepare("
        INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read, created_at)
        VALUES (?, ?, 'user_user', ?, ?, ?, 0, NOW())
    ");
    $title = '';
    $st->execute([$meCode, $peerCode, $title, $text, $attachmentName]);
}

/** selected peer */
$peerRaw = strtoupper(trim((string)($_GET['peer'] ?? '')));
$peerRow = null;
$peerCode = '';
$peerEmail = '';
$peerDisplay = '';

if ($peerRaw !== '') {
    $pr = resolvePeerByCode($dbh, $peerRaw);
    if (!empty($pr['ok'])) {
        $peerRow     = $pr['peer'];
        $peerCode    = (string)$pr['peerCode'];
        $peerEmail   = (string)$pr['peerEmail'];
        $peerDisplay = (string)$pr['peerDisplay'];

        if ($peerCode === $meCode) {
            $peerRow = null; $peerCode=''; $peerEmail=''; $peerDisplay='';
        }
    }
}

/** send */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peerRow && $peerCode !== '') {
    $text = trim((string)($_POST['message'] ?? ''));
    $attachmentName = null;

    if (!empty($_FILES['attachment']['name'] ?? '')) {
        $tmp  = (string)($_FILES['attachment']['tmp_name'] ?? '');
        $name = basename((string)($_FILES['attachment']['name'] ?? ''));
        if ($tmp && is_uploaded_file($tmp)) {
            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            $dir = __DIR__ . '/attachment';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $path = $dir . '/' . $safe;
            if (move_uploaded_file($tmp, $path)) $attachmentName = $safe;
        }
    }

    if ($text !== '' || $attachmentName) {
        insertMessage($dbh, $meCode, $peerCode, $text, $attachmentName);
    }

    header("Location: messages.php?peer=" . urlencode($peerCode));
    exit;
}

$threads = listThreads($dbh, $meCode, $meEmail);

$messages = [];
$lastId = 0;
if ($peerRow && $peerCode !== '') {
    markRead($dbh, $meCode, $meEmail, $peerCode, $peerEmail);
    $messages = loadHistory($dbh, $meCode, $meEmail, $peerCode, $peerEmail);
    if (!empty($messages)) {
        $last = end($messages);
        $lastId = (int)($last['id'] ?? 0);
        reset($messages);
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
    <title>Messages</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="bg-white darkd">
<div id="wrapper">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main id="site__main" class="2xl:ml-[--w-side] xl:ml-[--w-side-m] p-2.5 h-[calc(100vh-var(--m-top))] mt-[--m-top]">
        <div class="relative overflow-hidden border -m-2.5 dark:border-slate-700">
            <div class="flex bg-white dark:bg-dark2">

                <!-- sidebar -->
                <div class="md:w-[360px] relative border-r dark:border-slate-700">
                    <div id="side-chat" class="top-0 left-0 max-md:fixed max-md:w-5/6 max-md:h-screen bg-white z-50 max-md:shadow max-md:-translate-x-full dark:bg-dark2">

                        <!-- heading title -->
                        <div class="p-4 border-b dark:border-slate-700">
                            <div class="flex mt-2 items-center justify-between">
                                <h2 class="text-2xl font-bold text-black ml-1 dark:text-white"> Chats </h2>

                                <div class="flex items-center gap-2.5">
                                    <button class="group">
                                        <ion-icon name="settings-outline" class="text-2xl flex group-aria-expanded:rotate-180"></ion-icon>
                                    </button>
                                    <div class="md:w-[270px] w-full" uk-dropdown="pos: bottom-left; offset:10; animation: uk-animation-slide-bottom-small">
                                        <nav>
                                            <a href="#"><ion-icon class="text-2xl shrink-0 -ml-1" name="checkmark-outline"></ion-icon> Mark all as read </a>
                                            <a href="#"><ion-icon class="text-2xl shrink-0 -ml-1" name="notifications-outline"></ion-icon> notifications setting </a>
                                            <a href="#"><ion-icon class="text-xl shrink-0 -ml-1" name="volume-mute-outline"></ion-icon> Mute notifications </a>
                                        </nav>
                                    </div>

                                    <button class="">
                                        <ion-icon name="checkmark-circle-outline" class="text-2xl flex"></ion-icon>
                                    </button>

                                    <button type="button" class="md:hidden" uk-toggle="target: #side-chat ; cls: max-md:-translate-x-full">
                                        <ion-icon name="chevron-down-outline"></ion-icon>
                                    </button>
                                </div>
                            </div>

                            <div class="relative mt-4">
                                <div class="absolute left-3 bottom-1/2 translate-y-1/2 flex"><ion-icon name="search" class="text-xl"></ion-icon></div>
                                <input type="text" placeholder="Search" class="w-full !pl-10 !py-2 !rounded-lg">
                            </div>
                        </div>

                        <div class="space-y-2 p-2 overflow-y-auto md:h-[calc(100vh-204px)] h-[calc(100vh-130px)]">
                            <?php foreach ($threads as $t): ?>
                                <?php
                                    $peerKey     = (string)($t['peer_key'] ?? '');
                                    $pDisp       = (string)($t['peer_display'] ?? $peerKey);
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
                                                <?php echo h($pDisp); ?>
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
                    <div class="flex items-center justify-between gap-2 w- px-6 py-3.5 z-10 border-b dark:border-slate-700 uk-animation-slide-top-medium">
                        <div class="flex items-center sm:gap-4 gap-2">
                            <button type="button" class="md:hidden" uk-toggle="target: #side-chat ; cls: max-md:-translate-x-full">
                                <ion-icon name="chevron-back-outline" class="text-2xl -ml-4"></ion-icon>
                            </button>

                            <div class="relative cursor-pointer max-md:hidden" uk-toggle="target: .rightt ; cls: hidden">
                                <img src="assets/images/avatars/avatar-6.jpg" alt="" class="w-8 h-8 rounded-full shadow">
                                <div class="w-2 h-2 bg-teal-500 rounded-full absolute right-0 bottom-0 m-px"></div>
                            </div>

                            <div class="cursor-pointer" uk-toggle="target: .rightt ; cls: hidden">
                                <div class="text-base font-bold" id="peerTitle">
                                    <?php echo h($peerRow ? $peerDisplay : 'Select a chat'); ?>
                                </div>
                                <div class="text-xs text-green-500 font-semibold">
                                    <?php echo $peerRow ? h($peerCode) : ''; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" class="button__ico">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-6 h-6">
                                    <path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 013.5 2h1.148a1.5 1.5 0 011.465 1.175l.716 3.223a1.5 1.5 0 01-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 006.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 011.767-1.052l3.223.716A1.5 1.5 0 0118 15.352V16.5a1.5 1.5 0 01-1.5 1.5H15c-1.149 0-2.263-.15-3.326-.43A13.022 13.022 0 012.43 8.326 13.019 13.019 0 012 5V3.5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button type="button" class="hover:bg-slate-100 p-1.5 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </button>
                            <button type="button" class="hover:bg-slate-100 p-1.5 rounded-full" uk-toggle="target: .rightt ; cls: hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div id="chatBox" class="w-full p-5 py-10 overflow-y-auto md:h-[calc(100vh-204px)] h-[calc(100vh-195px)]">
                        <?php if (!$peerRow): ?>
                            <div class="py-10 text-center text-sm lg:pt-8">
                                <div class="mt-8">
                                    <div class="md:text-xl text-base font-medium text-black dark:text-white">Select a chat</div>
                                    <div class="text-gray-500 text-sm dark:text-white/80">Choose a user from the left to start messaging.</div>
                                </div>
                            </div>
                        <?php else: ?>

                            <div id="chatStream" class="text-sm font-medium space-y-6">
                                <?php
                                    $lastDay = '';
                                    foreach ($messages as $m):
                                        $id     = (int)($m['id'] ?? 0);
                                        $sender = (string)($m['sender'] ?? '');
                                        $dt     = (string)($m['created_at'] ?? '');
                                        $isRead = (int)($m['is_read'] ?? 0);
                                        $isMe   = (strcasecmp($sender, $meCode) === 0) || ($meEmail !== '' && strcasecmp($sender, $meEmail) === 0);

                                        $dk = $dt ? day_key($dt) : '';
                                        if ($dk !== '' && $dk !== $lastDay):
                                            $lastDay = $dk;
                                ?>
                                    <div class="flex justify-center dayDivider" data-day="<?php echo h($dk); ?>">
                                        <div class="font-medium text-gray-500 text-sm dark:text-white/70">
                                            <?php echo h(day_label($dt)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$isMe): ?>
                                    <div class="flex gap-3 msgRow" data-id="<?php echo (int)$id; ?>" data-day="<?php echo h($dk); ?>">
                                        <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-9 h-9 rounded-full shadow">
                                        <div>
                                            <div class="px-4 py-2 rounded-[20px] max-w-sm bg-secondery">
                                                <?php echo nl2br(h((string)($m['feedbackdata'] ?? ''))); ?>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500">
                                                <?php echo h($peerDisplay); ?> • <?php echo h(fmt_time_full($dt)); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="flex gap-2 flex-row-reverse items-end msgRow" data-id="<?php echo (int)$id; ?>" data-day="<?php echo h($dk); ?>">
                                        <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-5 h-5 rounded-full shadow">
                                        <div class="text-right">
                                            <div class="px-4 py-2 rounded-[20px] max-w-sm bg-gradient-to-tr from-sky-500 to-blue-500 text-white shadow">
                                                <?php echo nl2br(h((string)($m['feedbackdata'] ?? ''))); ?>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-400">
                                                <?php echo h($meDisplay); ?> • <?php echo h(fmt_time_full($dt)); ?>
                                                <span class="ml-1 msgTicks" data-msgid="<?php echo (int)$id; ?>"><?php echo $isRead ? '✓✓' : '✓'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    </div>

                    <div id="typingIndicator" class="hidden text-xs text-gray-500 px-6 pb-2"></div>

                    <?php if ($peerRow): ?>
                    <div class="flex items-center md:gap-4 gap-2 md:p-3 p-2 overflow-hidden">

                        <div id="message__wrap" class="flex items-center gap-2 h-full dark:text-white -mt-1.5">
                            <button type="button" class="shrink-0">
                                <ion-icon class="text-3xl flex" name="add-circle-outline"></ion-icon>
                            </button>

                            <div class="dropbar pt-36 h-60 bg-gradient-to-t via-white from-white via-30% from-30% dark:from-slate-900 dark:via-900"
                                 uk-drop="stretch: x; target: #message__wrap ;animation:  slide-bottom ;animate-out: true; pos: top-left; offset:10 ; mode: click ; duration: 200">
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

                            <button type="button" class="shrink-0">
                                <ion-icon class="text-3xl flex" name="happy-outline"></ion-icon>
                            </button>

                            <div class="dropbar p-2" uk-drop="stretch: x; target: #message__wrap ;animation: uk-animation-scale-up uk-transform-origin-bottom-left ;animate-out: true; pos: top-left ; offset:2; mode: click ; duration: 200 ">
                                <div class="sm:w-60 bg-white shadow-lg border rounded-xl pr-0 dark:border-slate-700 dark:bg-dark3">
                                    <h4 class="text-sm font-semibold p-3 pb-0">Send Imogi</h4>
                                    <div class="grid grid-cols-5 overflow-y-auto max-h-44 p-3 text-center text-xl">
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😊 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🤩 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😎 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🥳 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😂 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🥰 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 😡 </div>
                                        <div class="hover:bg-secondery p-1.5 rounded-md hover:scale-125 cursor-pointer duration-200"> 🤔 </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="relative flex-1">
                            <form id="sendForm" method="POST" enctype="multipart/form-data" class="flex items-center md:gap-4 gap-2 md:p-3 p-2 overflow-hidden">
                                <div class="relative flex-1">
                                    <textarea id="messageInput" name="message" placeholder="Write your message" rows="1"
                                              class="w-full resize-none bg-secondery rounded-full px-4 p-2"></textarea>

                                    <button type="submit" class="text-white shrink-0 p-2 absolute right-0.5 top-0">
                                        <ion-icon class="text-xl flex" name="send-outline"></ion-icon>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <button type="button" class="flex h-full dark:text-white">
                            <ion-icon class="text-3xl flex -mt-3" name="heart-outline"></ion-icon>
                        </button>
                    </div>
                    <?php endif; ?>

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

<script>
(function(){
    const peerCode = <?php echo $peerRow ? json_encode($peerCode) : '""'; ?>;
    const peerDisplay = <?php echo $peerRow ? json_encode($peerDisplay) : '""'; ?>;
    const meDisplay = <?php echo json_encode($meDisplay); ?>;

    const chatBox = document.getElementById('chatBox');
    const chatStream = document.getElementById('chatStream');
    const typingIndicator = document.getElementById('typingIndicator');

    let lastId = <?php echo (int)$lastId; ?>;
    let lastDay = (function(){
        const rows = document.querySelectorAll('.msgRow');
        if (!rows.length) return '';
        return rows[rows.length - 1].getAttribute('data-day') || '';
    })();

    function isNearBottom(el) {
        if (!el) return true;
        return (el.scrollHeight - el.scrollTop - el.clientHeight) < 140;
    }
    function scrollToBottom() {
        if (!chatBox) return;
        chatBox.scrollTop = chatBox.scrollHeight;
    }
    if (peerCode && chatBox) scrollToBottom();

    function escapeHtml(s){
        return String(s ?? '')
          .replace(/&/g,'&amp;')
          .replace(/</g,'&lt;')
          .replace(/>/g,'&gt;')
          .replace(/"/g,'&quot;')
          .replace(/'/g,'&#039;');
    }
    function nl2br(s){ return escapeHtml(s).replace(/\n/g, '<br>'); }

    function ensureDayDivider(dayKey, label) {
        if (!dayKey || !chatStream) return;
        const existing = chatStream.querySelector('.dayDivider[data-day="' + dayKey + '"]');
        if (existing) return;

        const wrap = document.createElement('div');
        wrap.className = 'flex justify-center dayDivider';
        wrap.setAttribute('data-day', dayKey);

        const inner = document.createElement('div');
        inner.className = 'font-medium text-gray-500 text-sm dark:text-white/70';
        inner.textContent = label || dayKey;

        wrap.appendChild(inner);
        chatStream.appendChild(wrap);
    }

    function updateTicksForMyMessage(id, isRead){
        const el = document.querySelector('.msgTicks[data-msgid="' + String(id) + '"]');
        if (el) el.textContent = isRead ? '✓✓' : '✓';
    }

    function appendMessage(item) {
        if (!chatBox || !chatStream) return;

        const nearBottom = isNearBottom(chatBox);

        if (item.day_key && item.day_key !== lastDay) {
            ensureDayDivider(item.day_key, item.day_label);
            lastDay = item.day_key;
        }

        const row = document.createElement('div');
        row.className = 'msgRow';
        row.setAttribute('data-id', item.id);
        row.setAttribute('data-day', item.day_key || '');

        const who = item.sender_name || (item.is_me ? (meDisplay || 'You') : (peerDisplay || 'User'));
        const timeLine = who + ' • ' + (item.time_label || '');

        if (item.is_me) {
            row.className += ' flex gap-2 flex-row-reverse items-end';
            row.innerHTML = `
                <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-5 h-5 rounded-full shadow">
                <div class="text-right">
                    <div class="px-4 py-2 rounded-[20px] max-w-sm bg-gradient-to-tr from-sky-500 to-blue-500 text-white shadow"></div>
                    <div class="mt-1 text-xs text-gray-400">
                        <span class="metaText"></span>
                        <span class="ml-1 msgTicks" data-msgid=""></span>
                    </div>
                </div>
            `;
            row.querySelector('div.px-4').innerHTML = nl2br(item.text || '');
            row.querySelector('.metaText').textContent = timeLine;
            const ticks = row.querySelector('.msgTicks');
            ticks.setAttribute('data-msgid', String(item.id || ''));
            ticks.textContent = (item.is_read && Number(item.is_read) === 1) ? '✓✓' : '✓';
        } else {
            row.className += ' flex gap-3';
            row.innerHTML = `
                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-9 h-9 rounded-full shadow">
                <div>
                    <div class="px-4 py-2 rounded-[20px] max-w-sm bg-secondery"></div>
                    <div class="mt-1 text-xs text-gray-500"></div>
                </div>
            `;
            row.querySelector('div.px-4').innerHTML = nl2br(item.text || '');
            row.querySelector('.text-xs').textContent = timeLine;
        }

        chatStream.appendChild(row);
        if (nearBottom) scrollToBottom();
    }

    // -----------------------
    // Long-poll messages
    // -----------------------
    let stop = false;
    async function longPollLoop(){
        if (!peerCode) return;

        while (!stop) {
            try {
                const url =
                  'ajax/user_chat_poll.php?peer=' + encodeURIComponent(peerCode) +
                  '&after=' + encodeURIComponent(String(lastId)) +
                  '&wait=20';

                const res = await fetch(url, { cache: 'no-store' });
                const data = await res.json();

                if (data && data.ok) {
                    if (Array.isArray(data.items)) {
                        for (const item of data.items) {
                            if (item.id && item.id > lastId) {
                                appendMessage(item);
                                lastId = item.id;
                            } else if (item.is_me && item.id) {
                                // if server returns older rows with updated is_read
                                updateTicksForMyMessage(item.id, Number(item.is_read) === 1);
                            }
                        }
                    }
                }
            } catch (e) {
                await new Promise(r => setTimeout(r, 700));
            }
        }
    }

    // -----------------------
    // Typing
    // -----------------------
    const input = document.getElementById('messageInput');
    const form  = document.getElementById('sendForm');

    let typingTimer = null;
    let lastTypingSent = 0;

    async function sendTyping(isTyping){
        if (!peerCode) return;
        try {
            await fetch('ajax/chat_typing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: 'peer=' + encodeURIComponent(peerCode) + '&typing=' + (isTyping ? '1' : '0')
            });
        } catch(e) {}
    }

    function onType(){
        const now = Date.now();
        if (now - lastTypingSent > 700) {
            sendTyping(true);
            lastTypingSent = now;
        }
        if (typingTimer) clearTimeout(typingTimer);
        typingTimer = setTimeout(() => sendTyping(false), 1800);
    }

    if (input) input.addEventListener('input', onType);

    if (form) {
        form.addEventListener('submit', () => {
            if (typingTimer) clearTimeout(typingTimer);
            sendTyping(false);
            // keep user at bottom after sending
            setTimeout(scrollToBottom, 120);
        });
    }

    async function pollTyping(){
        if (!peerCode || !typingIndicator) return;
        try {
            const res = await fetch('ajax/chat_typing_check.php?peer=' + encodeURIComponent(peerCode), { cache:'no-store' });
            const data = await res.json();
            if (data && data.ok && data.typing) {
                const nm = data.peer_name || peerDisplay || 'User';
                typingIndicator.textContent = nm + ' is typing...';
                typingIndicator.classList.remove('hidden');
            } else {
                typingIndicator.classList.add('hidden');
                typingIndicator.textContent = '';
            }
        } catch(e) {}
    }

    if (peerCode) {
        longPollLoop();
        pollTyping();
        setInterval(pollTyping, 650);
    }

    window.addEventListener('beforeunload', () => { stop = true; });

})();
</script>

</body>
</html>
