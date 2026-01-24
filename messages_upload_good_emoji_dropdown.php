<?php
// /Business_only3/messages.php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

// ✅ Online/Offline: bump my last_seen whenever this page loads
// (No code removed; this is additive and safe)
try {
    $sid = (int)($_SESSION['user_id'] ?? 0);
    if ($sid > 0) {
        $stSeen = $dbh->prepare("UPDATE users SET last_seen = NOW() WHERE id = :id LIMIT 1");
        $stSeen->execute([':id' => $sid]);
    }
} catch (Throwable $e) {
    // ignore
}

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

function fmt_time_short(string $dt): string {
    if ($dt === '') return '';
    $ts = strtotime($dt);
    return $ts ? date('h:i A', $ts) : '';
}

// ✅ Helper: human readable "Last seen X ago"
function seconds_ago_label(int $sec): string {
    if ($sec < 0) $sec = 0;
    if ($sec < 10) return 'Last seen just now';
    if ($sec < 60) return 'Last seen ' . $sec . 's ago';
    $m = (int) floor($sec / 60);
    if ($m < 60) return 'Last seen ' . $m . 'm ago';
    $h = (int) floor($m / 60);
    if ($h < 24) return 'Last seen ' . $h . 'h ago';
    $d = (int) floor($h / 24);
    return 'Last seen ' . $d . 'd ago';
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

/** Online/Offline helper based on users.last_seen */
function online_info(?string $lastSeen, int $thresholdSeconds = 300, ?int $ageSeconds = null): array {
    if ($ageSeconds !== null) {
        $online = ($ageSeconds <= $thresholdSeconds);
        return [
            'online' => $online,
            'label' => ($online ? 'Online' : seconds_ago_label((int)$ageSeconds)),
            'last_seen_label' => (string)($lastSeen ?? ''),
            'age_seconds' => $ageSeconds,
        ];
    }

    $lastSeen = (string)($lastSeen ?? '');
    if ($lastSeen === '') return ['online' => false, 'label' => 'Offline', 'last_seen_label' => '', 'age_seconds' => null];

    $ts = strtotime($lastSeen);
    if (!$ts) return ['online' => false, 'label' => 'Offline', 'last_seen_label' => '', 'age_seconds' => null];

    $age = time() - $ts;
    $online = ($age <= $thresholdSeconds);

    return [
        'online' => $online,
        'label' => ($online ? 'Online' : seconds_ago_label((int)$ageSeconds)),
        'last_seen_label' => date('M j, Y g:i A', $ts),
        'age_seconds' => $age,
    ];
}




/** session identity */
$meId    = function_exists('userId') ? (int)userId() : (int)($_SESSION['user_id'] ?? 0);
$meCode  = strtoupper(trim((string)userFriendCode()));
$meEmail = trim((string)userEmail());
$meName  = trim((string)myUserName());

if ($meCode === '' && $meEmail === '') {
    header("Location: index.php?session=reset");
    exit;
}

$meDisplay = $meName !== '' ? $meName : 'You';

/** Resolve peer */
function resolvePeerByCode(PDO $dbh, int $meId, string $peerCode): array {
    $peerCode = strtoupper(trim($peerCode));
    if ($peerCode === '') return ['ok'=>false];

    $st = $dbh->prepare("
        SELECT
            u.id, u.name, u.username, u.email, u.friend_code, u.status, u.last_seen,
            COALESCE(NULLIF(uc.display_name,''), u.friend_code) AS display
        FROM users u
        LEFT JOIN user_contacts uc
          ON uc.owner_user_id = :meId
         AND uc.friend_user_id = u.id
        WHERE UPPER(u.friend_code)=:c
        LIMIT 1
    ");
    $st->execute([':c'=>$peerCode, ':meId'=>$meId]);
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
function listThreads(PDO $dbh, int $meId, string $meCode, string $meEmail): array {
    $sql = "
        SELECT
            u.id AS peer_id,
            u.friend_code AS peer_key,
            u.last_seen AS peer_last_seen,
            TIMESTAMPDIFF(SECOND, u.last_seen, NOW()) AS peer_age_seconds,
            TIMESTAMPDIFF(SECOND, u.last_seen, NOW()) AS peer_age_seconds,
            COALESCE(NULLIF(uc.display_name,''), u.friend_code) AS peer_display,
	            CASE WHEN (uc.display_name IS NULL OR uc.display_name = '') THEN 1 ELSE 0 END AS is_unknown,
            u.last_seen AS peer_last_seen,

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
        LEFT JOIN user_contacts uc
          ON uc.owner_user_id = ?
         AND uc.friend_user_id = u.id
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
        $meId,
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

// Online/Offline info for current selected peer
$peerOnlineInfo = ['online' => false, 'label' => 'Offline'];

if ($peerRaw !== '') {
    $pr = resolvePeerByCode($dbh, $meId, $peerRaw);
    if (!empty($pr['ok'])) {
        $peerRow     = $pr['peer'];
        $peerCode    = (string)$pr['peerCode'];
        $peerEmail   = (string)$pr['peerEmail'];
        $peerDisplay = (string)$pr['peerDisplay'];

        if ($peerCode === $meCode) {
            $peerRow = null; $peerCode=''; $peerEmail=''; $peerDisplay='';
        }
        if ($peerRow) {
            $peerOnlineInfo = online_info((string)($peerRow['last_seen'] ?? ''), 300, isset($peerRow['peer_age_seconds']) ? (int)$peerRow['peer_age_seconds'] : null);
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

$threads = listThreads($dbh, $meId, $meCode, $meEmail);

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
    <!-- typing_fix_v1 -->
    <!-- messages_presence_live_v14 -->
    <!-- messages_FINAL_v2 -->
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Added: keyboard nav + highlight styles (no existing CSS removed) */
        .chat-active{outline:2px solid currentColor; outline-offset:2px; border-radius:12px;}
        mark.chatMark{padding:0 2px; border-radius:3px;}
        #noChatsMatch{display:none;}
    </style>
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
                                <input id="chatSearch" type="text" placeholder="Search" class="w-full !pl-10 !py-2 !rounded-lg" autocomplete="off">
                            </div>
                        </div>

                        <div class="space-y-2 p-2 overflow-y-auto md:h-[calc(95vh-204px)] h-[calc(100vh-130px)]" id="chatList">
                            <div id="noChatsMatch" class="p-4 text-sm text-gray-500">No chats match your search.</div>
                            <?php foreach ($threads as $t): ?>
                                <?php
                                    $peerKey     = (string)($t['peer_key'] ?? '');
                                    $pDisp       = (string)($t['peer_display'] ?? $peerKey);
	                                $isUnknown   = (int)($t['is_unknown'] ?? 1);
                                    $lastMsg     = (string)($t['last_message'] ?? '');
                                    $lastTime    = (string)($t['last_time'] ?? '');
                                    $lastTs      = $lastTime !== '' ? (int)strtotime($lastTime) : 0;
                                    $unread      = (int)($t['unread_count'] ?? 0);
                                    $oi = online_info((string)($t['peer_last_seen'] ?? ''), 300, isset($t['peer_age_seconds']) ? (int)$t['peer_age_seconds'] : null);
                                    $href = "messages.php?peer=" . urlencode($peerKey);
                                ?>
                                <a href="<?php echo h($href); ?>"
                                   class="chatItem relative flex items-center gap-4 p-2 duration-200 rounded-xl hover:bg-secondery"
                                   data-key="<?php echo h($peerKey); ?>"
                                   data-name="<?php echo h(mb_strtolower($pDisp)); ?>"
                                   data-code="<?php echo h(mb_strtolower($peerKey)); ?>"
                                   data-lastmsg="<?php echo h(mb_strtolower($lastMsg)); ?>"
                                   data-lastts="<?php echo (int)$lastTs; ?>"
                                   data-orig-name="<?php echo h($pDisp); ?>"
                                   data-orig-code="<?php echo h($peerKey); ?>"
                                   data-orig-lastmsg="<?php echo h($lastMsg); ?>">

                                    <div class="relative w-14 h-14 shrink-0">
                                        <img src="assets/images/avatars/avatar-5.jpg" alt="" class="object-cover w-full h-full rounded-full">
                                        <span class="peerOnlineDot inline-block w-2.5 h-2.5 rounded-full <?= $oi['online'] ? 'bg-green-500' : 'bg-gray-400'; ?>"
                                        title="<?= h($oi['label']); ?>"></span>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <div class="mr-auto text-sm text-black dark:text-white font-medium">
													<span class="chatName"><?php echo h($pDisp); ?></span>
                                                    
													<span class="peerOnlineLabel ml-2 text-[11px] font-semibold 
                                                        <?php echo $oi['online'] ? 'text-green-600' : 'text-gray-500'; ?>" hidden 
                                                        title="<?php echo h($oi['label']); ?>">
                                                    </span>
                                                    
	                                                <div class="flex items-center gap-2 text-xs text-gray-500">
	                                                    <span class="chatCode"><?php echo h($peerKey); ?></span>
	                                                    <?php if ($isUnknown === 1): ?>
	                                                        <span class="chatUnknown px-2 py-0.5 rounded-full bg-gray-200 text-gray-700">Unknown</span>
	                                                    <?php endif; ?>
	                                                </div>
                                            </div>

                                            <div class="text-xs font-light text-gray-500 dark:text-white/70">
                                                <span class="chatLastTime" title="<?php echo h(fmt_time_full($lastTime)); ?>"><?php echo h(fmt_time_short($lastTime)); ?></span>
                                            </div>

                                            <?php if ($unread > 0): ?>
                                                <div class="unreadDot w-2.5 h-2.5 bg-blue-600 rounded-full dark:bg-slate-700"></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="font-medium overflow-hidden text-ellipsis text-sm whitespace-nowrap">
                                            <span class="chatLastMsg"><?php echo h($lastMsg); ?></span>
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
                                        <div class="flex items-center gap-2">
                                            <div class="text-base font-bold" id="peerTitle">
                                                <?php echo h($peerRow ? $peerDisplay : 'Select a chat'); ?>
                                            </div>

                                    
                                            <div id="typingIndicator" class="hidden text-xs text-green-500 leading-tight mt-0.5"></div>
                                                <?php if ($peerRow && $peerCode !== ''): ?>
                                                <!-- ✍️ Rename inside messages (permanent SQL, user-only) -->
                                                <button id="btnRenamePeer" type="button" class="p-1 rounded-full hover:bg-secondery" title="Rename contact">
                                                    <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                                </button>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($peerRow && $peerCode !== ''): ?>
                                                <div id="renamePeerBox" class="mt-2" style="display:none;" onclick="event.stopPropagation();">
                                                    <div class="flex items-center gap-2">
                                                        <input id="renamePeerInput" type="text" class="w-60 !py-2 !rounded-lg" placeholder="Enter a name..." value="<?php echo h($peerDisplay); ?>">
                                                        <button id="btnRenameSave" type="button" class="px-3 py-2 rounded-lg bg-secondery">Save</button>
                                                        <button id="btnRenameCancel" type="button" class="px-3 py-2 rounded-lg bg-secondery">Cancel</button>
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-1">Renames are saved to your contacts and will update the sidebar.</div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-xs text-green-500 font-semibold">
                                                <?php echo $peerRow ? h($peerCode) : ''; ?>
                                            </div>
                                            <span id="peerOnlineBadge" class="text-[11px] font-semibold <?= $peerOnlineInfo['online'] ? 'text-green-600' : 'text-gray-500'; ?>">
                                            <?= h($peerOnlineInfo['online'] ? 'Online' : $peerOnlineInfo['label']); ?>
                                            </span>
                                        </div>
                                    </div>

                        <div class="flex items-center gap-2">

                            <a href="add_contact.php?friend=<?php echo urlencode($peerCode); ?>&return=messages" type="button" class="sm:p-2 p-1 rounded-full relative sm:bg-secondery dark:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 max-sm:hidden">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path>
                                    </svg>
                                <ion-icon name="add-circle-outline" class="sm:hidden text-2xl "></ion-icon>
                            </a>

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



                    <div id="chatBox" class="w-full p-5 py-10 overflow-y-auto md:h-[calc(95vh-204px)] h-[calc(100vh-195px)]">
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
                    <?php if ($peerRow): ?>
                    <div class="flex items-center md:gap-4 gap-2 md:p-3 p-2 overflow-hidden">
<!-- Attachment + Emoji toolbar -->
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="relative">
                                            <button type="button" id="msgBtnPlus"
                                                    class="w-10 h-10 rounded-full bg-secondery flex items-center justify-center text-xl">+</button>

                                            <!-- 4-circle menu -->
                                            <div id="msgPlusMenu"
                                                 class="hidden absolute z-20 mt-2 p-2 bg-white border border-gray-200 rounded-2xl shadow flex gap-2">
                                                <button type="button" class="w-12 h-12 rounded-full border bg-gray-50"
                                                        data-pick="image" title="Image">🖼</button>
                                                <button type="button" class="w-12 h-12 rounded-full border bg-gray-50"
                                                        data-pick="video" title="Video">🎥</button>
                                                <button type="button" class="w-12 h-12 rounded-full border bg-gray-50"
                                                        data-pick="doc" title="Document">📄</button>
                                                <button type="button" class="w-12 h-12 rounded-full border bg-gray-50"
                                                        data-pick="gif" title="GIF">🎞</button>
                                            </div>
                                        </div>

                                        <button type="button" id="msgBtnEmoji"
                                                class="w-10 h-10 rounded-full bg-secondery flex items-center justify-center text-xl"
                                                title="Emoji">😊
                                        </button>

                                        <input type="file" id="msgFileImage" name="attachment" class="hidden" accept="image/png,image/jpeg">
                                        <input type="file" id="msgFileVideo" name="attachment" class="hidden" accept="video/mp4,video/webm,video/ogg,video/quicktime">
                                        <input type="file" id="msgFileDoc"   name="attachment" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
                                        <input type="file" id="msgFileGif"   name="attachment" class="hidden" accept="image/gif">
                                    </div>

                                    <!-- Optional: document link/url (instead of upload) -->
                                    <input type="text" id="msgAttachmentUrl" name="attachment_url"
                                           class="hidden w-full bg-secondery rounded-xl px-4 p-2 mb-2"
                                           placeholder="Paste document link (optional)">

                                    <!-- Preview box -->
                                    <div id="msgPreviewBox" class="hidden w-full bg-secondery rounded-xl p-3 mb-2"></div>
                        

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
  console.log('messages_whatsapp_presence_typing loaded');

  // ✅ Presence heartbeat: keep my last_seen fresh even if I'm not sending messages
  // (Runs in background while messages.php is open)
  const startPresenceHeartbeat = () => {
    // Initial ping immediately
    fetch('ajax/me_presence_heartbeat.php', {cache:'no-store'}).catch(()=>{});
    // Then every 20 seconds
    setInterval(() => {
      fetch('ajax/me_presence_heartbeat.php', {cache:'no-store'}).catch(()=>{});
    }, 20000);
  };
  startPresenceHeartbeat();



  const peerCode = <?php echo $peerRow ? json_encode($peerCode) : '""'; ?>;
  const peerDisplay = <?php echo $peerRow ? json_encode($peerDisplay) : '""'; ?>;
  const meDisplay = <?php echo json_encode($meDisplay); ?>;

  const chatBox = document.getElementById('chatBox');
  const chatStream = document.getElementById('chatStream');
  const typingIndicator = document.getElementById('typingIndicator');
  const peerOnlineBadge = document.getElementById('peerOnlineBadge');

  let lastId = <?php echo (int)$lastId; ?>;

  let lastDay = (function(){
    const rows = document.querySelectorAll('.msgRow');
    if (!rows.length) return '';
    return rows[rows.length - 1].getAttribute('data-day') || '';
  })();

  function isNearBottom(el){
    if(!el) return true;
    return (el.scrollHeight - el.scrollTop - el.clientHeight) < 140;
  }
  function scrollToBottom(){
    if(!chatBox) return;
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
  function nl2br(s){ return escapeHtml(s).replace(/\\r?\\n/g,'<br>'); }

  function ensureDayDivider(dayKey, label){
    if(!dayKey || !chatStream) return;
    const existing = chatStream.querySelector('.dayDivider[data-day="' + dayKey + '"]');
    if(existing) return;

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

  
  function escapeHtml(str){
    return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
  }

  function renderAttachment(att){
    if(!att) return '';
    const s = String(att);

    // URL stored in attachment
    if(/^https?:\/\//i.test(s)){
      const u = escapeHtml(s);
      return `<div class="mt-2 text-sm"><a class="underline" target="_blank" href="${u}">🔗 ${u}</a></div>`;
    }

    const ext = (s.split('.').pop() || '').toLowerCase();
    const url = 'attachment/' + encodeURIComponent(s);

    if(['jpg','jpeg','png','gif'].includes(ext)){
      return `<div class="mt-2"><img src="${url}" class="max-w-[220px] rounded-xl" alt=""></div>`;
    }
    if(['mp4','webm','ogg','mov'].includes(ext)){
      return `<div class="mt-2"><video class="max-w-[320px] rounded-xl" controls src="${url}"></video></div>`;
    }
    const name = escapeHtml(s);
    return `<div class="mt-2 text-sm">📄 <a class="underline" target="_blank" href="${url}">${name}</a></div>`;
  }

function appendMessage(item){
    if(!chatBox || !chatStream) return;

    const nearBottom = isNearBottom(chatBox);

    if (item.day_key && item.day_key !== lastDay) {
      ensureDayDivider(item.day_key, item.day_label);
      lastDay = item.day_key;
    }

    const row = document.createElement('div');
    row.className = 'msgRow';
    row.setAttribute('data-id', String(item.id || ''));
    row.setAttribute('data-day', String(item.day_key || ''));

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
      row.querySelector('div.px-4').innerHTML = (item.text ? nl2br(item.text) : '') + renderAttachment(item.attachment);
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
      row.querySelector('div.px-4').innerHTML = (item.text ? nl2br(item.text) : '') + renderAttachment(item.attachment);
      row.querySelector('.text-xs').textContent = timeLine;
    }

    chatStream.appendChild(row);
    if (nearBottom) scrollToBottom();
  }

  // -----------------------
  // Long-poll messages
  // -----------------------
  let stop = false;
  let unreadCleared = false;

  function clearUnreadInSidebar(code){
    try{
      const a = document.querySelector('a.chatItem[data-key="' + CSS.escape(code) + '"]');
      if(!a) return;
      const dot = a.querySelector('.unreadDot');
      if(dot) dot.remove();
    }catch(e){}
  }

  async function longPollLoop(){
    if(!peerCode) return;

    while(!stop){
      try{
        const url = 'ajax/user_chat_poll.php?peer=' + encodeURIComponent(peerCode) +
                    '&mark=1&after=' + encodeURIComponent(String(lastId)) +
                    '&wait=1';

        const res = await fetch(url, {cache:'no-store'});
        const data = await res.json();

        if(data && data.ok){
          if(!unreadCleared){ clearUnreadInSidebar(peerCode); unreadCleared = true; }

          if(Array.isArray(data.items)){
            for(const item of data.items){
              if(item.id && Number(item.id) > lastId){
                appendMessage(item);
                lastId = Number(item.id);
              } else if (item.is_me && item.id) {
                updateTicksForMyMessage(item.id, Number(item.is_read) === 1);
              }
            }
          }
        }
      } catch(e){
        await new Promise(r => setTimeout(r, 700));
      }
    }
  }

  // -----------------------
  // Typing (WhatsApp style)
  // -----------------------
  const input = document.getElementById('messageInput');
  const form  = document.getElementById('sendForm');


  // -----------------------
  // Attachments UI (Plus menu + 4 circles + preview + emoji)
  // -----------------------
  const btnPlus   = document.getElementById('msgBtnPlus');
  const plusMenu  = document.getElementById('msgPlusMenu');
  const btnEmoji  = document.getElementById('msgBtnEmoji');
  const previewBox= document.getElementById('msgPreviewBox');
  const urlInput  = document.getElementById('msgAttachmentUrl');

  const fileImage = document.getElementById('msgFileImage');
  const fileVideo = document.getElementById('msgFileVideo');
  const fileDoc   = document.getElementById('msgFileDoc');
  const fileGif   = document.getElementById('msgFileGif');

  function clearFiles(){
    if(fileImage) fileImage.value='';
    if(fileVideo) fileVideo.value='';
    if(fileDoc)   fileDoc.value='';
    if(fileGif)   fileGif.value='';
  }

  function hidePreview(){
    if(!previewBox) return;
    previewBox.innerHTML='';
    previewBox.classList.add('hidden');
  }

  function showPreview(file){
    if(!previewBox) return;
    previewBox.innerHTML='';
    previewBox.classList.remove('hidden');

    const name = file.name || 'attachment';
    const ext = (name.split('.').pop() || '').toLowerCase();

    // image/gif
    if(file.type.startsWith('image/')){
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.className = 'max-w-[220px] rounded-xl';
      previewBox.appendChild(img);
      const cap = document.createElement('div');
      cap.className='mt-2 text-sm';
      cap.textContent = name;
      previewBox.appendChild(cap);
      return;
    }

    // video
    if(file.type.startsWith('video/')){
      const v = document.createElement('video');
      v.controls = true;
      v.src = URL.createObjectURL(file);
      v.className = 'max-w-[320px] rounded-xl';
      previewBox.appendChild(v);
      const cap = document.createElement('div');
      cap.className='mt-2 text-sm';
      cap.textContent = name;
      previewBox.appendChild(cap);
      return;
    }

    // documents
    previewBox.innerHTML = `<div class="text-sm">📄 ${name}</div>`;
  }

  function toggleMenu(){
    if(!plusMenu) return;

    const willOpen = plusMenu.classList.contains('hidden');
    if(willOpen){
      // ✅ Move to body + use fixed positioning so it can open UP (not clipped by bottom bar)
      if(!plusMenu.__movedToBody){
        document.body.appendChild(plusMenu);
        plusMenu.__movedToBody = true;
        // swap absolute dropdown classes to fixed
        plusMenu.classList.remove('absolute','mt-2');
        plusMenu.classList.add('fixed','z-[9999]');
      }

      // show first so we can measure height
      plusMenu.classList.remove('hidden');

      const r = btnPlus.getBoundingClientRect();
      const menuW = plusMenu.offsetWidth || 220;
      // align near the + button
      let left = Math.round(r.left);
      // keep within viewport
      left = Math.min(Math.max(8, left), window.innerWidth - menuW - 8);

      // open upward above + button
      let top = Math.round(r.top - plusMenu.offsetHeight - 8);
      // fallback: if not enough space, open downward
      if(top < 8) top = Math.round(r.bottom + 8);

      plusMenu.style.left = left + 'px';
      plusMenu.style.top  = top + 'px';

      // re-position next frame (after layout)
      requestAnimationFrame(() => {
        const menuW2 = plusMenu.offsetWidth || menuW;
        let left2 = Math.min(Math.max(8, Math.round(r.left)), window.innerWidth - menuW2 - 8);
        let top2  = Math.round(r.top - plusMenu.offsetHeight - 8);
        if(top2 < 8) top2 = Math.round(r.bottom + 8);
        plusMenu.style.left = left2 + 'px';
        plusMenu.style.top  = top2 + 'px';
      });
    } else {
      plusMenu.classList.add('hidden');
    }
  }

  if(btnPlus && plusMenu){
    btnPlus.addEventListener('click', (e) => {
      e.preventDefault();
      toggleMenu();
    });

    // Keep menu anchored to + button on resize/scroll
    const repositionPlusMenu = () => {
      if(!plusMenu || plusMenu.classList.contains('hidden')) return;
      const r = btnPlus.getBoundingClientRect();
      const menuW = plusMenu.offsetWidth || 220;
      let left = Math.min(Math.max(8, Math.round(r.left)), window.innerWidth - menuW - 8);
      let top  = Math.round(r.top - plusMenu.offsetHeight - 8);
      if(top < 8) top = Math.round(r.bottom + 8);
      plusMenu.style.left = left + 'px';
      plusMenu.style.top  = top + 'px';
    };
    window.addEventListener('resize', repositionPlusMenu);
    window.addEventListener('scroll', repositionPlusMenu, true);
document.addEventListener('click', (e) => {
      if(btnPlus.contains(e.target)) return;
      if(plusMenu.contains(e.target)) return;
      plusMenu.classList.add('hidden');
    });

    plusMenu.querySelectorAll('button[data-pick]').forEach((b) => {
      b.addEventListener('click', (e) => {
        e.preventDefault();
        const pick = b.getAttribute('data-pick');
        clearFiles();
        hidePreview();
        if(urlInput){
          urlInput.value='';
          urlInput.classList.add('hidden');
        }
        plusMenu.classList.add('hidden');

        if(pick === 'image' && fileImage) fileImage.click();
        if(pick === 'video' && fileVideo) fileVideo.click();
        if(pick === 'gif'   && fileGif)   fileGif.click();
        if(pick === 'doc'){
          // Let user pick file OR paste url
          if(urlInput) urlInput.classList.remove('hidden');
          if(fileDoc) fileDoc.click();
        }
      });
    });
  }

  [fileImage,fileVideo,fileDoc,fileGif].forEach((inp) => {
    if(!inp) return;
    inp.addEventListener('change', () => {
      if(inp.files && inp.files[0]) showPreview(inp.files[0]);
    });
  });

  if(btnEmoji && input){
  // ✅ Emoji dropdown (no alert/prompt)
  const emojiMenu = document.createElement('div');
  emojiMenu.id = 'emojiMenu';
  emojiMenu.className = 'hidden fixed z-[9999] w-[520px] max-w-[92vw] rounded-2xl overflow-hidden border border-slate-700 shadow-2xl';

  const emojiHeader = document.createElement('div');
  emojiHeader.className = 'bg-slate-700 text-white px-6 py-4 text-3xl font-extrabold';
  emojiHeader.textContent = 'Send Imogi';

  const emojiBody = document.createElement('div');
  emojiBody.className = 'bg-white px-10 py-10 max-h-[320px] overflow-y-auto';

  const emojiGrid = document.createElement('div');
  emojiGrid.id = 'emojiGrid';
  emojiGrid.className = 'grid grid-cols-5 gap-x-10 gap-y-8 justify-items-center';

  emojiBody.appendChild(emojiGrid);
  emojiMenu.appendChild(emojiHeader);
  emojiMenu.appendChild(emojiBody);
  document.body.appendChild(emojiMenu);

  const emojiList = [
    "😀","😁","😂","🤣","😊","😍","😘","😎",
    "😢","😭","😡","👍","👎","🙏","👏","🔥",
    "🎉","💯","❤️","💔","✅","❌","⭐","✨",
    "🤝","👀","💬","📌","🚀","🤔","😴","🙌"
  ];

  function buildEmojiGrid(){
    emojiGrid.innerHTML = '';
    for(const em of emojiList){
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'w-12 h-12 rounded-2xl hover:bg-slate-100 text-4xl flex items-center justify-center transition-transform duration-150 hover:scale-105';
      b.textContent = em;
      b.addEventListener('click', () => {
        input.value = (input.value || '') + em;
        input.focus();
        emojiMenu.classList.add('hidden');
      });
      emojiGrid.appendChild(b);
    }
  }

  function positionEmojiMenu(){
    const r = btnEmoji.getBoundingClientRect();
    const menuW = 520;
    let left = Math.round(r.right - menuW);
    if(left < 8) left = 8;

    // show to measure height
    let top = Math.round(r.top - emojiMenu.offsetHeight - 8);
    if(top < 8) top = Math.round(r.bottom + 8);

    // move closer to button a bit
    emojiMenu.style.left = (left + 4) + 'px';
    emojiMenu.style.top  = (top + 2) + 'px';
  }

  function toggleEmojiMenu(){
    const willOpen = emojiMenu.classList.contains('hidden');
    if(willOpen){
      emojiMenu.classList.remove('hidden');
      positionEmojiMenu();
      requestAnimationFrame(positionEmojiMenu);
    } else {
      emojiMenu.classList.add('hidden');
    }
  }

  buildEmojiGrid();

  btnEmoji.addEventListener('click', (e) => {
    e.preventDefault();
    toggleEmojiMenu();
  });

  document.addEventListener('click', (e) => {
    if(emojiMenu.classList.contains('hidden')) return;
    const inside = emojiMenu.contains(e.target) || btnEmoji.contains(e.target);
    if(!inside) emojiMenu.classList.add('hidden');
  });

  document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') emojiMenu.classList.add('hidden');
  });

  window.addEventListener('resize', () => { if(!emojiMenu.classList.contains('hidden')) positionEmojiMenu(); });
  window.addEventListener('scroll',  () => { if(!emojiMenu.classList.contains('hidden')) positionEmojiMenu(); }, true);
}

  let typingTimer = null;
  let lastTypingSent = 0;

  async function sendTyping(isTyping){
    if(!peerCode) return;
    try{
      await fetch('ajax/chat_typing.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body:'peer=' + encodeURIComponent(peerCode) + '&typing=' + (isTyping ? '1' : '0')
      });
    }catch(e){}
  }

  function onType(){
    const now = Date.now();
    if(now - lastTypingSent > 700){
      sendTyping(true);
      lastTypingSent = now;
    }
    if(typingTimer) clearTimeout(typingTimer);
    typingTimer = setTimeout(() => sendTyping(false), 1800);
  }

  if(input){
    input.addEventListener('input', onType);
    input.addEventListener('keydown', onType);
    input.addEventListener('paste', onType);
    input.addEventListener('blur', () => {
      if(typingTimer) clearTimeout(typingTimer);
      sendTyping(false);
    });
  }

  document.addEventListener('visibilitychange', () => {
    if(document.hidden){
      if(typingTimer) clearTimeout(typingTimer);
      sendTyping(false);
    }
  });

  async function pollTyping(){
    if(!peerCode || !typingIndicator) return;
    try{
      const res = await fetch('ajax/chat_typing_check.php?peer=' + encodeURIComponent(peerCode), {cache:'no-store'});
      const data = await res.json();
      if(data && data.ok && data.typing){
        typingIndicator.textContent = 'typing…';
        typingIndicator.classList.remove('hidden');
      } else {
        typingIndicator.classList.add('hidden');
        typingIndicator.textContent = '';
      }
    }catch(e){}
  }

  // -----------------------
  // Send message (AJAX)
  // -----------------------
  if(form){
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if(!peerCode || !input) return;

      const msg = (input.value || '').trim();

      // Attachment inputs (optional)
      const fileImage = document.getElementById('msgFileImage');
      const fileVideo = document.getElementById('msgFileVideo');
      const fileDoc   = document.getElementById('msgFileDoc');
      const fileGif   = document.getElementById('msgFileGif');
      const urlInput  = document.getElementById('msgAttachmentUrl');

      const pickedFile =
        (fileImage && fileImage.files && fileImage.files[0]) ? fileImage.files[0] :
        (fileVideo && fileVideo.files && fileVideo.files[0]) ? fileVideo.files[0] :
        (fileDoc   && fileDoc.files   && fileDoc.files[0])   ? fileDoc.files[0] :
        (fileGif   && fileGif.files   && fileGif.files[0])   ? fileGif.files[0] :
        null;

      const attachmentUrl = (urlInput && urlInput.value) ? urlInput.value.trim() : '';

      // Allow: message OR file OR url
      if(!msg && !pickedFile && !attachmentUrl) return;

      if(typingTimer) clearTimeout(typingTimer);
      sendTyping(false);

      const btn = form.querySelector('button[type="submit"]');
      if(btn) btn.disabled = true;

      try{
        const fd = new FormData();
        fd.append('to', peerCode);
        fd.append('message', msg);
        if(pickedFile) fd.append('attachment', pickedFile);
        if(attachmentUrl) fd.append('attachment_url', attachmentUrl);

        const res = await fetch('ajax/user_chat_send.php', {
          method:'POST',
          body: fd
        });
        const data = await res.json();

        if(data && data.ok && data.item){
          const item = data.item;
          item.text = item.text ?? item.feedbackdata ?? '';
          item.time_label = item.time_label ?? item.time ?? '';
          item.is_me = true;
          appendMessage(item);
          if(item.id && Number(item.id) > lastId) lastId = Number(item.id);
          input.value = '';
          setTimeout(scrollToBottom, 120);
        } else {
          alert((data && data.error) ? data.error : 'Message failed');
        }
      }catch(err){
        alert('Message failed');
      }finally{
        if(btn) btn.disabled = false;
      }
    });
  }

  // -----------------------
  // Sidebar search (keeps working after live refresh)
  // -----------------------
  const chatSearch = document.getElementById('chatSearch');
  const noChatsMatch = document.getElementById('noChatsMatch');

  function getChatItems(){ return Array.from(document.querySelectorAll('a.chatItem')); }

  function highlightText(text, q){
    if(!q) return escapeHtml(text);
    const idx = text.toLowerCase().indexOf(q);
    if(idx < 0) return escapeHtml(text);
    const before = text.slice(0, idx);
    const match = text.slice(idx, idx + q.length);
    const after = text.slice(idx + q.length);
    return escapeHtml(before) + '<mark class="chatMark">' + escapeHtml(match) + '</mark>' + escapeHtml(after);
  }

  let activeIndex = -1;
  function clearActive(){ for(const a of getChatItems()) a.classList.remove('chat-active'); }
  function visibleChats(){ return getChatItems().filter(a => a.style.display !== 'none'); }

  function applyChatSearch(){
    const q = (chatSearch ? (chatSearch.value || '') : '').trim().toLowerCase();
    const items = getChatItems();
    let visible = [];

    for(const a of items){
      const name = (a.getAttribute('data-name') || '').toLowerCase();
      const code = (a.getAttribute('data-code') || '').toLowerCase();
      const last = (a.getAttribute('data-lastmsg') || '').toLowerCase();
      const ok = (q === '' || name.includes(q) || code.includes(q) || last.includes(q));
      a.style.display = ok ? '' : 'none';
      if(ok) visible.push(a);

      const origName = a.getAttribute('data-orig-name') || '';
      const origCode = a.getAttribute('data-orig-code') || '';
      const origLast = a.getAttribute('data-orig-lastmsg') || '';
      const nEl = a.querySelector('.chatName');
      const cEl = a.querySelector('.chatCode');
      const lEl = a.querySelector('.chatLastMsg');
      if(nEl) nEl.innerHTML = highlightText(origName, q);
      if(cEl) cEl.innerHTML = highlightText(origCode, q);
      if(lEl) lEl.innerHTML = highlightText(origLast, q);
    }

    visible.sort((a,b)=> (Number(b.getAttribute('data-lastts')||0) - Number(a.getAttribute('data-lastts')||0)));
    if(visible.length > 0){
      const parent = visible[0].parentElement;
      if(parent){
        for(const el of visible) parent.appendChild(el);
      }
    }

    if(noChatsMatch){
      noChatsMatch.style.display = (q !== '' && visible.length === 0) ? '' : 'none';
    }

    activeIndex = -1;
    clearActive();
  }

  function setActive(i){
    const list = visibleChats();
    if(list.length === 0) return;
    if(i < 0) i = 0;
    if(i >= list.length) i = list.length - 1;
    activeIndex = i;
    clearActive();
    list[activeIndex].classList.add('chat-active');
    list[activeIndex].scrollIntoView({block:'nearest'});
  }

  function onKeyNav(e){
    const list = visibleChats();
    if(list.length === 0) return;
    if(e.key === 'ArrowDown'){ e.preventDefault(); setActive(activeIndex + 1); }
    if(e.key === 'ArrowUp'){ e.preventDefault(); setActive(activeIndex - 1); }
    if(e.key === 'Enter'){
      if(activeIndex >= 0 && list[activeIndex]){
        e.preventDefault();
        window.location.href = list[activeIndex].getAttribute('href');
      }
    }
  }

  if(chatSearch){
    chatSearch.addEventListener('input', applyChatSearch);
    chatSearch.addEventListener('keydown', onKeyNav);
  }
  applyChatSearch();

  // -----------------------
  // Rename contact
  // -----------------------
  const btnRenamePeer = document.getElementById('btnRenamePeer');
  const renamePeerBox = document.getElementById('renamePeerBox');
  const renamePeerInput = document.getElementById('renamePeerInput');
  const btnRenameSave = document.getElementById('btnRenameSave');
  const btnRenameCancel = document.getElementById('btnRenameCancel');

  function openRenameBox(){
    if(!renamePeerBox) return;
    renamePeerBox.style.display = 'block';
    if(renamePeerInput){
      renamePeerInput.focus();
      renamePeerInput.setSelectionRange(0, renamePeerInput.value.length);
    }
  }
  function closeRenameBox(){
    if(!renamePeerBox) return;
    renamePeerBox.style.display = 'none';
  }

  async function saveRename(){
    if(!peerCode || !renamePeerInput) return;
    const nm = (renamePeerInput.value || '').trim();
    if(!nm) return alert('Name is required.');

    try{
      const res = await fetch('ajax/contact_save_from_messages.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body:'friend_code=' + encodeURIComponent(peerCode) + '&display_name=' + encodeURIComponent(nm)
      });
      const data = await res.json();
      if(!data || !data.ok){
        return alert((data && data.error) ? data.error : 'Rename failed');
      }

      const peerTitle = document.getElementById('peerTitle');
      if(peerTitle) peerTitle.textContent = nm;

      const item = document.querySelector('a.chatItem[data-key="' + CSS.escape(peerCode) + '"]');
      if(item){
        item.setAttribute('data-orig-name', nm);
        item.setAttribute('data-name', nm.toLowerCase());
        const nEl = item.querySelector('.chatName');
        if(nEl) nEl.textContent = nm;
        const unk = item.querySelector('.chatUnknown');
        if(unk) unk.remove();
        applyChatSearch();
      }
      closeRenameBox();
    }catch(e){
      alert('Rename failed');
    }
  }

  if(btnRenamePeer) btnRenamePeer.addEventListener('click', (e)=>{ e.stopPropagation(); openRenameBox(); });
  if(btnRenameCancel) btnRenameCancel.addEventListener('click', (e)=>{ e.stopPropagation(); closeRenameBox(); });
  if(btnRenameSave) btnRenameSave.addEventListener('click', (e)=>{ e.stopPropagation(); saveRename(); });
  if(renamePeerInput) renamePeerInput.addEventListener('keydown', (e)=>{
    if(e.key === 'Enter'){ e.preventDefault(); saveRename(); }
    if(e.key === 'Escape'){ e.preventDefault(); closeRenameBox(); }
  });

  // -----------------------
  // Presence (WhatsApp-like)
  // - Dot: green if online, gray if offline
  // - Header: "Last seen ..." (server-provided) for both online & offline
  // -----------------------
  function applyPresenceToItem(code, info){
    if(!code) return;
    const item = document.querySelector('a.chatItem[data-key="' + CSS.escape(code) + '"]');
    if(!item) return;

    const online = !!(info && info.online);
    const label  = (info && info.label) ? String(info.label) : (online ? 'Online' : 'Offline');

    const dot = item.querySelector('.peerOnlineDot');
    if(dot){
      dot.classList.remove('bg-green-500','bg-gray-400');
      dot.classList.add(online ? 'bg-green-500' : 'bg-gray-400');
      dot.title = label;
    }

    const lbl = item.querySelector('.peerOnlineLabel');
    if(lbl){
      lbl.title = label;
      // label is hidden in your HTML; title still helpful on hover
      lbl.classList.remove('text-green-600','text-gray-500');
      lbl.classList.add(online ? 'text-green-600' : 'text-gray-500');
    }
  }

  let presenceOfflineStrikes = 0;
  async function pollPresenceHeader(){
    if(!peerCode) return;
    try{
      const res = await fetch('ajax/user_presence_ping.php?peer=' + encodeURIComponent(peerCode), {cache:'no-store'});
      const data = await res.json();
      if(!data || !data.ok) return;

      const online = !!data.online;
      const label = data.label ? String(data.label) : (online ? 'Online' : 'Offline');

      if(peerOnlineBadge){
        peerOnlineBadge.textContent = label;
        peerOnlineBadge.title = label;
        peerOnlineBadge.classList.remove('text-green-600','text-gray-500');
        peerOnlineBadge.classList.add(stableOnline ? 'text-green-600' : 'text-gray-500');
      }

      // also update sidebar row for active peer
      applyPresenceToItem(peerCode, {online, label});
    }catch(e){}
  }

  function getSidebarPeerCodes(){
    const nodes = document.querySelectorAll('a.chatItem[data-key]');
    const set = new Set();
    nodes.forEach(n => {
      const c = (n.getAttribute('data-key') || '').trim();
      if(c) set.add(c.toUpperCase());
    });
    return Array.from(set);
  }

  async function pollPresenceAll(){
    try{
      const peers = getSidebarPeerCodes();
      if(!peers.length) return;

      const res = await fetch('ajax/user_presence_batch.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        cache:'no-store',
        body:new URLSearchParams({peers: JSON.stringify(peers)})
      });
      const data = await res.json();
      if(!data || !data.ok || !data.data) return;

      for(const code of Object.keys(data.data)){
        applyPresenceToItem(code, data.data[code]);
      }
      // (Disabled) Do not override header presence from batch to avoid flicker.
}catch(e){}
  }

  // -----------------------
  // Sidebar live refresh (preview + unread dot + ordering)
  // -----------------------
  async function refreshSidebarThreads(){
    try{
      const res = await fetch('ajax/user_chat_threads_poll.php', {cache:'no-store'});
      const data = await res.json();
      if(!data || !data.ok || !Array.isArray(data.threads)) return;

      const list = document.getElementById('chatList');
      if(!list) return;

      const byKey = new Map();
      list.querySelectorAll('a.chatItem[data-key]').forEach(a => byKey.set(a.dataset.key, a));

      for(const t of data.threads){
        const key = String(t.peer_key || '');
        if(!key) continue;

        let a = byKey.get(key);
        if(!a) continue; // keep your server-rendered items; no need to create new here

        const display = String(t.peer_display || key);
        const lastMsg = String(t.last_message || '');
        const lastShort = String(t.last_time_short || '');
        const lastFull = String(t.last_time_full || '');
        const lastTs = Number(t.last_ts || 0);
        const unread = Number(t.unread_count || 0);

        a.dataset.lastts = String(lastTs);
        a.dataset.lastmsg = lastMsg.toLowerCase();

        const nameEl = a.querySelector('.chatName'); if(nameEl) nameEl.textContent = display;
        const msgEl  = a.querySelector('.chatLastMsg'); if(msgEl) msgEl.textContent = lastMsg;

        const timeEl = a.querySelector('.chatLastTime');
        if(timeEl){
          timeEl.textContent = lastShort;
          if(lastFull) timeEl.setAttribute('title', lastFull);
        }

        const existingDot = a.querySelector('.unreadDot');
        if(unread > 0){
          if(!existingDot){
            const dot = document.createElement('div');
            dot.className = 'unreadDot w-2.5 h-2.5 bg-blue-600 rounded-full dark:bg-slate-700';
            const timeWrap = a.querySelector('.text-xs.font-light.text-gray-500.dark\:text-white\/70');
            if(timeWrap && timeWrap.parentElement) timeWrap.parentElement.appendChild(dot);
            else a.querySelector('.flex.items-center.gap-2.mb-1\.5')?.appendChild(dot);
          }
        } else {
          if(existingDot) existingDot.remove();
        }
      }

      const items = Array.from(list.querySelectorAll('a.chatItem[data-key]'));
      items.sort((a,b)=> (Number(b.dataset.lastts||0) - Number(a.dataset.lastts||0)));
      for(const a of items) list.appendChild(a);

      // re-apply search highlight after live updates
      applyChatSearch();
    }catch(e){}
  }

  // -----------------------
  // Start loops
  // -----------------------
  pollPresenceAll();
  setInterval(pollPresenceAll, 3000);

  refreshSidebarThreads();
  setInterval(refreshSidebarThreads, 2000);

  if(peerCode){
    longPollLoop();

    pollTyping();
    setInterval(pollTyping, 650);

    pollPresenceHeader();
    setInterval(pollPresenceHeader, 2000);
  }

  window.addEventListener('beforeunload', () => {
    stop = true;
    try{ sendTyping(false); }catch(e){}
  });
})();
</script>

</body>
</html>
