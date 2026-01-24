<?php
// /Business_only3/includes/header.php
require_once __DIR__ . '/session_user.php';
requireUserLogin();

require_once __DIR__ . '/user_identity.php';
require_once __DIR__ . '/../admin/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

$meId = function_exists('userId') ? (int)userId() : (int)($_SESSION['user_id'] ?? 0);

/**
 * Get my friend code
 */
$meCode = '';
if (function_exists('userFriendCode')) {
    $meCode = trim((string) userFriendCode());
}
if ($meCode === '') {
    $meCode = trim((string)($_SESSION['user_friend_code'] ?? ''));
}
$meEmail = '';
if (function_exists('userEmail')) {
    $meEmail = trim((string) userEmail());
}
if ($meEmail === '') {
    $meEmail = trim((string)($_SESSION['user_email'] ?? ''));
}

/**
 * Fetch unread chat threads ONLY
 * - show nickname from user_contacts if you saved it
 * - otherwise show friend_code (NOT real name)
 */
$chatThreads = [];

if ($meCode !== '' || $meEmail !== '') {
    try {
        $st = $dbh->prepare("
            SELECT
                u.friend_code AS peer_code,
                uc.display_name AS contact_name,
                COALESCE(NULLIF(uc.display_name,''), u.friend_code) AS peer_display,
                MAX(f.created_at) AS last_time,
                SUBSTRING_INDEX(GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR '\n'), '\n', 1) AS last_message,
                COUNT(*) AS unread_count
            FROM feedback f
            JOIN users u
              ON (u.friend_code = f.sender OR u.email = f.sender)
            LEFT JOIN user_contacts uc
              ON uc.owner_user_id = :meId
             AND uc.friend_user_id = u.id
            WHERE f.channel = 'user_user'
              AND f.is_read = 0
              AND (f.receiver = :meCode OR f.receiver = :meEmail)
            GROUP BY u.friend_code, peer_display
            ORDER BY last_time DESC
            LIMIT 8
        ");
        $st->execute([
            ':meId' => $meId,
            ':meCode' => $meCode,
            ':meEmail' => $meEmail,
        ]);
        $chatThreads = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $chatThreads = [];
    }
}

// Split threads:
// - Named contacts: show in dropdown
// - Unknown friend codes: keep unread count, but do NOT show in dropdown
$unknownUnread = 0;
$namedChatThreads = [];
foreach ($chatThreads as $t) {
    $contactName = trim((string)($t['contact_name'] ?? ''));
    $cnt = (int)($t['unread_count'] ?? 0);
    if ($contactName === '') {
        $unknownUnread += $cnt;
    } else {
        $namedChatThreads[] = $t;
    }
}

 
?>

