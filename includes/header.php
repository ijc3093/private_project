<?php
// /Business_only3/includes/header.php
require_once __DIR__ . '/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

// session values set by setUserSession()
$userEmail = $_SESSION['user_login'] ?? '';
$userName  = $_SESSION['user_name'] ?? '';
$displayName = ($userName !== '') ? $userName : $userEmail;

// --- Bell badge count (SYSTEM notifications only, exclude chat) ---
$bellCount = 0;

try {
    $email = $userEmail;

    if ($email !== '') {
        // Types you never want to show on bell
        $blockedTypes = [
            'New chat message',
            'Internal Chat',
            'New internal message'
        ];

        $ph = implode(',', array_fill(0, count($blockedTypes), '?'));

        $stmtBadge = $dbh->prepare("
            SELECT COUNT(*)
            FROM notification
            WHERE notireceiver = ?
              AND is_read = 0
              AND notitype NOT IN ($ph)
        ");
        $stmtBadge->execute(array_merge([$email], $blockedTypes));
        $bellCount = (int)$stmtBadge->fetchColumn();
    }
} catch (Throwable $e) {
    $bellCount = 0;
}
?>

<style>
  .notif-link{ position:relative; display:inline-block; }
  .notif-badge{
    position:absolute;
    top:10px;
    right:10px;
    background:red;
    color:#fff;
    border-radius:12px;
    padding:2px 6px;
    font-size:11px;
    font-weight:700;
    line-height:1;
    min-width:18px;
    text-align:center;
  }
  .chat-link{ position:relative; display:inline-block; }
  .chat-badge{
    position:absolute;
    top:10px;
    right:10px;
    background:red;
    color:#fff;
    border-radius:12px;
    padding:2px 6px;
    font-size:11px;
    font-weight:700;
    line-height:1;
    min-width:18px;
    text-align:center;
    display:none;
  }
</style>

<div class="brand clearfix">
  <h4 class="pull-left text-white" style="margin:20px 0 0 20px">
      <i class="fa fa-rocket"></i>&nbsp; Private App
  </h4>

  <h4 class="pull-left text-white" style="margin:20px 0 0 20px">
      Hi, <?php echo htmlentities($displayName); ?>
  </h4>

  <span class="menu-btn"><i class="fa fa-bars"></i></span>

  <ul class="ts-profile-nav">

    <!-- ✅ CHAT ICON -->
    <li>
      <a href="user_feedback.php" class="chat-link">
        <i class="fa fa-comments" style="font-size:18px;"></i>
        <span id="chatBadge" class="chat-badge"></span>
      </a>
    </li>

    <!-- ✅ NOTIFICATION ICON -->
    <li>
      <a href="notification.php" class="notif-link">
        <i class="fa fa-bell" style="font-size:18px;"></i>

        <!-- IMPORTANT: give it an ID for JS polling -->
        <span id="notiBadge" class="notif-badge"
              style="<?php echo ($bellCount > 0) ? '' : 'display:none;'; ?>">
          <?php echo ($bellCount > 99) ? '99+' : (int)$bellCount; ?>
        </span>
      </a>
    </li>

    <!-- ✅ ACCOUNT -->
    <li class="ts-account">
      <a href="#">
        <img
          src="avatar.php?ts=<?php echo time(); ?>"
          class="ts-avatar hidden-side"
          alt="Profile"
          style="width:40px;height:40px;border-radius:50%;object-fit:cover;"
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

  async function safeJsonFetch(url){
    const r = await fetch(url, { cache: 'no-store' });
    const txt = await r.text();
    try { return JSON.parse(txt); } catch(e) { return null; }
  }

  async function pollChat(){
    try {
      const data = await safeJsonFetch('/Business_only3/ajax/user_chat_unread_poll.php');
      if (data && data.ok) setBadge(document.getElementById('chatBadge'), data.unread);
    } catch(e){}
  }

  async function pollNoti(){
    try {
      const data = await safeJsonFetch('/Business_only3/ajax/user_notifications_poll.php');
      if (data && data.ok) setBadge(document.getElementById('notiBadge'), data.unread);
    } catch(e){}
  }

  pollChat();
  pollNoti();
  setInterval(pollChat, 4000);
  setInterval(pollNoti, 5000);
})();
</script>
