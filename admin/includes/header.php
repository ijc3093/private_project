<?php
/**
 * ==========================================================
 * ADMIN HEADER (GLOBAL SECURITY GATE)
 * File: /Business_only3/admin/includes/header.php
 * ==========================================================
 */

require_once __DIR__ . '/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/../controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

/* ==========================================================
   ✅ GLOBAL ACCOUNT GATE
   - inactive → logout
   - force_password_change → redirect
========================================================== */
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$adminId = (int)($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    clearAdminSession();
    header("Location: index.php");
    exit;
}

// pages allowed when forced
$allowWhenForced = ['change-password.php', 'logout.php'];

$stGate = $dbh->prepare("
    SELECT status, force_password_change
    FROM admin
    WHERE idadmin = :id
    LIMIT 1
");
$stGate->execute([':id' => $adminId]);
$gate = $stGate->fetch(PDO::FETCH_ASSOC);

if (!$gate || (int)$gate['status'] !== 1) {
    clearAdminSession();
    header("Location: index.php");
    exit;
}

if ((int)$gate['force_password_change'] === 1 && !in_array($currentPage, $allowWhenForced, true)) {
    header("Location: change-password.php?force=1");
    exit;
}

/* ==========================================================
   ROLE MAP
========================================================== */
$roleMap = [
    1 => 'Admin',
    2 => 'Manager',
    3 => 'Gospel',
    4 => 'Staff'
];

/* ==========================================================
   LOAD ADMIN PROFILE (SAFE: by ID)
========================================================== */
$stmt = $dbh->prepare("
    SELECT idadmin, fullname, username, email, image, role
    FROM admin
    WHERE idadmin = :id
    LIMIT 1
");
$stmt->execute([':id' => $adminId]);
$user = $stmt->fetch(PDO::FETCH_OBJ);

$adminLogin  = $user->fullname ?? '';
$adminRoleId = (int)($user->role ?? 1);
$roleName    = $roleMap[$adminRoleId] ?? 'Admin';

/* ==========================================================
   AVATAR
========================================================== */
$avatarWeb = '../images/profile.jpg';

if ($user && !empty($user->image)) {
    $imgPath = __DIR__ . '../images/' . $user->image;
    if (file_exists($imgPath)) {
        $avatarWeb = '../images/' . $user->image;
    }
}

$displayName = $adminLogin;
?>

<script>
/* Prevent cached admin pages after logout */
window.addEventListener("pageshow", function (event) {
  if (event.persisted) window.location.reload();
});
</script>

<div class="brand clearfix">
  <h4 class="pull-left text-white" style="margin:20px 0 0 20px">
    <i class="fa fa-rocket"></i>&nbsp; Private App
  </h4>

  <h4 class="pull-left text-white" style="margin:20px 0 0 20px">
    Hi, <?php echo htmlentities($displayName); ?>  • <?php echo htmlentities($roleName); ?>
  </h4>

  <span class="menu-btn"><i class="fa fa-bars"></i></span>

  <ul class="ts-profile-nav">

    <!-- COMPOSE -->
    <li>
      <a href="compose.php" title="Compose">
        <i class="fa fa-pencil-square-o"></i>
      </a>
    </li>

    <!-- CHAT -->
    <li>
      <a href="feedback.php" title="Chat Inbox" style="position:relative;">
        <i class="fa fa-comments"></i>
        <span id="chatBadge"
              style="display:none;position:absolute;top:8px;right:8px;background:red;color:#fff;border-radius:10px;padding:2px 6px;font-size:11px;font-weight:700;">
        </span>
      </a>
    </li>

    <!-- NOTIFICATIONS -->
    <li>
      <a href="notification.php" title="Notifications" style="position:relative;">
        <i class="fa fa-bell"></i>
        <span id="notiBadge"
              style="display:none;position:absolute;top:8px;right:8px;background:red;color:#fff;border-radius:10px;padding:2px 6px;font-size:11px;font-weight:700;">
        </span>
      </a>
    </li>

    <!-- ACCOUNT -->
    <li>
      <a href="#">
        <img src="avatar_admin.php?ts=<?php echo time(); ?>"
             class="ts-avatar hidden-side"
             alt="Profile"
             style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
        Account <i class="fa fa-angle-down hidden-side"></i>
      </a>

      <ul>
        <li><a href="profile.php">My Profile</a></li>
        <li><a href="change-password.php">Change Password</a></li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
    </li>

  </ul>
</div>

<script>
(function(){
  function setBadge(el, n){
    if(!el) return;
    n = parseInt(n||0,10);
    if(n>0){
      el.style.display='inline-block';
      el.textContent = n>99?'99+':n;
    }else{
      el.style.display='none';
    }
  }

  async function pollNotifications(){
    try{
      const r = await fetch('ajax/notifications_poll.php',{cache:'no-store'});
      const d = await r.json();
      if(d && d.ok) setBadge(document.getElementById('notiBadge'), d.unread);
    }catch(e){}
  }

  async function pollChat(){
    try{
      const r = await fetch('ajax/chat_unread_poll.php',{cache:'no-store'});
      const d = await r.json();
      if(d && d.ok) setBadge(document.getElementById('chatBadge'), d.unread);
    }catch(e){}
  }

  pollNotifications();
  pollChat();
  setInterval(pollNotifications,5000);
  setInterval(pollChat,4000);
})();
</script>
