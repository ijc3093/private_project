<?php
// /Business_only3/includes/header.php
require_once __DIR__ . '/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

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

/**
 * Fetch unread chat threads ONLY
 * (each peer appears once, disappears after read)
 */
$chatThreads = [];

if ($meCode !== '') {
    try {
        $st = $dbh->prepare("
            SELECT
                f.sender AS peer_code,
                COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), u.friend_code) AS peer_display,
                u.friend_code AS peer_key,
                f.feedbackdata AS last_message,
                f.created_at AS last_time,
                COUNT(*) OVER (PARTITION BY f.sender) AS unread_count
            FROM feedback f
            JOIN users u ON u.friend_code = f.sender
            WHERE f.channel = 'user_user'
              AND f.receiver = ?
              AND f.is_read = 0
            ORDER BY f.created_at DESC
            LIMIT 8
        ");
        $st->execute([$meCode]);
        $chatThreads = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $chatThreads = [];
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
                
                <!-- search -->
                <!-- <div id="search--box" class="xl:w-[680px] sm:w-96 sm:relative rounded-xl overflow-hidden z-20 bg-secondery max-md:hidden w-screen left-0 max-sm:fixed max-sm:top-2 dark:!bg-white/5">
                    <ion-icon name="search" class="absolute left-4 top-1/2 -translate-y-1/2"></ion-icon>
                    <input type="text" placeholder="Search Friends, videos .." class="w-full !pl-10 !font-normal !bg-transparent h-12 !text-sm">
                </div>   -->
                <!-- search dropdown-->
                <div class="hidden uk- open z-10"
                        uk-drop="pos: bottom-center ; animation: uk-animation-slide-bottom-small;mode:click ">
                    
                        <div class="xl:w-[694px] sm:w-96 bg-white dark:bg-dark3 w-screen p-2 rounded-lg shadow-lg -mt-14 pt-14">
                            <div class="flex justify-between px-2 py-2.5 text-sm font-medium"> 
                                <div class=" text-black dark:text-white">Recent</div>
                                <button type="button" class="text-blue-500">Clear</button>
                            </div>
                            <nav class="text-sm font-medium text-black dark:text-white">
                                <a href="#" class=" relative px-3 py-1.5 flex items-center gap-4 hover:bg-secondery rounded-lg dark:hover:bg-white/10"> <img src="assets/images/avatars/avatar-2.jpg" class="w-9 h-9 rounded-full"> <div>   <div> Jesse Steeve </div>  <div class="text-xs text-blue-500 font-medium mt-0.5">  Friend </div>   </div> <ion-icon name="close" class="text-base absolute right-3 top-1/2 -translate-y-1/2 "></ion-icon>  </a>  
                                <a href="#" class=" relative px-3 py-1.5 flex items-center gap-4 hover:bg-secondery rounded-lg dark:hover:bg-white/10"> <img src="assets/images/avatars/avatar-2.jpg" class="w-9 h-9 rounded-full"> <div>   <div>  Martin Gray </div>  <div class="text-xs text-blue-500 font-medium mt-0.5">  Friend </div>   </div> <ion-icon name="close" class="text-base absolute right-3 top-1/2 -translate-y-1/2 "></ion-icon>  </a>  
                                <a href="#" class=" relative px-3 py-1.5 flex items-center gap-4 hover:bg-secondery rounded-lg dark:hover:bg-white/10"> <img src="assets/images/group/group-2.jpg" class="w-9 h-9 rounded-full"> <div>   <div>  Delicious Foods  </div>  <div class="text-xs text-rose-500 font-medium mt-0.5">  Group </div>   </div> <ion-icon name="close" class="text-base absolute right-3 top-1/2 -translate-y-1/2 "></ion-icon>  </a>  
                                <a href="#" class=" relative px-3 py-1.5 flex items-center gap-4 hover:bg-secondery rounded-lg dark:hover:bg-white/10"> <img src="assets/images/group/group-1.jpg" class="w-9 h-9 rounded-full"> <div>   <div> Delicious Foods  </div>  <div class="text-xs text-yellow-500 font-medium mt-0.5">  Page </div>   </div> <ion-icon name="close" class="text-base absolute right-3 top-1/2 -translate-y-1/2 "></ion-icon>  </a>  
                                <a href="#" class=" relative px-3 py-1.5 flex items-center gap-4 hover:bg-secondery rounded-lg dark:hover:bg-white/10"> <img src="assets/images/avatars/avatar-6.jpg" class="w-9 h-9 rounded-full"> <div>   <div>  John Welim </div>  <div class="text-xs text-blue-500 font-medium mt-0.5">  Friend </div>   </div> <ion-icon name="close" class="text-base absolute right-3 top-1/2 -translate-y-1/2 "></ion-icon>  </a>  
                                <a href="#" class="hidden relative  px-3 py-1.5 flex items-center gap-4 hover:bg-secondery rounded-lg dark:hover:bg-white/10"> <ion-icon class="text-2xl" name="search-outline"></ion-icon>  Creative ideas about Business  </a>  
                                <a href="#" class="hidden relative  px-3 py-1.5 flex items-center gap-4 hover:bg-secondery rounded-lg dark:hover:bg-white/10"> <ion-icon class="text-2xl" name="search-outline"></ion-icon>  8 Facts About Writting  </a>  
                            </nav>
                            <hr class="-mx-2 mt-2 hidden">
                            <div class="flex justify-end pr-2 text-sm font-medium text-red-500 hidden"> 
                                <a href="#" class="flex hover:bg-red-50 dark:hover:bg-slate-700 p-1.5 rounded"> <ion-icon name="trash" class="mr-2 text-lg"></ion-icon> Clear your history</a> 
                            </div>
                        </div>
                        
                </div>

                <!-- header icons -->
                <div class="flex items-center sm:gap-4 gap-2 absolute right-5 top-1/2 -translate-y-1/2 text-black">
                    <!-- create -->
                    <button type="button" class="sm:p-2 p-1 rounded-full relative sm:bg-secondery dark:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 max-sm:hidden">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path>
                            </svg>
                        <ion-icon name="add-circle-outline" class="sm:hidden text-2xl "></ion-icon>
                    </button>
                    <div    class="hidden bg-white p-4 rounded-lg overflow-hidden drop-shadow-xl dark:bg-slate-700 md:w-[324px] w-screen border2"
                            uk-drop="offset:6;pos: bottom-right; mode: click; animate-out: true; animation: uk-animation-scale-up uk-transform-origin-top-right ">
                        
                            <h3 class="font-bold text-md"> Create  </h3>

                            <!-- slider -->
                            <!-- <div class="mt-4" tabindex="-1" uk-slider="finite:true;sets: true">

                                <div class="uk-slider-container pb-1">
                                
                                    <ul class="uk-slider-items grid-small" uk-scrollspy="target: > li; cls: uk-animation-scale-up , uk-animation-slide-right-small; delay: 20 ;repeat: true">
                                        <li class="w-28" uk-scrollspy-class="uk-animation-fade">
                                            <div class="p-3 px-4 rounded-lg bg-teal-100/60 text-teal-600 dark:text-white dark:bg-dark4">
                                                <ion-icon name="book" class="text-2xl drop-shadow-md"></ion-icon>
                                                <div class="mt-1.5 text-sm font-medium"> Story </div>
                                            </div>
                                        </li>   
                                        <li class="w-28">
                                            <div class="p-3 px-4 rounded-lg bg-sky-100/60 text-sky-600 dark:text-white dark:bg-dark4">
                                                <ion-icon name="camera" class="text-2xl drop-shadow-md"></ion-icon>
                                                <div class="mt-1.5 text-sm font-medium"> Post </div>
                                            </div>
                                        </li> 
                                    </ul>
                            
                                </div> -->
                            
                                <!-- slide nav icons -->
                                <!-- <div class="dark:hidden">
                                    <a class="absolute -translate-y-1/2 top-1/2 -left-4 flex items-center w-8 h-full px-1.5 justify-start bg-gradient-to-r from-white via-white dark:from-slate-600 dark:via-slate-500 dark:from-transparent dark:via-transparent" href="#" uk-slider-item="previous"> <ion-icon name="chevron-back" class="text-xl dark:text-white"></ion-icon> </a>
                                    <a class="absolute -translate-y-1/2 top-1/2 -right-4 flex items-center w-8 h-full px-1.5 justify-end bg-gradient-to-l from-white via-white dark:from-transparent dark:via-transparent" href="#" uk-slider-item="next">  <ion-icon name="chevron-forward" class="text-xl dark:text-white"></ion-icon> </a>
                                </div> -->
        

                                <!-- slide nav -->
                                <!-- <div class="justify-center mt-2 -mb-2 hidden dark:flex">
                                    <ul class="inline-flex flex-wrap justify-center gap-1 uk-dotnav uk-slider-nav"> </ul>
                                </div> -->

                            <!-- </div> -->

                            <!-- list -->
                            <!-- <ul class="-m-1 mt-4 pb-1 text-xs text-gray-500 dark:text-white" uk-scrollspy="target: > li; cls: uk-animation-scale-up , uk-animation-slide-bottom-small ;repeat: true">
                                <li class="flex items-center gap-4 hover:bg-secondery rounded-md p-1.5 cursor-pointer dark:hover:bg-white/10">
                                    <img src="assets/images/icons/group.png" alt="" class="w-7">
                                    <div class="flex-1">
                                        <a href="timeline.html"><h4 class="font-medium text-sm text-black dark:text-white"> Groups </h4></a>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-white"> Meet people with similar interests. </div>
                                    </div>
                                </li>
                                <li class="flex items-center gap-4 hover:bg-secondery rounded-md p-1.5 cursor-pointer dark:hover:bg-white/10">
                                    <img src="assets/images/icons/page.png" alt="" class="w-7">
                                    <div class="flex-1">
                                        <a href="timeline.html"><h4 class="font-medium text-sm text-black dark:text-white"> Pages </h4></a>
                                        <div class="mt-1"> Find and connect with businesses.
                                    </div>
                                </li>
                            </ul> -->


                    </div>

                    <!-- notification -->
                    <button type="button" class="sm:p-2 p-1 rounded-full relative sm:bg-secondery dark:text-white" uk-tooltip="title: Notification; pos: bottom; offset:6">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 max-sm:hidden">
                            <path d="M5.85 3.5a.75.75 0 00-1.117-1 9.719 9.719 0 00-2.348 4.876.75.75 0 001.479.248A8.219 8.219 0 015.85 3.5zM19.267 2.5a.75.75 0 10-1.118 1 8.22 8.22 0 011.987 4.124.75.75 0 001.48-.248A9.72 9.72 0 0019.266 2.5z" />
                            <path fill-rule="evenodd" d="M12 2.25A6.75 6.75 0 005.25 9v.75a8.217 8.217 0 01-2.119 5.52.75.75 0 00.298 1.206c1.544.57 3.16.99 4.831 1.243a3.75 3.75 0 107.48 0 24.583 24.583 0 004.83-1.244.75.75 0 00.298-1.205 8.217 8.217 0 01-2.118-5.52V9A6.75 6.75 0 0012 2.25zM9.75 18c0-.034 0-.067.002-.1a25.05 25.05 0 004.496 0l.002.1a2.25 2.25 0 11-4.5 0z" clip-rule="evenodd" />
                        </svg>
                        <!-- <div class="absolute top-0 right-0 -m-1 bg-red-600 text-white text-xs px-1 rounded-full">6</div> -->
                        <ion-icon name="notifications-outline" class="sm:hidden text-2xl"></ion-icon>
                    </button> 
                    <div  class="hidden bg-white pr-1.5 rounded-lg drop-shadow-xl dark:bg-slate-700 md:w-[365px] w-screen border2"
                        uk-drop="offset:6;pos: bottom-right; mode: click; animate-out: true; animation: uk-animation-scale-up uk-transform-origin-top-right ">
                    
                        <!-- heading -->
                        <div class="flex items-center justify-between gap-2 p-4 pb-2">
                            <h3 class="font-bold text-xl"> Notifications </h3>

                            <div class="flex gap-2.5"> 
                                <button type="button" class="p-1 flex rounded-full focus:bg-secondery dark:text-white"> <ion-icon class="text-xl" name="ellipsis-horizontal"></ion-icon> </button>
                                <div  class="w-[280px] group" uk-dropdown="pos: bottom-right; animation: uk-animation-scale-up uk-transform-origin-top-right; animate-out: true; mode: click; offset:5"> 
                                    <nav class="text-sm"> 
                                        <a href="#"> <ion-icon class="text-xl shrink-0" name="checkmark-circle-outline"></ion-icon>  Mark all as read</a>  
                                        <a href="#"> <ion-icon class="text-xl shrink-0" name="settings-outline"></ion-icon> Notification setting</a>  
                                        <a href="#"> <ion-icon class="text-xl shrink-0" name="notifications-off-outline"></ion-icon> Mute Notification </a>  
                                    </nav>
                                </div> 
                            </div>
                        </div>

                        <div class="text-sm h-[400px] w-full overflow-y-auto pr-2">
                            
                            <!-- contents list -->
                            <div class="pl-2 p-1 text-sm font-normal dark:text-white">
                                
                                <a href="#" class="relative flex items-center gap-3 p-2 duration-200 rounded-xl pr-10 hover:bg-secondery dark:hover:bg-white/10 bg-teal-500/5">
                                    <div class="relative w-12 h-12 shrink-0"> <img src="assets/images/avatars/avatar-3.jpg" alt="" class="object-cover w-full h-full rounded-full"></div>
                                    <div class="flex-1 ">
                                        <p> <b class="font-bold mr-1"> Alexa Gray</b> started following you. Welcome him to your profile. 👋 </p>
                                        <div class="text-xs text-gray-500 mt-1.5 dark:text-white/80"> 4 hours ago </div>
                                        <div class="w-2.5 h-2.5 bg-teal-600 rounded-full absolute right-3 top-5"></div>
                                    </div>
                                </a>
                                <a href="#" class="relative flex items-center gap-3 p-2 duration-200 rounded-xl pr-10 hover:bg-secondery dark:hover:bg-white/10">
                                    <div class="relative w-12 h-12 shrink-0"> <img src="assets/images/avatars/avatar-7.jpg" alt="" class="object-cover w-full h-full rounded-full"></div>
                                    <div class="flex-1 ">
                                        <p> <b class="font-bold mr-1">Jesse Steeve</b> mentioned you in a story. Check it out and reply. 📣 </p>
                                        <div class="text-xs text-gray-500 mt-1.5 dark:text-white/80"> 8 hours ago </div> 
                                    </div>
                                </a>
                                <a href="#" class="relative flex items-center gap-3 p-2 duration-200 rounded-xl pr-10 hover:bg-secondery dark:hover:bg-white/10">
                                    <div class="relative w-12 h-12 shrink-0"> <img src="assets/images/avatars/avatar-6.jpg" alt="" class="object-cover w-full h-full rounded-full"></div>
                                    <div class="flex-1 ">
                                        <p> <b class="font-bold mr-1"> Alexa stella</b> commented on your photo  “Wow, stunning shot!” 💬 </p>
                                        <div class="text-xs text-gray-500 mt-1.5 dark:text-white/80"> 8 hours ago </div> 
                                    </div>
                                </a>
                                <a href="#" class="relative flex items-center gap-3 p-2 duration-200 rounded-xl pr-10 hover:bg-secondery dark:hover:bg-white/10">
                                    <div class="relative w-12 h-12 shrink-0"> <img src="assets/images/avatars/avatar-2.jpg" alt="" class="object-cover w-full h-full rounded-full"></div>
                                    <div class="flex-1 "> 
                                        <p> <b class="font-bold mr-1"> John Michael</b> who you might know,  is on socialite.</p>
                                        <div class="text-xs text-gray-500 mt-1.5 dark:text-white/80"> 2 hours ago </div>
                                    </div>
                                    <button type="button" class="button text-white bg-primary">fallow</button>
                                </a>
                                <a href="#" class="relative flex items-center gap-3 p-2 duration-200 rounded-xl pr-10 hover:bg-secondery dark:hover:bg-white/10 bg-teal-500/5">
                                    <div class="relative w-12 h-12 shrink-0"> <img src="assets/images/avatars/avatar-3.jpg" alt="" class="object-cover w-full h-full rounded-full"></div>
                                    <div class="flex-1 ">
                                        <p> <b class="font-bold mr-1"> Sarah Gray</b> sent you a message. He wants to chat with you. 💖 </p>
                                        <div class="text-xs text-gray-500 mt-1.5 dark:text-white/80"> 4 hours ago </div>
                                        <div class="w-2.5 h-2.5 bg-teal-600 rounded-full absolute right-3 top-5"></div>
                                    </div>
                                </a>
                                <a href="#" class="relative flex items-center gap-3 p-2 duration-200 rounded-xl pr-10 hover:bg-secondery dark:hover:bg-white/10">
                                    <div class="relative w-12 h-12 shrink-0"> <img src="assets/images/avatars/avatar-4.jpg" alt="" class="object-cover w-full h-full rounded-full"></div>
                                    <div class="flex-1 ">
                                        <p> <b class="font-bold mr-1"> Jesse Steeve</b> sarah tagged you <br> in a photo of your birthday party. 📸 </p>
                                        <div class="text-xs text-gray-500 mt-1.5 dark:text-white/80"> 8 hours ago </div>
                                    </div> 
                                </a>
                                <a href="#" class="relative flex items-center gap-3 p-2 duration-200 rounded-xl pr-10 hover:bg-secondery dark:hover:bg-white/10">
                                    <div class="relative w-12 h-12 shrink-0"> <img src="assets/images/avatars/avatar-2.jpg" alt="" class="object-cover w-full h-full rounded-full"></div>
                                    <div class="flex-1 ">
                                        <p> <b class="font-bold mr-1"> Lewis Lewis</b> mentioned you in a story. Check it out and reply. 📣 </p>
                                        <div class="text-xs text-gray-500 mt-1.5 dark:text-white/80"> 8 hours ago </div> 
                                    </div>
                                </a> 
                                <a href="#" class="relative flex items-center gap-3 p-2 duration-200 rounded-xl pr-10 hover:bg-secondery dark:hover:bg-white/10">
                                    <div class="relative w-12 h-12 shrink-0"> <img src="assets/images/avatars/avatar-7.jpg" alt="" class="object-cover w-full h-full rounded-full"></div>
                                    <div class="flex-1 ">
                                        <p> <b class="font-bold mr-1"> Martin Gray</b> liked your photo of the Eiffel Tower. 😍 </p>
                                        <div class="text-xs text-gray-500 mt-1.5 dark:text-white/80"> 8 hours ago </div> 
                                    </div>
                                </a>
                                
                            </div>

                        </div> 


                        <!-- footer -->
                        <a href="notification.php">
                            <div class="text-center py-4 border-t border-slate-100 text-sm font-medium text-blue-600 dark:text-white dark:border-gray-600">  View Notifications </div>
                        </a>

                        <div class="w-3 h-3 absolute -top-1.5 right-3 bg-white border-l border-t rotate-45 max-md:hidden dark:bg-dark3 dark:border-transparent"></div>
                    </div>

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
                        <div  class="hidden bg-white pr-1.5 rounded-lg drop-shadow-xl dark:bg-slate-700 md:w-[360px] w-screen border2"
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
                            
                            <div class="p-2 pt-0 pr-1 dark:text-white/80" id="chatDropdownList"><!-- ✅ chat Dropdown List  -->
                                        <div class="p-3 text-sm text-gray-500">No new messages.</div>
                                <?php if (empty($chatThreads)): ?>
                                    <div class="p-4 text-sm text-gray-500">No chats yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($chatThreads as $t): ?>
                                            <?php
                                                $peerCode = (string)($t['peer_code'] ?? '');
                                                $peerName = (string)($t['peer_display'] ?? $peerCode);
                                                $lastMsg  = (string)($t['last_message'] ?? '');
                                                $lastTime = (string)($t['last_time'] ?? '');
                                                $unread   = (int)($t['unread_count'] ?? 0);

                                                // ✅ Click goes to sendreply, then sendreply redirects to messages.php?peer=<friend_code>
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
                                                            <div class="text-[11px] text-gray-500">
                                                                <?php echo htmlspecialchars($peerCode, ENT_QUOTES, 'UTF-8'); ?>
                                                            </div>
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
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                            </div>
                        </div> 


                        <!-- footer -->
                        <a href="messages.php">
                            <div class="text-center py-4 border-t border-slate-100 text-sm font-medium text-blue-600 dark:text-white dark:border-gray-600">   See all Messages  </div>
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

                    <div class="flex items-center gap-2 hidden">

                        <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-9 h-9 rounded-full shadow">

                        <div class="w-20 font-semibold text-gray-600"> Hamse </div>

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg> 

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

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, (m) => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        }[m]));
    }

    function renderDropdown(items) {
        if (!list) return;

        if (!items || items.length === 0) {
            list.innerHTML = '<div class="p-3 text-sm text-gray-500">No new messages.</div>';
            return;
        }

        list.innerHTML = items.map(it => {
            const peerCode = esc(it.peer_code);
            const name     = esc(it.peer_display || it.peer_code);
            const msg      = esc(it.last_message || '');
            const time     = esc(it.last_time || '');
            const unread   = parseInt(it.unread_count || 0, 10);

            // IMPORTANT: user_sendreply expects ?to=FRIEND_CODE
            const href = 'user_sendreply.php?to=' + encodeURIComponent(peerCode);

            return `
                <a href="${href}" class="relative flex items-center gap-4 p-2 py-3 duration-200 rounded-lg hover:bg-secondery dark:hover:bg-white/10">
                    <div class="relative w-10 h-10 shrink-0">
                        <img src="assets/images/avatars/avatar-2.jpg" alt="" class="object-cover w-full h-full rounded-full">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <div class="mr-auto text-sm text-black dark:text-white font-medium">${name}</div>
                            <div class="text-xs text-gray-500 dark:text-white/80">${time}</div>
                            ${unread > 0 ? `<div class="w-2.5 h-2.5 bg-blue-600 rounded-full dark:bg-slate-700"></div>` : ``}
                        </div>
                        <div class="font-normal overflow-hidden text-ellipsis text-xs whitespace-nowrap">${msg}</div>
                        <div class="text-[11px] text-gray-500">${peerCode}</div>
                    </div>

                    ${unread > 0 ? `<div class="absolute right-2 top-3 bg-red-600 text-white text-[11px] leading-none px-1.5 py-1 rounded-full min-w-[18px] text-center">${unread > 99 ? '99+' : unread}</div>` : ``}
                </a>
            `;
        }).join('');
    }

    let busyUnread = false;
    let busyDrop   = false;

    async function pollUnreadBadge() {
        if (busyUnread) return;
        busyUnread = true;
        try {
            const res = await fetch('ajax/user_chat_unread_poll.php', { cache: 'no-store' });
            const data = await res.json();
            if (data && data.ok) setBadge(data.unread);
        } catch (e) {
        } finally {
            busyUnread = false;
        }
    }

    async function pollDropdown() {
        if (!list || busyDrop) return;
        busyDrop = true;

        try {
            const res = await fetch('ajax/user_chat_poll.php?mode=unread_threads', { cache: 'no-store' });
            const data = await res.json();
            if (data && data.ok) {
                renderDropdown(data.items || []);
                // Optional: keep badge consistent even if unread_poll lags
                if (typeof data.total_unread !== 'undefined') setBadge(data.total_unread);
            }
        } catch (e) {
        } finally {
            busyDrop = false;
        }
    }

    // initial + interval
    pollUnreadBadge();
    pollDropdown();
    setInterval(() => {
        pollUnreadBadge();
        pollDropdown();   // this is what makes peers disappear after reading
    }, 4000);
})();
</script>