<!-- header -->
<header class="z-[100] h-[--m-top] fixed top-0 left-0 w-full flex items-center bg-white/80 sky-50 backdrop-blur-xl border-b border-slate-200 dark:bg-dark2 dark:border-slate-800">

    <div class="flex items-center w-full xl:px-6 px-2 max-lg:gap-10">

        <div class="2xl:w-[--w-side] lg:w-[--w-side-sm]">

            <!-- left -->
            <div class="flex items-center gap-1"> 

                <!-- icon menu -->
                <button uk-toggle="target: #site__sidebar ; cls :!-translate-x-0"
                        class="flex items-center justify-center w-8 h-8 text-xl rounded-full hover:bg-gray-100 xl:hidden dark:hover:bg-slate-600 group"> 
                        <ion-icon name="menu-outline" class="text-2xl group-aria-expanded:hidden"></ion-icon>
                        <ion-icon name="close-outline" class="hidden text-2xl group-aria-expanded:block"></ion-icon>
                </button>
                <div id="logo">
                    <a href="feed.php"> 
                        <img src="assets/images/logo.png" alt="" class="w-28 md:block hidden dark:!hidden">
                        <img src="assets/images/logo-light.png" alt="" class="dark:md:block hidden">
                        <img src="assets/images/logo-mobile.png" class="hidden max-md:block w-20 dark:!hidden" alt="">
                        <img src="assets/images/logo-mobile-light.png" class="hidden dark:max-md:block w-20" alt="">
                    </a>
                </div>
                    
            </div>

        </div>
        <div class="flex-1 relative">

            <div class="max-w-[1220px] mx-auto flex items-center">

                <!-- header icons -->
                <div class="flex items-center sm:gap-4 gap-2 absolute right-5 top-1/2 -translate-y-1/2 text-black">

                    <!-- notification -->
                    <button type="button" class="sm:p-2 p-1 rounded-full relative sm:bg-secondery dark:text-white" uk-tooltip="title: Notification; pos: bottom; offset:6">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 max-sm:hidden">
                            <path d="M5.85 3.5a.75.75 0 00-1.117-1 9.719 9.719 0 00-2.348 4.876.75.75 0 001.479.248A8.219 8.219 0 015.85 3.5zM19.267 2.5a.75.75 0 10-1.118 1 8.22 8.22 0 011.987 4.124.75.75 0 001.48-.248A9.72 9.72 0 0019.266 2.5z" />
                            <path fill-rule="evenodd" d="M12 2.25A6.75 6.75 0 005.25 9v.75a8.217 8.217 0 01-2.119 5.52.75.75 0 00.298 1.206c1.544.57 3.16.99 4.831 1.243a3.75 3.75 0 107.48 0 24.583 24.583 0 004.83-1.244.75.75 0 00.298-1.205 8.217 8.217 0 01-2.118-5.52V9A6.75 6.75 0 0012 2.25zM9.75 18c0-.034 0-.067.002-.1a25.05 25.05 0 004.496 0l.002.1a2.25 2.25 0 11-4.5 0z" clip-rule="evenodd" />
                        </svg>
                        <ion-icon name="notifications-outline" class="sm:hidden text-2xl"></ion-icon>
                    </button> 

                    <!-- messages -->
                    <a href="messages.php"
                       class="sm:p-2 p-1 rounded-full relative sm:bg-secondery dark:text-white inline-flex items-center justify-center"
                       uk-tooltip="title: Messages; pos: bottom; offset:6">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 max-sm:hidden">
                            <path fill-rule="evenodd" d="M4.848 2.771A49.144 49.144 0 0112 2.25c2.43 0 4.817.178 7.152.52 1.978.292 3.348 2.024 3.348 3.97v6.02c0 1.946-1.37 3.678-3.348 3.97a48.901 48.901 0 01-3.476.383.39.39 0 00-.297.17l-2.755 4.133a.75.75 0 01-1.248 0l-2.755-4.133a.39.39 0 00-.297-.17 48.9 48.9 0 01-3.476-.384c-1.978-.29-3.348-2.024-3.348-3.97V6.741c0-1.946 1.37-3.68 3.348-3.97zM6.75 8.25a.75.75 0 01.75-.75h9a.75.75 0 010 1.5h-9a.75.75 0 01-.75-.75zm.75 2.25a.75.75 0 000 1.5H12a.75.75 0 000-1.5H7.5z" clip-rule="evenodd"></path>
                        </svg>

                        <ion-icon name="chatbox-ellipses-outline" class="sm:hidden text-2xl"></ion-icon>

                        <!-- ✅ unread badge -->
                        <span id="chatBadge"
                              class="hidden absolute -top-1 -right-1 bg-red-600 text-white text-[11px] leading-none px-1.5 py-1 rounded-full min-w-[18px] text-center">
                        </span>
                    </a>

                    <div class="hidden bg-white pr-1.5 rounded-lg drop-shadow-xl dark:bg-slate-700 md:w-[360px] w-screen border2"
                         uk-drop="offset:6;pos: bottom-right; mode: click; animate-out: true; animation: uk-animation-scale-up uk-transform-origin-top-right ">

                        <!-- heading -->
                        <div class="flex items-center justify-between gap-2 p-4 pb-1">
                            <h3 class="font-bold text-xl"> Chats </h3>

                            <div class="flex gap-2.5 text-lg text-slate-900 dark:text-white">
                                <ion-icon name="expand-outline"></ion-icon>
                                <a href="compose.php"><ion-icon name="create-outline"></ion-icon></a>
                            </div>
                        </div>

                        <div class="relative w-full p-2 px-3 ">
                            <input type="text" class="w-full !pl-10 !rounded-lg dark:!bg-white/10" placeholder="Search">
                            <ion-icon name="search-outline" class="dark:text-white absolute left-7 -translate-y-1/2 top-1/2"></ion-icon>
                        </div>

                        <div class="h-80 overflow-y-auto pr-2">
                            <div class="p-2 pt-0 pr-1 dark:text-white/80" id="chatDropdownList">
                                <?php if ($unknownUnread > 0): ?>
                                    <a href="messages.php" class="block p-3 mb-2 text-sm rounded bg-yellow-50 text-yellow-800 hover:bg-yellow-100">
                                        You have <b><?php echo (int)$unknownUnread; ?></b> new message(s) from unknown friend code(s). Open Messages to name them.
                                    </a>
                                <?php endif; ?>

                                <?php if (empty($namedChatThreads) && $unknownUnread === 0): ?>
                                    <div class="p-3 text-sm text-gray-500">No new messages.</div>
                                <?php else: ?>
                                    <?php foreach ($namedChatThreads as $t): ?>
                                        <?php
                                            $peerCode = (string)($t['peer_code'] ?? '');
                                            $peerName = (string)($t['peer_display'] ?? $peerCode);
                                            $lastMsg  = (string)($t['last_message'] ?? '');
                                            $lastTime = (string)($t['last_time'] ?? '');
                                            $unread   = (int)($t['unread_count'] ?? 0);

                                            $href = "user_sendreply.php?peer=" . urlencode($peerCode);

                                            $timeLabel = '';
                                            if ($lastTime !== '') {
                                                $ts = strtotime($lastTime);
                                                if ($ts) $timeLabel = date('h:i A', $ts);
                                            }
                                        ?>
                                        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
                                           class="relative flex items-center gap-4 p-2 py-3 duration-200 rounded-lg hover:bg-secondery dark:hover:bg-white/10">
                                            <div class="relative w-10 h-10 shrink-0">
                                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="object-cover w-full h-full rounded-full">
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <div class="mr-auto text-sm text-black dark:text-white font-medium">
                                                        <?php echo htmlspecialchars($peerName, ENT_QUOTES, 'UTF-8'); ?>
                                                        <div class="text-[11px] text-gray-500"><?php echo htmlspecialchars($peerCode, ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </div>

                                                    <div class="text-xs text-gray-500 dark:text-white/80">
                                                        <?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>

                                                    <?php if ($unread > 0): ?>
                                                        <div class="w-2.5 h-2.5 bg-blue-600 rounded-full dark:bg-slate-700"></div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="font-normal overflow-hidden text-ellipsis text-xs whitespace-nowrap">
                                                    <?php echo htmlspecialchars($lastMsg, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            </div>

                                            <?php if ($unread > 0): ?>
                                                <div class="absolute right-2 top-3 bg-red-600 text-white text-[11px] leading-none px-1.5 py-1 rounded-full min-w-[18px] text-center">
                                                    <?php echo ($unread > 99) ? '99+' : (string)$unread; ?>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- footer -->
                        <a href="messages.php">
                            <div class="text-center py-4 border-t border-slate-100 text-sm font-medium text-blue-600 dark:text-white dark:border-gray-600">See all Messages</div>
                        </a>

                        <div class="w-3 h-3 absolute -top-1.5 right-3 bg-white border-l border-t rotate-45 max-md:hidden dark:bg-dark3 dark:border-transparent"></div>
                    </div>

                    <!-- profile -->
                    <div  class="rounded-full relative bg-secondery cursor-pointer shrink-0">
                        <img src="assets/images/avatars/avatar-2.jpg" alt="" class="sm:w-9 sm:h-9 w-7 h-7 rounded-full shadow shrink-0"> 
                    </div>
                    <div  class="hidden bg-white rounded-lg drop-shadow-xl dark:bg-slate-700 w-64 border2"
                        uk-drop="offset:6;pos: bottom-right;animate-out: true; animation: uk-animation-scale-up uk-transform-origin-top-right ">
                        
                        <a href="timeline.php">
                            <div class="p-4 py-5 flex items-center gap-4">
                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-10 h-10 rounded-full shadow">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-black">Stell johnson</h4>
                                    <div class="text-sm mt-1 text-blue-600 font-light dark:text-white/70">@mohnson</div>
                                </div>
                            </div>
                        </a>

                        <hr class="dark:border-gray-600/60">

                        <nav class="p-2 text-sm text-black font-normal dark:text-white">
                            <a href="upgrade.html">
                                <div class="flex items-center gap-2.5 hover:bg-secondery p-2 px-2.5 rounded-md dark:hover:bg-white/10 text-blue-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                    </svg>
                                    Upgrade To Premium 
                                </div>
                            </a>  
                            <a href="setting.html">
                                <div class="flex items-center gap-2.5 hover:bg-secondery p-2 px-2.5 rounded-md dark:hover:bg-white/10"> 
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                    </svg>
                                    My Billing 
                                </div>
                            </a>
                            <a href="setting.html">
                                <div class="flex items-center gap-2.5 hover:bg-secondery p-2 px-2.5 rounded-md dark:hover:bg-white/10"> 
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" />
                                    </svg>
                                    Advatacing
                                </div>
                            </a>
                            <a href="setting.html">
                                <div class="flex items-center gap-2.5 hover:bg-secondery p-2 px-2.5 rounded-md dark:hover:bg-white/10"> 
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    My Account
                                </div>
                            </a>
                            <button type="button" class="w-full">
                                <div class="flex items-center gap-2.5 hover:bg-secondery p-2 px-2.5 rounded-md dark:hover:bg-white/10"> 
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                                    </svg>
                                    Night mode
                                    <span class="bg-slate-200/40 ml-auto p-0.5 rounded-full w-9 dark:hover:bg-white/20">
                                        <span class="bg-white block h-4 relative rounded-full shadow-md w-2 w-4 dark:bg-blue-600"></span>
                                    </span>
                                </div>
                            </button>   
                            <hr class="-mx-2 my-2 dark:border-gray-600/60">
                            <a href="logout.php">
                                <div class="flex items-center gap-2.5 hover:bg-secondery p-2 px-2.5 rounded-md dark:hover:bg-white/10"> 
                                    <svg class="w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Log Out 
                                </div>
                            </a>

                        </nav>

                    </div> 

                </div>

            </div>

        </div>

    </div>

</header>

<script>
(function () {
    const badge = document.getElementById('chatBadge');
    const list  = document.getElementById('chatDropdownList');

    function setBadge(count) {
        if (!badge) return;
        count = parseInt(count || 0, 10);
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
            badge.textContent = '';
        }
    }

    
    async function pollUnreadCount() {
        try {
            const res = await fetch('/Business_only3/ajax/user_chat_unread_poll.php', { cache: 'no-store' });
            const data = await res.json();
            if (data && data.ok) {
                setBadge(data.unread || 0);
            }
        } catch (e) {
            // ignore
        }
    }

function esc(s) {
        return String(s || '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function renderDropdown(items, unknownCount) {
        if (!list) return;
        unknownCount = parseInt(unknownCount || 0, 10);

        const named = (items || []).filter(it => {
            const cn = String(it.contact_name || '').trim();
            return cn !== '';
        });

        if ((!named || named.length === 0) && unknownCount <= 0) {
            list.innerHTML = '<div class="p-3 text-sm text-gray-500">No new messages.</div>';
            return;
        }

        const unknownBanner = unknownCount > 0 ?
            `<a href="messages.php" class="block p-3 mb-2 text-sm rounded bg-yellow-50 text-yellow-800 hover:bg-yellow-100">You have <b>${unknownCount}</b> new message(s) from unknown friend code(s). Open Messages to name them.</a>`
            : '';

        list.innerHTML = unknownBanner + named.map(it => {
            const peerCode = esc(it.peer_code);
            const name     = esc(it.peer_display || it.peer_code);
            const msg      = esc(it.last_message || '');
            const time     = esc(it.last_time || '');
            const unread   = parseInt(it.unread_count || 0, 10);

            const href = 'user_sendreply.php?peer=' + encodeURIComponent(peerCode);

            return `
                <a href="${href}" class="relative flex items-center gap-4 p-2 py-3 duration-200 rounded-lg hover:bg-secondery dark:hover:bg-white/10">
                    <div class="relative w-10 h-10 shrink-0">
                        <img src="assets/images/avatars/avatar-2.jpg" alt="" class="object-cover w-full h-full rounded-full">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <div class="mr-auto text-sm text-black dark:text-white font-medium">${name}
                                <div class="text-[11px] text-gray-500">${peerCode}</div>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-white/80">${time}</div>
                            ${unread > 0 ? `<div class="w-2.5 h-2.5 bg-blue-600 rounded-full dark:bg-slate-700"></div>` : ``}
                        </div>
                        <div class="font-normal overflow-hidden text-ellipsis text-xs whitespace-nowrap">${msg}</div>
                    </div>
                    ${unread > 0 ? `<div class="absolute right-2 top-3 bg-red-600 text-white text-[11px] leading-none px-1.5 py-1 rounded-full min-w-[18px] text-center">${unread > 99 ? '99+' : unread}</div>` : ``}
                </a>
            `;
        }).join('');
    }

    let busy = false;
    async function pollUnreadThreads() {
        if (busy) return;
        busy = true;
        try {
            const res = await fetch('/Business_only3/ajax/user_chat_poll.php?mode=unread_threads', { cache: 'no-store' });
            const data = await res.json();
            if (data && data.ok) {
                renderDropdown(data.items || [], data.unknown_unread || 0);
                setBadge(data.total_unread || 0);
            }
        } catch (e) {
        } finally {
            busy = false;
        }
    }

    pollUnreadCount();
    pollUnreadThreads();

    // ✅ Presence keepalive: keep ME online while I stay on a page (no clicks / no navigation)
    // This endpoint bumps my last_seen server-side. All users running this header will stay 'Online'.
    setInterval(() => {
        fetch('/Business_only3/ajax/user_presence_ping.php', { cache: 'no-store' }).catch(() => {});
    }, 20000);

    setInterval(() => { pollUnreadCount(); pollUnreadThreads(); }, 4000);
})();
</script>




<script>
(function(){
  // Presence heartbeat: keep users.last_seen fresh while logged in.
  // Tries multiple URL forms so it works even if the folder name changes.
  const candidates = [
    '/Business_only3/ajax/me_presence_heartbeat.php',
    '/ajax/me_presence_heartbeat.php',
    'ajax/me_presence_heartbeat.php',
    '../ajax/me_presence_heartbeat.php'
  ];

  async function heartbeat(){
    for (const url of candidates) {
      try {
        const res = await fetch(url, {
          cache: 'no-store',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) continue;
        const data = await res.json().catch(()=>null);
        if (data && data.ok) return true;
      } catch(e) {}
    }
    return false;
  }

  // Run immediately, then every 20 seconds (more responsive)
  heartbeat();
  setInterval(heartbeat, 20000);

  // Also bump on activity (cheap & helps if timers pause)
  let t=null;
  function bumpSoon(){
    if (t) return;
    t=setTimeout(()=>{ t=null; heartbeat(); }, 1500);
  }
  ['mousemove','keydown','touchstart','scroll','click'].forEach(ev=>{
    window.addEventListener(ev, bumpSoon, { passive:true });
  });
})();
</script>
