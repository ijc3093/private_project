<?php
require_once __DIR__ . '/session_admin.php';
requireAdminLogin();

/* ---------------------------
   LOAD CONTROLLER
---------------------------- */
require_once __DIR__ . '/../controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

/* ---------------------------
   ROLE MAP
---------------------------- */
$roleMap = [
    1 => 'Admin',
    2 => 'Manager',
    3 => 'Gospel',
    4 => 'Staff',
    5 => 'Teacher'
];

/* ---------------------------
   ADMIN SESSION
---------------------------- */
$adminLogin  = $_SESSION['admin_login'] ?? '';
$adminRoleId = (int)($_SESSION['userRole'] ?? 0);
$roleName    = $roleMap[$adminRoleId] ?? 'Admin';

/* ---------------------------
   LOAD ADMIN RECORD
---------------------------- */
$user = null;
if ($adminLogin !== '') {
    $stmt = $dbh->prepare("
        SELECT idadmin, username, email, image
        FROM admin
        WHERE username = :u OR email = :e
        LIMIT 1
    ");
    $stmt->execute([
        ':u' => $adminLogin,
        ':e' => $adminLogin
    ]);
    $user = $stmt->fetch(PDO::FETCH_OBJ);
}

/* ---------------------------
   ✅ AVATAR (Admin pages)
---------------------------- */
$avatarWeb = '../images/default.jpg';

if ($user && !empty($user->image)) {
    $abs = __DIR__ . '/../../images/' . $user->image;
    if (file_exists($abs)) {
        $avatarWeb = '../images/' . $user->image;
    }
}

$displayName = ($user && !empty($user->username)) ? $user->username : $adminLogin;
?>

<script>
/* Prevent cached admin pages after logout */
window.addEventListener("pageshow", function (event) {
  if (event.persisted) window.location.reload();
});
</script>

<div class="brand clearfix">
  <h4 class="pull-left text-white" style="margin:20px 0 0 20px">
    <i class="fa fa-rocket"></i>&nbsp; Gospel
  </h4>

  <h4 class="pull-left text-white" style="margin:20px 0 0 20px">
    Hi, <?php echo htmlentities($displayName); ?> as <?php echo htmlentities($roleName); ?>
  </h4>

  <span class="menu-btn"><i class="fa fa-bars"></i></span>

  <ul class="ts-profile-nav">

    <!-- ✅ COMPOSE (NEW MESSAGE) -->
    <li>
      <a href="compose.php" title="Compose" style="position:relative;display:inline-block;">
        <i class="fa fa-pencil-square-o" style="font-size:18px;"></i>
      </a>
    </li>

    <!-- ✅ CHAT ICON -->
    <li>
      <a href="feedback.php" title="Chat Inbox" style="position:relative;display:inline-block;">
        <i class="fa fa-comments" style="font-size:18px;"></i>
        <span id="chatBadge"
              style="display:none;position:absolute;top:10px;right:10px;background:red;color:#fff;border-radius:10px;padding:2px 6px;font-size:11px;font-weight:700;">
        </span>
      </a>
    </li>

    <!-- ✅ NOTIFICATION BELL -->
    <li>
      <a href="notification.php" title="Notifications" style="position:relative;display:inline-block;">
        <i class="fa fa-bell" style="font-size:18px;"></i>
        <span id="notiBadge"
              style="display:none;position:absolute;top:10px;right:10px;background:red;color:#fff;border-radius:10px;padding:2px 6px;font-size:11px;font-weight:700;">
        </span>
      </a>
    </li>

    <!-- ✅ ACCOUNT -->
    <li>
      <a href="#">
        <img
          src="avatar.php?ts=<?php echo time(); ?>"
          class="ts-avatar hidden-side"
          alt="Profile"
          style="width:30px;height:30px;border-radius:60%;object-fit:cover;"
        >
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
  function setBadge(el, n) {
    if (!el) return;
    n = parseInt(n || 0, 10);
    if (n > 0) {
      el.style.display = 'inline-block';
      el.textContent = n > 99 ? '99+' : n;
    } else {
      el.style.display = 'none';
      el.textContent = '';
    }
  }

  // ✅ Use RELATIVE paths (recommended)
  async function pollNotifications(){
    try {
      const r = await fetch('ajax/notifications_poll.php', { cache: 'no-store' });
      const data = await r.json();
      if (data && data.ok) setBadge(document.getElementById('notiBadge'), data.unread);
    } catch(e){}
  }

  async function pollChat(){
    try {
      const r = await fetch('ajax/chat_unread_poll.php', { cache: 'no-store' });
      const data = await r.json();
      if (data && data.ok) setBadge(document.getElementById('chatBadge'), data.unread);
    } catch(e){}
  }

  pollNotifications();
  pollChat();

  setInterval(pollNotifications, 5000);
  setInterval(pollChat, 4000);
})();
</script>
