<?php
// /Business_only3/timeline.php
declare(strict_types=1);
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();
require_once __DIR__ . '/admin/controller.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');


$controller = new Controller();
$dbh = $controller->pdo();

// session values set by setUserSession()
$userEmail = $_SESSION['user_login'] ?? '';
$userName  = $_SESSION['user_name'] ?? '';

$displayName = ($userName !== '') ? $userName : $userEmail;

$msg = '';
$error = '';

// ------------------------------------
// Helper: generate friend code XXXX-XXXX-XXXX
// ------------------------------------
function generateFriendCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // avoid confusing chars (I,O,1,0)
    $make = function(int $n) use ($chars) {
        $out = '';
        for ($i=0; $i<$n; $i++) {
            $out .= $chars[random_int(0, strlen($chars)-1)];
        }
        return $out;
    };
    return $make(4) . '-' . $make(4) . '-' . $make(4);
}

// ------------------------------------
// LOAD USER DATA (by session email)
// ------------------------------------
$sessionEmail = $_SESSION['user_login'] ?? '';

$sql = "SELECT id, name, username, email, friend_code, mobile, designation, image_type, friend_code
        FROM users
        WHERE email = :email
        LIMIT 1";
$query = $dbh->prepare($sql);
$query->execute([':email' => $sessionEmail]);
$result = $query->fetch(PDO::FETCH_OBJ);

// if (!$result) {
//     session_destroy();
//     header('location:timeline.php');
//     exit;
// }

// ------------------------------------
// ✅ Ensure Friend Code exists
// ------------------------------------
// LOAD USER DATA (by session email)
$sessionUser_friend_code = (string)($_SESSION['user_friend_code'] ?? '');
if ($sessionUser_friend_code === '') {
    // if your session uses different key, fix it here:
    // e.g. $_SESSION['user_friend_code']
    die("Invalid session.");
}

$sql = "SELECT id, name, username, email, mobile, designation, image_type, friend_code
        FROM users
        WHERE friend_code = :friend_code
        LIMIT 1";
$query = $dbh->prepare($sql);
$query->execute([':friend_code' => $sessionUser_friend_code]);
$result = $query->fetch(PDO::FETCH_OBJ);

if (!$result) {
    session_destroy();
    header("Location: index.php?session=reset");
    exit;
}

/** Generate UNIQUE friend code only if missing */
function makeFriendCode(string $prefix = 'USR'): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $part = function() use ($chars) {
        $s = '';
        for ($i = 0; $i < 4; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
        return $s;
    };
    return strtoupper($prefix . '-' . $part() . '-' . $part());
}

function ensureFriendCode(PDO $dbh, int $userId): string {
    // re-check from DB (source of truth)
    $st = $dbh->prepare("SELECT friend_code FROM users WHERE id = :id LIMIT 1");
    $st->execute([':id' => $userId]);
    $code = trim((string)$st->fetchColumn());

    if ($code !== '') return $code;

    // generate once and save (unique index guarantees uniqueness)
    for ($i=0; $i<60; $i++) {
        $new = makeFriendCode('USR');
        try {
            $up = $dbh->prepare("UPDATE users SET friend_code = :c WHERE id = :id AND (friend_code IS NULL OR friend_code = '')");
            $up->execute([':c' => $new, ':id' => $userId]);

            // confirm
            $st2 = $dbh->prepare("SELECT friend_code FROM users WHERE id = :id LIMIT 1");
            $st2->execute([':id' => $userId]);
            $final = trim((string)$st2->fetchColumn());
            if ($final !== '') return $final;
        } catch (Throwable $e) {
            // if collision happened, loop again
        }
    }
    return '';
}

$result->friend_code = ensureFriendCode($dbh, (int)$result->id);


