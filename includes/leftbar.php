<?php
// /Business_only3/includes/leftbar.php
require_once __DIR__ . '/session_user.php';
requireUserLogin();
?>
<nav class="ts-sidebar">
  <ul class="ts-sidebar-menu">

    <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> &nbsp;Dashboard</a></li>
    <li><a href="profile.php"><i class="fa fa-user"></i> &nbsp;My Profile</a></li>

    <!-- ✅ CHAT (User ↔ Admin + User ↔ User same-role) -->
    <li>
      <a href="user_feedback.php">
        <i class="fa fa-comments"></i> &nbsp;Chat Inbox
        <span id="chatBadgeSide" class="badge"
              style="display:none;background:red;margin-left:6px;">0</span>
      </a>
    </li>

    <li>
      <a href="compose_user.php">
        <i class="fa fa-pencil"></i> &nbsp;Start a chat
      </a>
    </li>

    <!-- ✅ NOTIFICATIONS -->
    <li>
      <a href="notification.php">
        <i class="fa fa-bell"></i> &nbsp;Notification List
        <span id="notiBadgeSide" class="badge"
              style="display:none;background:red;margin-left:6px;">0</span>
      </a>
    </li>

    <li><a href="logout.php"><i class="fa fa-sign-out"></i> &nbsp;Logout</a></li>
  </ul>
</nav>

<script>
(function() {
  const chatBadge = document.getElementById('chatBadgeSide');
  const notiBadge = document.getElementById('notiBadgeSide');

  function setBadge(el, n){
    if (!el) return;
    n = parseInt(n || 0, 10);
    if (n > 0){
      el.textContent = (n > 99) ? '99+' : n;
      el.style.display = 'inline-block';
    } else {
      el.textContent = '0';
      el.style.display = 'none';
    }
  }

  async function pollChat(){
    try {
      const r = await fetch('/Business_only3/ajax/user_chat_unread_poll.php', { cache: 'no-store' });
      const data = await r.json();
      if (data && data.ok) setBadge(chatBadge, data.unread);
    } catch(e){}
  }

  async function pollNoti(){
    try {
      const r = await fetch('/Business_only3/ajax/user_notifications_poll.php', { cache: 'no-store' });
      const data = await r.json();
      if (data && data.ok) setBadge(notiBadge, data.unread);
    } catch(e){}
  }

  pollChat();
  pollNoti();
  setInterval(pollChat, 4000);
  setInterval(pollNoti, 5000);
})();
</script>
