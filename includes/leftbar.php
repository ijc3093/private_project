<?php
require_once __DIR__ . '/session_user.php';
requireUserLogin();
?>
<nav class="ts-sidebar">
    <ul class="ts-sidebar-menu">

        <li><a href="dashboard.php"><i class="fa fa-user"></i> &nbsp;Dashboard</a></li>
        <li><a href="profile.php"><i class="fa fa-user"></i> &nbsp;Profile</a></li>
        <li><a href="feedback.php"><i class="fa fa-envelope"></i> &nbsp;New Compose</a></li>

        <li>
            <a href="notification.php">
                <i class="fa fa-bell"></i> &nbsp;Notification
                <span id="notiBadge" class="badge" style="display:none;background:red;margin-left:6px;">0</span>
            </a>
        </li>

        <li><a href="messages.php"><i class="fa fa-user-times"></i> &nbsp;Message</a></li>
        <li><a href="logout.php"><i class="fa fa-sign-out"></i> &nbsp;Logout</a></li>
    </ul>
</nav>

<script>
(function() {
  const badge = document.getElementById('notiBadge');

  async function refreshUnread() {
    try {
      const res = await fetch('api/unread_count.php', { cache: 'no-store' });
      const data = await res.json();

      if (!data.ok) return;

      const n = Number(data.count || 0);
      if (n > 0) {
        badge.textContent = n;
        badge.style.display = 'inline-block';
      } else {
        badge.textContent = '0';
        badge.style.display = 'none';
      }
    } catch (e) {
      // ignore silently
    }
  }

  refreshUnread();
  setInterval(refreshUnread, 5000); // every 5 seconds
})();
</script>