// ------------------------------------
// UPDATE PROFILE
// ------------------------------------
if (isset($_POST['submit'])) {

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $mobileno    = trim($_POST['mobile'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    if ($name === '' || $email === '' || $mobileno === '' || $designation === '') {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    }

    // prevent duplicate email (only if changed)
    if ($error === '' && strtolower($email) !== strtolower($result->email)) {
        $dup = $dbh->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $dup->execute([':email' => $email]);
        if ($dup->fetchColumn()) {
            $error = "This email already exists.";
        }
    }

    // ---------------------------------------
    // ✅ DB AVATAR UPLOAD (BLOB)
    // ---------------------------------------
    if ($error === '' && !empty($_FILES['image']['name'])) {
        $allowedTypes = ['image/jpeg','image/png','image/jpg'];
        $mime = mime_content_type($_FILES['image']['tmp_name']);

        if (!in_array($mime, $allowedTypes, true)) {
            $error = "Image must be JPG or PNG.";
        } else {
            $blob = file_get_contents($_FILES['image']['tmp_name']);
            $type = $mime;

            $updImg = $dbh->prepare("
                UPDATE users
                SET image_blob = :b, image_type = :t
                WHERE id = :id
                LIMIT 1
            ");
            $updImg->execute([
                ':b'  => $blob,
                ':t'  => $type,
                ':id' => (int)$result->id
            ]);
        }
    }

    // ---------------------------------------
    // ✅ Update user fields
    // ---------------------------------------
    if ($error === '') {

        $sql = "UPDATE users
                SET name = :name,
                    email = :email,
                    mobile = :mobile,
                    designation = :designation
                WHERE id = :id
                LIMIT 1";

        $upd = $dbh->prepare($sql);
        $ok = $upd->execute([
            ':name'        => $name,
            ':email'       => $email,
            ':mobile'      => $mobileno,
            ':designation' => $designation,
            ':id'          => (int)$result->id
        ]);

        if ($ok) {

            // reload user record
            $query = $dbh->prepare("SELECT id, name, email, mobile, designation, image_type, friend_code FROM users WHERE id = :id LIMIT 1");
            $query->execute([':id' => (int)$result->id]);
            $result = $query->fetch(PDO::FETCH_OBJ);

            // update session
            setUserSession([
                'id'    => (int)$result->id,
                'email' => (string)$result->email,
                'name'  => (string)$result->name,
                'image' => 'db'
            ]);

            header("Location: profile.php?updated=1");
            exit;

        } else {
            $error = "Update failed. Please try again.";
        }
    }
}

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $msg = "Information Updated Successfully";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link href="assets/images/favicon.png" rel="icon" type="image/png">

    <!-- title and description-->
    <title>Socialite</title>
    <meta name="description" content="Socialite - Social sharing network HTML Template">
   
    <!-- css files -->
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">  
    
    <!-- google font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
 
</head>
<body>
 
    <div id="wrapper">

        <!-- header -->
        <?php include __DIR__ . '/includes/header.php'; ?>
    
        <!-- sidebar -->
        <div id="site__sidebar" class="fixed top-0 left-0 z-[99] pt-[--m-top] overflow-hidden transition-transform xl:duration-500 max-xl:w-full max-xl:-translate-x-full">

            <!-- sidebar inner -->
            <div class="p-2 max-xl:bg-white shadow-sm 2xl:w-72 sm:w-64 w-[80%] h-[calc(100vh-64px)] relative z-30 max-lg:border-r dark:max-xl:!bg-slate-700 dark:border-slate-700">
        
                <div class="pr-4" data-simplebar>

                    <!-- LEFTBAR HERE -->
                    <?php include __DIR__ . '/includes/leftbar.php'; ?>

                    <div class="text-xs font-medium flex flex-wrap gap-2 gap-y-0.5 p-2 mt-2">
                        <a href="#" class="hover:underline">About</a>
                        <a href="#" class="hover:underline">Blog </a>
                        <a href="#" class="hover:underline">Careers</a>
                        <a href="#" class="hover:underline">Support</a>
                        <a href="#" class="hover:underline">Contact Us </a>
                        <a href="#" class="hover:underline">Developer</a>
                    </div>

                </div>

            </div>

            <!-- sidebar overly -->
            <div id="site__sidebar__overly" 
                class="absolute top-0 left-0 z-20 w-screen h-screen xl:hidden backdrop-blur-sm"
                uk-toggle="target: #site__sidebar ; cls :!-translate-x-0"> 
            </div>
        </div>

        <!-- main contents -->
        <main id="site__main" class="2xl:ml-[--w-side]  xl:ml-[--w-side-sm] p-2.5 h-[calc(100vh-var(--m-top))] mt-[--m-top]">

            <div class="max-w-[1065px] mx-auto max-lg:-m-2.5">
            
                <!-- cover  -->
                <div class="bg-white shadow lg:rounded-b-2xl lg:-mt-10 dark:bg-dark2">
    
                    <!-- cover -->
                    <div class="relative overflow-hidden w-full lg:h-72 h-48">
                        <img src="assets/images/avatars/profile-cover.jpg" alt="" class="h-full w-full object-cover inset-0">
    
                        <!-- overly -->
                        <div class="w-full bottom-0 absolute left-0 bg-gradient-to-t from-black/60 pt-20 z-10"></div>
    
                        <div class="absolute bottom-0 right-0 m-4 z-20">
                            <div class="flex items-center gap-3">
                                <button class="button bg-white/20 text-white flex items-center gap-2 backdrop-blur-small">Crop</button>
                                <button class="button bg-black/10 text-white flex items-center gap-2 backdrop-blur-small">Edit</button>
                            </div>
                        </div>
    
                    </div>
                        <?php if($error): ?>
                        <div class="errorWrap"><strong>ERROR:</strong> <?php echo htmlentities($error); ?></div>
                        <?php elseif($msg): ?>
                        <div class="succWrap"><strong>SUCCESS:</strong> <?php echo htmlentities($msg); ?></div>
                        <?php endif; ?>
                    <!-- user info -->
                    <div class="p-3">
    
                        <div class="flex flex-col justify-center md:items-center lg:-mt-48 -mt-28">
    
                            <div class="relative lg:h-48 lg:w-48 w-28 h-28 mb-4 z-10">
                                <div class="relative overflow-hidden rounded-full md:border-[6px] border-gray-100 shrink-0 dark:border-slate-900 shadow">
                                    <img src="assets/images/avatars/avatar-6.jpg" alt="" class="h-full w-full object-cover inset-0">                           
                                </div>
                                <button type="button" class="absolute -bottom-3 left-1/2 -translate-x-1/2 bg-white shadow p-1.5 rounded-full sm:flex hidden"> <ion-icon name="camera" class="text-2xl md hydrated" role="img" aria-label="camera"></ion-icon></button>
                            </div>
    
                            <h3 class="md:text-3xl text-base font-bold text-black dark:text-white"> <?php echo htmlentities($displayName); ?> </h3>
    
                            <p class="mt-2 text-gray-500 dark:text-white/80"> Your Friend Code: <?php echo htmlentities($result->friend_code ?? ''); ?>   <a href="#" class="text-blue-500 ml-4 inline-block"> Edit </a></p>
    
                            <p class="mt-2 max-w-xl text-sm md:font-normal font-light text-center hidden"> I love beauty and emotion. 🥰 I’m passionate about photography and learning. 📚 I explore genres and styles. 🌈 I think photography is storytelling. 😊</p>
    
                        </div>
     
                    </div>
    
                    <!-- navigations -->
                    <div class="flex items-center justify-between mt-3 border-t border-gray-100 px-2 max-lg:flex-col dark:border-slate-700" 
                         uk-sticky="offset:50; cls-active: bg-white/80 shadow rounded-b-2xl z-50 backdrop-blur-xl dark:!bg-slate-700/80; animation:uk-animation-slide-top ; media: 992">
                        
                        <div class="flex items-center gap-2 text-sm py-2 pr-1 max-md:w-full lg:order-2">
                            <button class="button bg-primary flex items-center gap-2 text-white py-2 px-3.5 max-md:flex-1">  
                                <ion-icon name="add-circle" class="text-xl"></ion-icon> 
                                <span class="text-sm"> Add Your Story  </span>
                            </button>
                             
                            <button type="submit" class="rounded-lg bg-secondery flex px-2.5 py-2 dark:bg-dark2"> 
                                <ion-icon name="search" class="text-xl">
                            </button>
    
                            <div> 
                                <button type="submit" class="rounded-lg bg-secondery flex px-2.5 py-2 dark:bg-dark3"> 
                                    <ion-icon name="ellipsis-horizontal" class="text-xl">
                                </button>
                                <div  class="w-[240px]" uk-dropdown="pos: bottom-right; animation: uk-animation-scale-up uk-transform-origin-top-right; animate-out: true; mode: click;offset:10"> 
                                    <nav>
                                        <a href="#"> <ion-icon class="text-xl" name="pricetags-outline"></ion-icon> Unfollow </a>  
                                        <a href="#"> <ion-icon class="text-xl" name="time-outline"></ion-icon>  Mute story </a>  
                                        <a href="#"> <ion-icon class="text-xl" name="flag-outline"></ion-icon>  Report </a>  
                                        <a href="#"> <ion-icon class="text-xl" name="share-outline"></ion-icon> Share profile </a>  
                                        <hr>
                                        <a href="#" class="text-red-400 hover:!bg-red-50 dark:hover:!bg-red-500/50"> <ion-icon class="text-xl" name="stop-circle-outline"></ion-icon>  Block </a>  
                                    </nav>
                                </div>
                            </div>
                        </div>
    
                        <nav class="flex gap-0.5 rounded-xl -mb-px text-gray-600 font-medium text-[15px]  dark:text-white max-md:w-full max-md:overflow-x-auto">
                            <a href="#" class="inline-block  py-3 leading-8 px-3.5 border-b-2 border-blue-600 text-blue-600">Timeline</a>
                            <a href="#" class="inline-block py-3 leading-8 px-3.5">Friend <span class="text-xs pl-2 font-normal lg:inline-block hidden">2,680</span></a>
                            <a href="#" class="inline-block py-3 leading-8 px-3.5">Photo</a>
                            <a href="#" class="inline-block py-3 leading-8 px-3.5">Photo</a>
                            <a href="#" class="inline-block py-3 leading-8 px-3.5">Photo</a>
                            <a href="#" class="inline-block py-3 leading-8 px-3.5">Video</a>
                            <a href="#" class="inline-block py-3 leading-8 px-3.5">Group</a>
                         
                            <!-- dropdown -->
                            <div>
                                <a href="#" class="inline-flex items-center gap-2 py-3 leading-8 px-3"> 
                                    More <ion-icon name="chevron-down"></ion-icon>
                                </a>
                                <div  class="md:w-[240px] w-screen" uk-dropdown="pos: bottom-right; animation: uk-animation-scale-up uk-transform-origin-top-right; animate-out: true; mode: click;offset:-4"> 
                                    <nav class="text-[15px]">
                                        <a href="#"> Likes </a>  
                                        <a href="#"> Music </a>  
                                        <a href="#"> Events </a>  
                                        <a href="#"> Books </a>  
                                        <a href="#"> Reviews given </a>  
                                        <a href="#"> Groups  </a>  
                                        <a href="#"> Manage Sections  </a>  
                                    </nav>
                                </div>
                            </div>
                             
                        </nav>
    
                    </div>
    
                </div>
    
                <div class="flex 2xl:gap-12 gap-10 mt-8 max-lg:flex-col" id="js-oversized">
    
                    <!-- feed story -->
    
                    <div class="flex-1 xl:space-y-6 space-y-3">
                        
                        <!-- add story -->
                        <div class="bg-white rounded-xl shadow-sm p-4 space-y-4 text-sm font-medium border1 dark:bg-dark2">
    
                            <div class="flex items-center gap-3">
                                <div class="flex-1 bg-slate-100 hover:bg-opacity-80 transition-all rounded-lg cursor-pointer dark:bg-dark3" uk-toggle="target: #create-status"> 
                                    <div class="py-2.5 text-center dark:text-white"> What do you have in mind? </div>
                                </div>
                                <div class="cursor-pointer hover:bg-opacity-80 p-1 px-1.5 rounded-lg transition-all bg-pink-100/60 hover:bg-pink-100" uk-toggle="target: #create-status">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 stroke-pink-600 fill-pink-200/70" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2c3e50" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M15 8h.01" />
                                        <path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9z" />
                                        <path d="M3.5 15.5l4.5 -4.5c.928 -.893 2.072 -.893 3 0l5 5" />
                                        <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l2.5 2.5" />
                                      </svg>
                                </div>
                                <div class="cursor-pointer hover:bg-opacity-80 p-1 px-1.5 rounded-lg transition-all bg-sky-100/60 hover:bg-sky-100" uk-toggle="target: #create-status">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 stroke-sky-600 fill-sky-200/70 " viewBox="0 0 24 24" stroke-width="1.5" stroke="#2c3e50" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M15 10l4.553 -2.276a1 1 0 0 1 1.447 .894v6.764a1 1 0 0 1 -1.447 .894l-4.553 -2.276v-4z" />
                                        <path d="M3 6m0 2a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2z" />
                                    </svg>
                                </div> 
                            </div>
                            
                        </div>
                        
                        <!--  post image-->
                        <div class="bg-white rounded-xl shadow-sm text-sm font-medium border1 dark:bg-dark2">
    
                            <!-- post heading -->
                            <div class="flex gap-3 sm:p-4 p-2.5 text-sm font-medium">
                                <a href="timeline.html"> <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-9 h-9 rounded-full"> </a>  
                                <div class="flex-1">
                                    <a href="timeline.html"> <h4 class="text-black dark:text-white"> Monroe Parker </h4> </a>  
                                    <div class="text-xs text-gray-500 dark:text-white/80"> 2 hours ago</div>
                                </div>
    
                                <div class="-mr-1">
                                    <button type="button" class="button-icon w-8 h-8"> <ion-icon class="text-xl" name="ellipsis-horizontal"></ion-icon> </button>
                                    <div  class="w-[245px]" uk-dropdown="pos: bottom-right; animation: uk-animation-scale-up uk-transform-origin-top-right; animate-out: true; mode: click"> 
                                        <nav> 
                                            <a href="#"> <ion-icon class="text-xl shrink-0" name="bookmark-outline"></ion-icon>  Add to favorites </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0" name="notifications-off-outline"></ion-icon> Mute Notification </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0" name="flag-outline"></ion-icon>  Report this post </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0" name="share-outline"></ion-icon>  Share your profile </a>  
                                            <hr>
                                            <a href="#" class="text-red-400 hover:!bg-red-50 dark:hover:!bg-red-500/50"> <ion-icon class="text-xl shrink-0" name="stop-circle-outline"></ion-icon>  Unfollow </a>  
                                        </nav>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- post image -->
                            <div class="relative w-full lg:h-96 h-full sm:px-4">
                                <img src="assets/images/post/img-2.jpg" alt="" class="sm:rounded-lg w-full h-full object-cover">
                            </div>
    
                            <!-- post icons -->
                            <div class="sm:p-4 p-2.5 flex items-center gap-4 text-xs font-semibold">
                                <div>
                                    <div class="flex items-center gap-2.5">
                                        <button type="button" class="button-icon text-red-500 bg-red-100 dark:bg-slate-700"> <ion-icon class="text-lg" name="heart"></ion-icon> </button>
                                        <a href="#">1,300</a>
                                    </div>
                                    <div    class="p-1 px-2 bg-white rounded-full drop-shadow-md w-[212px] dark:bg-slate-700 text-2xl"
                                            uk-drop="offset:10;pos: top-left; animate-out: true; animation: uk-animation-scale-up uk-transform-origin-bottom-left"> 
                                        
                                        <div class="flex gap-2"  uk-scrollspy="target: > button; cls: uk-animation-scale-up; delay: 100 ;repeat: true">
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> 👍 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> ❤️ </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> 😂 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> 😯 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> 😢 </span></button>
                                        </div>
                                        
                                        <div class="w-2.5 h-2.5 absolute -bottom-1 left-3 bg-white rotate-45 hidden"></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="button-icon bg-slate-200/70 dark:bg-slate-700"> <ion-icon class="text-lg" name="chatbubble-ellipses"></ion-icon> </button>
                                    <span>260</span>
                                </div>
                                <button type="button" class="button-icon ml-auto"> <ion-icon class="text-xl" name="paper-plane-outline"></ion-icon> </button>
                                <button type="button" class="button-icon"> <ion-icon class="text-xl" name="share-outline"></ion-icon> </button>
                            </div>
    
                            <!-- comments -->
                            <div class="sm:p-4 p-2.5 border-t border-gray-100 font-normal space-y-3 relative dark:border-slate-700/40"> 
                        
                                <div class="flex items-start gap-3 relative">
                                    <a href="timeline.html"> <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-6 h-6 mt-1 rounded-full"> </a>
                                    <div class="flex-1">
                                        <a href="timeline.html" class="text-black font-medium inline-block dark:text-white"> Steeve </a>
                                        <p class="mt-0.5">What a beautiful photo! I love it. 😍 </p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 relative">
                                    <a href="timeline.html"> <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-6 h-6 mt-1 rounded-full"> </a>
                                    <div class="flex-1">
                                        <a href="timeline.html" class="text-black font-medium inline-block dark:text-white"> Monroe </a>
                                        <p class="mt-0.5">   You captured the moment.😎 </p>
                                    </div>
                                </div>
    
                                <button type="button" class="flex items-center gap-1.5 text-gray-500 hover:text-blue-500 mt-2">
                                    <ion-icon name="chevron-down-outline" class="ml-auto duration-200 group-aria-expanded:rotate-180"></ion-icon>
                                    More Comment
                                </button>
    
                            </div>
    
                            <!-- add comment -->
                            <div class="sm:px-4 sm:py-3 p-2.5 border-t border-gray-100 flex items-center gap-1 dark:border-slate-700/40">
                                
                                <img src="assets/images/avatars/avatar-7.jpg" alt="" class="w-6 h-6 rounded-full">
                                
                                <div class="flex-1 relative overflow-hidden h-10">
                                    <textarea placeholder="Add Comment...." rows="1" class="w-full resize-none !bg-transparent px-4 py-2 focus:!border-transparent focus:!ring-transparent"></textarea>
    
                                    <div class="!top-2 pr-2" uk-drop="pos: bottom-right; mode: click">
                                        <div class="flex items-center gap-2" uk-scrollspy="target: > svg; cls: uk-animation-slide-right-small; delay: 100 ;repeat: true">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 fill-sky-600">
                                                <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd" />
                                            </svg>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 fill-pink-600">
                                                <path d="M3.25 4A2.25 2.25 0 001 6.25v7.5A2.25 2.25 0 003.25 16h7.5A2.25 2.25 0 0013 13.75v-7.5A2.25 2.25 0 0010.75 4h-7.5zM19 4.75a.75.75 0 00-1.28-.53l-3 3a.75.75 0 00-.22.53v4.5c0 .199.079.39.22.53l3 3a.75.75 0 001.28-.53V4.75z" />
                                            </svg>
                                        </div>
                                    </div>
                                    
    
                                </div>
                                
    
                                <button type="submit" class="text-sm rounded-full py-1.5 px-3.5 bg-secondery"> Replay</button>
                            </div> 
    
                        </div>
    
                        <!-- post text-->
                        <div class="bg-white rounded-xl shadow-sm text-sm font-medium border1 dark:bg-dark2">
    
                            <!-- post heading -->
                            <div class="flex gap-3 sm:p-4 p-2.5 text-sm font-medium">
                                <a href="timeline.html"> <img src="assets/images/avatars/avatar-5.jpg" alt="" class="w-9 h-9 rounded-full"> </a> 
                                <div class="flex-1">
                                    <a href="timeline.html"> <h4 class="text-black dark:text-white"> John Michael </h4> </a> 
                                    <div class="text-xs text-gray-500 dark:text-white/80"> 2 hours ago</div>
                                </div>
    
                                <div class="-mr-1">
                                    <button type="button" class="button__ico w-8 h-8" aria-haspopup="true" aria-expanded="false"> <ion-icon class="text-xl md hydrated" name="ellipsis-horizontal" role="img" aria-label="ellipsis horizontal"></ion-icon> </button>
                                    <div class="w-[245px] uk-dropdown" uk-dropdown="pos: bottom-right; animation: uk-animation-scale-up uk-transform-origin-top-right; animate-out: true; mode: click"> 
                                        <nav> 
                                            <a href="#"> <ion-icon class="text-xl shrink-0 md hydrated" name="bookmark-outline" role="img" aria-label="bookmark outline"></ion-icon>  Add to favorites </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0 md hydrated" name="notifications-off-outline" role="img" aria-label="notifications off outline"></ion-icon> Mute Notification </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0 md hydrated" name="flag-outline" role="img" aria-label="flag outline"></ion-icon>  Report this post </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0 md hydrated" name="share-outline" role="img" aria-label="share outline"></ion-icon>  Share your profile </a>  
                                            <hr>
                                            <a href="#" class="text-red-400 hover:!bg-red-50 dark:hover:!bg-red-500/50"> <ion-icon class="text-xl shrink-0 md hydrated" name="stop-circle-outline" role="img" aria-label="stop circle outline"></ion-icon>  Unfollow </a>  
                                        </nav>
                                    </div>
                                </div>
                            </div>
                             
                            <div class="sm:px-4 p-2.5 pt-0">
                                <p class="font-normal"> Photography is the art of capturing light with a camera. It can be used to create images that tell stories, express emotions, or document reality. it can be fun, challenging, or rewarding. It can also be a hobby, a profession, or a passion. 📷 </p>
                            </div> 
    
                            <!-- post icons -->
                            <div class="sm:p-4 p-2.5 flex items-center gap-4 text-xs font-semibold">
                                <div>
                                    <div class="flex items-center gap-2.5">
                                        <button type="button" class="button-icon text-red-500 bg-red-100 dark:bg-slate-700"> <ion-icon class="text-lg" name="heart"></ion-icon> </button>
                                        <a href="#">1,300</a>
                                    </div>
                                    <div    class="p-1 px-2 bg-white rounded-full drop-shadow-md w-[212px] dark:bg-slate-700 text-2xl"
                                            uk-drop="offset:10;pos: top-left; animate-out: true; animation: uk-animation-scale-up uk-transform-origin-bottom-left"> 
                                        
                                        <div class="flex gap-2"  uk-scrollspy="target: > button; cls: uk-animation-scale-up; delay: 100 ;repeat: true">
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> 👍 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> ❤️ </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> 😂 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> 😯 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"> <span> 😢 </span></button>
                                        </div>
                                        
                                        <div class="w-2.5 h-2.5 absolute -bottom-1 left-3 bg-white rotate-45 hidden"></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="button-icon bg-slate-200/70 dark:bg-slate-700"> <ion-icon class="text-lg" name="chatbubble-ellipses"></ion-icon> </button>
                                    <span>260</span>
                                </div>
                                <button type="button" class="button-icon ml-auto"> <ion-icon class="text-xl" name="paper-plane-outline"></ion-icon> </button>
                                <button type="button" class="button-icon"> <ion-icon class="text-xl" name="share-outline"></ion-icon> </button>
                            </div>
    
                            <!-- comments -->
                            <div class="sm:p-4 p-2.5 border-t border-gray-100 font-normal space-y-3 relative dark:border-slate-700/40"> 
                        
                                <div class="flex items-start gap-3 relative">
                                    <a href="timeline.html"> <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-6 h-6 mt-1 rounded-full"> </a>
                                    <div class="flex-1">
                                        <a href="timeline.html" class="text-black font-medium inline-block dark:text-white"> Steeve </a>
                                        <p class="mt-0.5"> I love taking photos of nature and animals. 🌳🐶</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 relative">
                                    <a href="timeline.html"> <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-6 h-6 mt-1 rounded-full"> </a>
                                    <div class="flex-1">
                                        <a href="timeline.html" class="text-black font-medium inline-block dark:text-white"> Monroe </a>
                                        <p class="mt-0.5">  I enjoy people and emotions. 😊😢 </p>
                                    </div>
                                </div> 
                                <div class="flex items-start gap-3 relative">
                                    <a href="timeline.html"> <img src="assets/images/avatars/avatar-5.jpg" alt="" class="w-6 h-6 mt-1 rounded-full"> </a>
                                    <div class="flex-1">
                                        <a href="timeline.html" class="text-black font-medium inline-block dark:text-white"> Jesse </a>
                                        <p class="mt-0.5">  Photography is my passion. 🎨📸   </p>
                                    </div>
                                </div>
                            </div>
    
                            <!-- add comment -->
                            <div class="sm:px-4 sm:py-3 p-2.5 border-t border-gray-100 flex items-center gap-1 dark:border-slate-700/40">
                                
                                <img src="assets/images/avatars/avatar-7.jpg" alt="" class="w-6 h-6 rounded-full">
                                
                                <div class="flex-1 relative overflow-hidden h-10">
                                    <textarea placeholder="Add Comment...." rows="1" class="w-full resize-none !bg-transparent px-4 py-2 focus:!border-transparent focus:!ring-transparent" aria-haspopup="true" aria-expanded="false"></textarea>
    
                                    <div class="!top-2 pr-2 uk-drop" uk-drop="pos: bottom-right; mode: click">
                                        <div class="flex items-center gap-2" uk-scrollspy="target: > svg; cls: uk-animation-slide-right-small; delay: 100 ;repeat: true">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 fill-sky-600" style="opacity: 0;">
                                                <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 fill-pink-600" style="opacity: 0;">
                                                <path d="M3.25 4A2.25 2.25 0 001 6.25v7.5A2.25 2.25 0 003.25 16h7.5A2.25 2.25 0 0013 13.75v-7.5A2.25 2.25 0 0010.75 4h-7.5zM19 4.75a.75.75 0 00-1.28-.53l-3 3a.75.75 0 00-.22.53v4.5c0 .199.079.39.22.53l3 3a.75.75 0 001.28-.53V4.75z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    
    
                                </div>
                                
    
                                <button type="submit" class="text-sm rounded-full py-1.5 px-3.5 bg-secondery"> Replay</button>
                            </div> 
    
                        </div>
    
                        <!-- placeholder -->
                        <div class="rounded-xl shadow-sm p-4 space-y-4 bg-slate-200/40 animate-pulse border1 dark:bg-dark2">
    
                            <div class="flex gap-3">
                                <div class="w-9 h-9 rounded-full bg-slate-300/20"></div>
                                <div class="flex-1 space-y-3">
                                    <div class="w-40 h-5 rounded-md bg-slate-300/20"></div>
                                    <div class="w-24 h-4 rounded-md bg-slate-300/20"></div>
                                </div>
                                <div class="w-6 h-6 rounded-full bg-slate-300/20"></div>
                            </div>
    
                            <div class="w-full h-52 rounded-lg bg-slate-300/10 my-3"> </div>
    
                            <div class="flex gap-3">
    
                                <div class="w-16 h-5 rounded-md bg-slate-300/20"></div>
    
                                <div class="w-14 h-5 rounded-md bg-slate-300/20"></div>
    
                                <div class="w-6 h-6 rounded-full bg-slate-300/20 ml-auto"></div>
                                <div class="w-6 h-6 rounded-full bg-slate-300/20  "></div>
                            </div>
    
                        </div>
         
        
                    </div>
        
                    <!-- sidebar -->
                     
                    <div class="lg:w-[400px]"> 
          
                        <div class="lg:space-y-4 lg:pb-8 max-lg:grid sm:grid-cols-2 max-lg:gap-6" 
                             uk-sticky="media: 1024; end: #js-oversized; offset: 80">
    
                            <div class="box p-5 px-6">
            
                                <div class="flex items-ce justify-between text-black dark:text-white">
                                    <h3 class="font-bold text-lg"> Intro   </h3>
                                    <a href="#" class="text-sm text-blue-500">Edit</a>
                                </div>
    
                                <ul class="text-gray-700 space-y-4 mt-4 text-sm dark:text-white/80">
    
                                    <li class="flex items-center gap-3"> 
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                        </svg> 
                                        <div>  Live In <span class="font-semibold text-black dark:text-white"> Cairo , Eygept  </span> </div>
                                    </li>
                                    <li class="flex items-center gap-3"> 
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                                        </svg>
                                        <div>  Studied at <span class="font-semibold text-black dark:text-white"> University of Turkey  </span> </div>
                                    </li>
                                    <li class="flex items-center gap-3"> 
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                                        </svg>
                                        
                                        <div>  Works at <span class="font-semibold text-black dark:text-white">  Envanto Martket </span> </div>
                                    </li>
                                    <li class="flex items-center gap-3"> 
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                        </svg>
                                        <div> In <span class="font-semibold text-black dark:text-white"> Relationship  </span></div>
                                    </li>
                                    <li class="flex items-center gap-3"> 
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 19.5v-.75a7.5 7.5 0 00-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                        </svg>
                                        <div>  Flowwed By <span class="font-semibold text-black dark:text-white"> 3,240 People </span> </div>
                                    </li>
                            
                                </ul>
    
                                <!-- Hobbies -->
                                <div class="flex flex-wrap gap-1 text-sm mt-4 font-semibold capitalize">
                                    <div class="inline-flex items-center gap-2 py-0.5 px-2.5 border shadow rounded-full border-gray-100">
                                        Shoping
                                    </div>
                                    <div class="inline-flex items-center gap-2 py-0.5 px-2.5 border shadow rounded-full border-gray-100">
                                        code
                                    </div>
                                    <div class="inline-flex items-center gap-2 py-0.5 px-2.5 border shadow rounded-full border-gray-100">
                                        art
                                    </div>
                                    <div class="inline-flex items-center gap-2 py-0.5 px-2.5 border shadow rounded-full border-gray-100">
                                        design
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-1 text-center text-sm mt-4 mb-2 rounded-lg overflow-hidden">
    
                                    <div class="relative w-full aspect-[4/3]">
                                        <img src="assets/images/avatars/avatar-5.jpg" alt="" class="object-cover w-full h-full inset-0">
                                    </div> 
                                    <div class="relative w-full aspect-[4/3]">
                                        <img src="assets/images/avatars/avatar-7.jpg" alt="" class="object-cover w-full h-full inset-0">
                                    </div> 
                                    <div class="relative w-full aspect-[4/3]">
                                        <img src="assets/images/avatars/avatar-4.jpg" alt="" class="object-cover w-full h-full inset-0">
                                    </div> 
                                    <div class="relative w-full aspect-[4/3]">
                                        <img src="assets/images/avatars/avatar-6.jpg" alt="" class="object-cover w-full h-full inset-0">
                                    </div> 
    
                                </div>
                                
                            </div>
                            
                            <div class="box p-5 px-6">
            
                                <div class="flex items-ce justify-between text-black dark:text-white">
                                    <h3 class="font-bold text-lg"> Friends  
                                        <span class="block text-sm text-gray-500 mt-0. font-normal dark:text-white"> 3489 Friends </span> 
                                    </h3>
                                    <a href="#" class="text-sm text-blue-500">Find Friend</a>
                                </div>
                            
                                <div class="grid grid-cols-3 gap-2 gap-y-5 text-center text-sm mt-4 mb-2">
    
                                    <div>
                                        <div class="relative w-full aspect-square rounded-lg overflow-hidden">
                                            <img src="assets/images/avatars/avatar-7.jpg" alt="" class="object-cover w-full h-full inset-0">
                                        </div> 
                                        <div class="mt-2 line-clamp-1"> Jesse Steeve </div>
                                    </div>
                                    <div>
                                        <div class="relative w-full aspect-square rounded-lg overflow-hidden">
                                            <img src="assets/images/avatars/avatar-2.jpg" alt="" class="object-cover w-full h-full inset-0">
                                        </div> 
                                        <div class="mt-2 line-clamp-1"> John Michael </div>
                                    </div>
                                    <div>
                                        <div class="relative w-full aspect-square rounded-lg overflow-hidden">
                                            <img src="assets/images/avatars/avatar-3.jpg" alt="" class="object-cover w-full h-full inset-0">
                                        </div> 
                                        <div class="mt-2 line-clamp-1"> Monroe Parker </div>
                                    </div>
                                    <div>
                                        <div class="relative w-full aspect-square rounded-lg overflow-hidden">
                                            <img src="assets/images/avatars/avatar-4.jpg" alt="" class="object-cover w-full h-full inset-0">
                                        </div> 
                                        <div class="mt-2 line-clamp-1"> Martin Gray </div>
                                    </div>
                                    <div>
                                        <div class="relative w-full aspect-square rounded-lg overflow-hidden">
                                            <img src="assets/images/avatars/avatar-5.jpg" alt="" class="object-cover w-full h-full inset-0">
                                        </div> 
                                        <div class="mt-2 line-clamp-1"> James Lewis </div>
                                    </div>
                                    <div>
                                        <div class="relative w-full aspect-square rounded-lg overflow-hidden">
                                            <img src="assets/images/avatars/avatar-6.jpg" alt="" class="object-cover w-full h-full inset-0">
                                        </div> 
                                        <div class="mt-2 line-clamp-1"> Alexa stella </div>
                                    </div>
                                    
                                    
                                </div>
    
                                
                            </div>
                            
                            <!-- Groups You Manage  -->
                            <div class="bg-white rounded-xl shadow p-5 px-6 border1 dark:bg-dark2">
                                            
                                <div class="flex items-baseline justify-between text-black dark:text-white">
                                    <h3 class="font-bold text-base"> Suggested Manage </h3>
                                    <a href="#" class="text-sm text-blue-500">See all</a>
                                </div>
    
                                <div class="side-list">
    
                                    <div class="side-list-item">
                                        <a href="timeline-group.html">
                                            <img src="assets/images/avatars/avatar-2.jpg" alt="" class="side-list-image rounded-full">
                                        </a>
                                        <div class="flex-1">
                                            <a href="timeline-group.html"><h4 class="side-list-title">  John Michael</h4></a>
                                            <div class="side-list-info"> Updated 6 day ago </div>
                                        </div>
                                        <button class="button bg-primary-soft dark:text-white">Like</button>
                                    </div>
                                    <div class="side-list-item">
                                        <a href="timeline-group.html">
                                            <img src="assets/images/avatars/avatar-4.jpg" alt="" class="side-list-image rounded-full"> 
                                        </a>
                                        <div class="flex-1">
                                            <a href="timeline-group.html"><h4 class="side-list-title"> Martin Gray</h4></a>
                                            <div class="side-list-info"> Updated 2 month ago </div>
                                        </div>
                                        <button class="button bg-primary-soft dark:text-white">Like</button>
                                    </div>  
                                    <div class="side-list-item">
                                        <a href="timeline-group.html">
                                            <img src="assets/images/avatars/avatar-3.jpg" alt="" class="side-list-image rounded-full">
                                        </a>
                                        <div class="flex-1">
                                            <a href="timeline-group.html"><h4 class="side-list-title"> Monroe Parker</h4></a>
                                            <div class="side-list-info"> Updated 1 week ago </div>
                                        </div>
                                        <button class="button bg-primary-soft dark:text-white">Like</button>
                                    </div>  
                                    <div class="side-list-item">
                                        <a href="timeline-group.html">
                                            <img src="assets/images/avatars/avatar-1.jpg" alt="" class="side-list-image rounded-full">
                                        </a>
                                        <div class="flex-1">
                                            <a href="timeline-group.html"><h4 class="side-list-title"> Jesse Steeve</h4></a>
                                            <div class="side-list-info"> Updated 2 day ago </div>
                                        </div>
                                        <button class="button bg-primary-soft dark:text-white">Like</button>
                                    </div>  
                                    
                                </div>
    
                                <button class="bg-secondery w-full text-black py-1.5 font-medium px-3.5 rounded-md text-sm mt-2 dark:text-white">See all</button>
    
                            </div>
    
                            <!-- Groups You Manage  -->
                            <div class="bg-white rounded-xl shadow p-5 px-6 border1 dark:bg-dark2">
                                            
                                <div class="flex items-baseline justify-between text-black dark:text-white">
                                    <h3 class="font-bold text-base"> Suggested Manage </h3>
                                    <a href="#" class="text-sm text-blue-500">See all</a>
                                </div>
    
                                <div class="side-list">
    
                                    <div class="side-list-item">
                                        <a href="timeline-group.html">
                                            <img src="assets/images/avatars/avatar-2.jpg" alt="" class="side-list-image rounded-md">
                                        </a>
                                        <div class="flex-1">
                                            <a href="timeline-group.html"><h4 class="side-list-title">  John Michael</h4></a>
                                            <div class="side-list-info"> Updated 1 week ago </div>
                                        </div>
                                        <button class="button bg-primary text-white">Like</button>
                                    </div>
                                    <div class="side-list-item">
                                        <a href="timeline-group.html">
                                            <img src="assets/images/avatars/avatar-4.jpg" alt="" class="side-list-image rounded-md"> 
                                        </a>
                                        <div class="flex-1">
                                            <a href="timeline-group.html"><h4 class="side-list-title"> Martin Gray</h4></a>
                                            <div class="side-list-info"> Updated 4 week ago </div>
                                        </div>
                                        <button class="button bg-primary text-white">Like</button>
                                    </div>  
                                    <div class="side-list-item">
                                        <a href="timeline-group.html">
                                            <img src="assets/images/avatars/avatar-3.jpg" alt="" class="side-list-image rounded-md">
                                        </a>
                                        <div class="flex-1">
                                            <a href="timeline-group.html"><h4 class="side-list-title"> Monroe Parker</h4></a>
                                            <div class="side-list-info"> Updated 2 month ago </div>
                                        </div>
                                        <button class="button bg-primary text-white">Like</button>
                                    </div>  
                                
                                    
                                </div>
        
                            </div>
    
                        </div>
                        
                    </div>
    
                </div>
    
            </div>
            
        </main>

    </div>


    <!-- open chat box -->
    <div>
        <button type="button" class="sm:m-10 m-5 px-4 py-2.5 rounded-2xl bg-gradient-to-tr from-blue-500 to-blue-700 text-white shadow fixed bottom-0 right-0 group flex items-center gap-2">
            
            <svg class="w-6 h-6 group-aria-expanded:hidden duration-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"></path>
            </svg>
    
            <div class="text-base font-semibold max-sm:hidden"> Chat </div>
    
            <svg class="w-6 h-6 -mr-1 hidden group-aria-expanded:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 11-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
            </svg>
              
        </button>
        <div  class="bg-white rounded-xl drop-shadow-xl  sm:w-80 w-screen border-t dark:bg-dark3 dark:border-slate-600" id="chat__box"
              uk-drop="offset:10;pos: bottom-right; animate-out: true; animation: uk-animation-scale-up uk-transform-origin-bottom-right; mode: click">
                                              
              <div class="relative">
                <div class="p-5">
                    <h1 class="text-lg font-bold text-black"> Chats </h1>
                </div>
        
                <!-- search input defaul is hidden -->
                <div class="bg-white p-3 absolute w-full top-11 border-b flex gap-2 hidden dark:border-slate-600 dark:bg-slate-700 z-10" 
                     uk-scrollspy="cls:uk-animation-slide-bottom-small ; repeat: true; duration:0" id="search__chat">
                    
                    <div class="relative w-full">
                        <input type="text" class="w-full rounded-3xl dark:!bg-white/10" placeholder="Search">
    
                        <button type="button" class="absolute  right-0  rounded-full shrink-0 px-2 -translate-y-1/2 top-1/2" 
                                uk-toggle="target: #search__chat ; cls: hidden">
                            
                                <ion-icon name="close-outline" class="text-xl flex"></ion-icon>
                        </button>
                    </div>
    
                </div>
                
                <!-- button actions -->
                <div class="absolute top-0 -right-1 m-5 flex gap-2 text-xl">
                    <button uk-toggle="target: #search__chat ; cls: hidden">
                        <ion-icon name="search-outline"></ion-icon>
                    </button>
                    <button uk-toggle="target: #chat__box ; cls: uk-open">
                        <ion-icon name="close-outline"></ion-icon>
                    </button>
                </div>
                
                <!-- tabs -->
                <div class="page-heading bg-slat e-50 ">
                     
                    <nav class="nav__underline -mt-7 px-5">
        
                        <ul class="group" uk-switcher="connect: #chat__tabs ; animation: uk-animation-slide-right-medium, uk-animation-slide-left-medium"> 
                       
                            <li> <a href="#" class="inline-block py-[18px] border-b-2 border-transparent aria-expanded:text-black aria-expanded:border-black aria-expanded:dark:text-white aria-expanded:dark:border-white"> Friends  </a> </li>
                            <li> <a href="#"> Groups </a> </li>
                            
                        </ul> 
        
                    </nav>
        
                </div>
                  
                <!-- tab 2 optional -->
                <div class="grid grid-cols-2 px-3 py-2 bg-slate-50  -mt-12 relative z-10 text-sm border-b  hidden" uk-switcher="connect: #chat__tabs; toggle: * > button ; animation: uk-animation-slide-right uk-animation-slide-top">
                    <button class="bg-white shadow rounded-md py-1.5"> Friends </button>
                    <button> Groups </button>
                </div>
    
                <!-- chat list -->
                <div class="uk-switcher overflow-hidden rounded-xl -mt-8" id="chat__tabs">
    
                    <!-- tab list 1 -->
                    <div class="space-y -m t-5 p-3 text-sm font-medium h-[280px] overflow-y-auto">
        
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-1.jpg" alt="" class="w-7 rounded-full">
                                <div> Jesse Steeve </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-7 rounded-full">
                                <div> John Michael </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-7 rounded-full">
                                <div> Monroe Parker </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-5.jpg" alt="" class="w-7 rounded-full">
                                <div> James Lewis </div>
                            </div>
                        </a> 
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-4.jpg" alt="" class="w-7 rounded-full">
                                <div> Martin Gray </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-6.jpg" alt="" class="w-7 rounded-full">
                                <div> Alexa stella </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-1.jpg" alt="" class="w-7 rounded-full">
                                <div> Jesse Steeve </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-7 rounded-full">
                                <div> John Michael </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-7 rounded-full">
                                <div> Monroe Parker </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-5.jpg" alt="" class="w-7 rounded-full">
                                <div> James Lewis </div>
                            </div>
                        </a>
                        
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-4.jpg" alt="" class="w-7 rounded-full">
                                <div> Martin Gray </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-6.jpg" alt="" class="w-7 rounded-full">
                                <div> Alexa stella </div>
                            </div>
                        </a>
                        
                         
                    </div>
        
                    <!-- tab list 2 -->
                    <div class="space-y -m t-5 p-3 text-sm font-medium h-[280px] overflow-y-auto">
        
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-1.jpg" alt="" class="w-7 rounded-full">
                                <div> Jesse Steeve </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-7 rounded-full">
                                <div> John Michael </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-7 rounded-full">
                                <div> Monroe Parker </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-5.jpg" alt="" class="w-7 rounded-full">
                                <div> James Lewis </div>
                            </div>
                        </a> 
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-4.jpg" alt="" class="w-7 rounded-full">
                                <div> Martin Gray </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-6.jpg" alt="" class="w-7 rounded-full">
                                <div> Alexa stella </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-1.jpg" alt="" class="w-7 rounded-full">
                                <div> Jesse Steeve </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-7 rounded-full">
                                <div> John Michael </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-3.jpg" alt="" class="w-7 rounded-full">
                                <div> Monroe Parker </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-5.jpg" alt="" class="w-7 rounded-full">
                                <div> James Lewis </div>
                            </div>
                        </a>
                        
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-4.jpg" alt="" class="w-7 rounded-full">
                                <div> Martin Gray </div>
                            </div>
                        </a>
                        <a href="#" class="block">
                            <div class="flex items-center gap-3.5 rounded-lg p-2 hover:bg-secondery dark:hover:bg-white/10">
                                <img src="assets/images/avatars/avatar-6.jpg" alt="" class="w-7 rounded-full">
                                <div> Alexa stella </div>
                            </div>
                        </a>
                        
                         
                    </div>
                    
                </div>
    
    
              </div>
              
              <div class="w-3.5 h-3.5 absolute -bottom-2 right-5 bg-white rotate-45 dark:bg-dark3"></div>
        </div>
    </div>

    <!-- create status -->
    <div class="hidden lg:p-20 uk- open" id="create-status" uk-modal="">
   
        <div class="uk-modal-dialog tt relative overflow-hidden mx-auto bg-white shadow-xl rounded-lg md:w-[520px] w-full dark:bg-dark2">

            <div class="text-center py-4 border-b mb-0 dark:border-slate-700">
                <h2 class="text-sm font-medium text-black"> Create Status </h2>

                <!-- close button -->
                <button type="button" class="button-icon absolute top-0 right-0 m-2.5 uk-modal-close">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
     
            </div>
                    
            <div class="space-y-5 mt-3 p-2">
                <textarea class="w-full !text-black placeholder:!text-black !bg-white !border-transparent focus:!border-transparent focus:!ring-transparent !font-normal !text-xl   dark:!text-white dark:placeholder:!text-white dark:!bg-slate-800" name="" id=""  rows="6" placeholder="What do you have in mind?"></textarea>
            </div>

            <div class="flex items-center gap-2 text-sm py-2 px-4 font-medium flex-wrap">
                <button type="button" class="flex items-center gap-1.5 bg-sky-50 text-sky-600 rounded-full py-1 px-2 border-2 border-sky-100 dark:bg-sky-950 dark:border-sky-900">  
                    <ion-icon name="image" class="text-base"></ion-icon>  
                    Image 
                </button>
                <button type="button" class="flex items-center gap-1.5 bg-teal-50 text-teal-600 rounded-full py-1 px-2 border-2 border-teal-100 dark:bg-teal-950 dark:border-teal-900">  
                    <ion-icon name="videocam" class="text-base"></ion-icon>  
                    Video 
                </button>
                <button type="button" class="flex items-center gap-1.5 bg-orange-50 text-orange-600 rounded-full py-1 px-2 border-2 border-orange-100 dark:bg-yellow-950 dark:border-yellow-900">  
                    <ion-icon name="happy" class="text-base"></ion-icon>  
                    Feeling
                </button>
                <button type="button" class="flex items-center gap-1.5 bg-red-50 text-red-600 rounded-full py-1 px-2 border-2 border-rose-100 dark:bg-rose-950 dark:border-rose-900">  
                    <ion-icon name="location" class="text-base"></ion-icon>  
                    Check in 
                </button>
                <button type="button" class="grid place-items-center w-8 h-8 text-xl rounded-full bg-secondery">  
                    <ion-icon name="ellipsis-horizontal"></ion-icon>
                </button>
            </div>
 
            <div class="p-5 flex justify-between items-center">
                <div>
                    <button class="inline-flex items-center py-1 px-2.5 gap-1 font-medium text-sm rounded-full bg-slate-50 border-2 border-slate-100 group aria-expanded:bg-slate-100 aria-expanded: dark:text-white dark:bg-slate-700 dark:border-slate-600" type="button"> 
                        Everyone
                        <ion-icon name="chevron-down-outline" class="text-base duration-500 group-aria-expanded:rotate-180"></ion-icon> 
                    </button>

                    <div  class="p-2 bg-white rounded-lg shadow-lg text-black font-medium border border-slate-100 w-60 dark:bg-slate-700"
                          uk-drop="offset:10;pos: bottom-left; reveal-left;animate-out: true; animation: uk-animation-scale-up uk-transform-origin-bottom-left ; mode:click">
                               
                        <form>
                            <label>
                                <input type="radio" name="radio-status" id="monthly1" class="peer appearance-none hidden" checked />
                                <div class=" relative flex items-center justify-between cursor-pointer rounded-md p-2 px-3 hover:bg-secondery peer-checked:[&_.active]:block dark:bg-dark3">
                                    <div class="text-sm">  Everyone </div> 
                                    <ion-icon name="checkmark-circle" class="hidden active absolute -translate-y-1/2 right-2 text-2xl text-blue-600 uk-animation-scale-up"></ion-icon>
                                </div>
                            </label>
                            <label>
                                <input type="radio" name="radio-status" id="monthly1" class="peer appearance-none hidden" />
                                <div class=" relative flex items-center justify-between cursor-pointer rounded-md p-2 px-3 hover:bg-secondery peer-checked:[&_.active]:block dark:bg-dark3">
                                    <div class="text-sm"> Friends </div> 
                                    <ion-icon name="checkmark-circle" class="hidden active absolute -translate-y-1/2 right-2 text-2xl text-blue-600 uk-animation-scale-up"></ion-icon>
                                </div>
                            </label>
                            <label>
                                <input type="radio" name="radio-status" id="monthly" class="peer appearance-none hidden" />
                                <div class=" relative flex items-center justify-between cursor-pointer rounded-md p-2 px-3 hover:bg-secondery peer-checked:[&_.active]:block dark:bg-dark3">
                                    <div class="text-sm"> Only me </div> 
                                    <ion-icon name="checkmark-circle" class="hidden active absolute -translate-y-1/2 right-2 text-2xl text-blue-600 uk-animation-scale-up"></ion-icon>
                                </div>
                            </label>
                        </form>

                    </div>
                </div>
                <div class="flex items-center gap-2"> 
                    <button type="button" class="button bg-blue-500 text-white py-2 px-12 text-[14px]"> Create</button>
                </div>
            </div>
        
        </div>

    </div>


    <!-- Javascript  -->
    <script src="assets/js/uikit.min.js"></script>
    <script src="assets/js/simplebar.js"></script>
    <script src="assets/js/script.js"></script>
 
 
    <!-- Ion icon -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
 

</body>
</html>