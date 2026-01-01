<?php
require_once __DIR__ . '/session_user.php';
requireUserLogin();

// session values set by setUserSession()
$userEmail = $_SESSION['user_login'] ?? '';
$userName  = $_SESSION['user_name'] ?? '';

$displayName = ($userName !== '') ? $userName : $userEmail;
?>

<div class="brand clearfix">
    <h4 class="pull-left text-white" style="margin:20px 0 0 20px">
        <i class="fa fa-rocket"></i>&nbsp; Gospel
    </h4>

    <h4 class="pull-left text-white" style="margin:20px 0 0 20px">
        Hi, <?php echo htmlentities($displayName); ?>
    </h4>

    <span class="menu-btn"><i class="fa fa-bars"></i></span>

    <ul class="ts-profile-nav">

        <!-- ✅ CHAT ICON + BADGE -->
        <li >
            <a href="messages.php" style="position:relative;display:inline-block;">
                <i class="fa fa-comments" style="font-size:18px;"></i>
                <span id="chatBadge"
                      style="display:none;position:absolute;top:10px;right:10px;background:red;color:#fff;border-radius:10px;padding:2px 6px;font-size:11px;font-weight:700;">
                </span>
            </a>
        </li>

        <!-- ✅ NOTIFICATION BELL + BADGE -->
        <li >
            <a href="notification.php" style="position:relative;display:inline-block;">
                <i class="fa fa-bell" style="font-size:18px;"></i>
                <span id="notiBadge"
                      style="display:none;position:absolute;top:10px;right:10px;background:red;color:#fff;border-radius:10px;padding:2px 6px;font-size:11px;font-weight:700;">
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
  function setBadge(id, n){
    const el = document.getElementById(id);
    if(!el) return;

    n = parseInt(n || 0, 10);
    if(n > 0){
      el.style.display = 'inline-block';
      el.textContent = (n > 99) ? '99+' : n;
    }else{
      el.style.display = 'none';
      el.textContent = '';
    }
  }

  async function pollBadges(){
    try{
      // 🔔 Notifications unread
      const nRes = await fetch('ajax/notifications_poll.php', { cache: 'no-store' });
      const nData = await nRes.json();
      if(nData && nData.ok) setBadge('notiBadge', nData.unread);

      // 💬 Chat unread (Admin -> user)
      const cRes = await fetch('ajax/chat_unread_poll.php', { cache:'no-store' });
      const cData = await cRes.json();
      if(cData && cData.ok) setBadge('chatBadge', cData.unread);

    }catch(e){
      // ignore
    }
  }

  pollBadges();
  setInterval(pollBadges, 4000);
})();
</script>
